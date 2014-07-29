<?php
/**
 * Created by PhpStorm.
 * User: yangliu
 * Date: 14-06-25
 * Time: 下午3:36
 */

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class VDTrackController extends RESTController
{

    public function get()
    {
        $result = $this->handleImpression();
        return $this->respond($result);
    }

    private function handleImpression()
    {

        $ad_hash = $this->request->get("ad", null, '');
        $zone_hash = $this->request->get("zone", null, '');
        $mac = $this->request->get("i", null, '');
        $ds = $this->request->get("ds", null, '');
        $dm = $this->request->get("dm", null, '');


        $ad = VdUnit::findFirst(array(
            "hash = '" . $ad_hash . "'"
        ));
        if (!$ad) {
            return $this->codeInputError();
        }

        $resultData = $this->getCacheDataValue(CACHE_PREFIX . "_CLIENT_FREQUENCY_" . $ad->campaign_id . $mac);
        if ($resultData) {
            return $this->codeNoAds();
        }

        $zone = $this->getplacementinfo($zone_hash);
        if (!$zone) {
            return $this->codeInputError();
        }

        $current_time = time();
        $current_date = date('Y-m-d', $current_time);

        $clientfrequency = $this->get_client_frequency($ad->campaign_id, $mac,$current_date);

        $frequency = $this->checkfrequency($ad->campaign_id, $current_date, $mac);

        if ($clientfrequency && $frequency) {

            $phql = "UPDATE VdClientFrequency SET impression=impression+1 WHERE campaign_id=" . $ad->campaign_id . " and mac='" . $mac . "'
                    and vd_date='".$current_date."'";

            $this->modelsManager->executeQuery($phql);

            $this->updateActualImpression($ad->campaign_id,$current_date);

        } elseif (!$clientfrequency && $frequency) {
            $clientimpression = new VdClientFrequency();
            $clientimpression->campaign_id = $ad->campaign_id;
            $clientimpression->mac = $mac;
            $clientimpression->impression = 1;
            $clientimpression->vd_date = $current_date;
            $clientimpression->save();

            $this->updateActualImpression($ad->campaign_id,$current_date);

        } else {
            return $this->codeNoAds();
        }

        $reporting['ip'] = $this->request->getClientAddress(TRUE);
        $reporting['type'] = '1';
        $reporting['publication_id'] = $zone->publication_id;
        $reporting['zone_id'] = $zone->entry_id;
        $reporting['campaign_id'] = $ad->campaign_id;

        $reporting['creative_id'] = $ad->adv_id;
        $reporting['requests'] = 0;
        $reporting['impressions'] = 1;
        $reporting['clicks'] = 0;
        $reporting['timestamp'] = $current_time;

        $reporting['report_hash'] = md5(serialize($reporting));

        $queue = $this->getDi()->get('beanstalkVdReporting');
        $queue->put(serialize($reporting));


        $reporting['equipment_key'] = $mac;

        if (empty($ds)) {
            $reporting['device_name'] = $dm;
        } else {
            $reporting['device_name'] = $ds;
        }
        $this->save_log($reporting, $current_time);


        return $this->codeSuccess();
    }

    private function getplacementinfo($zone_hash)
    {
        $zone = VdZone::findFirst(array(
            "zone_hash = ?0",
            "bind" => array(0 => $zone_hash),
        ));
        return $zone;
    }

    private function get_client_frequency($campaign_id, $mac,$current_date)
    {
        $conditions = "campaign_id = :campaign_id:  AND mac = :mac:  AND vd_date=:vd_date:";
        $params = array('campaign_id' => $campaign_id,
                        'mac' => $mac,
                        'vd_date'=>$current_date
        );
        $clientfrequency = VdClientFrequency::findFirst(array(
            "conditions" => $conditions,
            "bind" => $params
        ));

        return $clientfrequency;

    }

    private function checkfrequency($campaign_id, $current_date, $mac)
    {
        $conditions = "campaign_id = :campaign_id:  AND time_start<=:time_start:  AND time_end>=:time_end:";
        $params = array('campaign_id' => $campaign_id,
            'time_start' => $current_date,
            'time_end' => $current_date
        );
        $schedulefrequency = VdScheduleFrequency::findFirst(array(
            "conditions" => $conditions,
            "bind" => $params
        ));

        if ($schedulefrequency) {
            if ($schedulefrequency->type == 0) {
                $date_start = $current_date;
                $date_end = $current_date;
            } elseif ($schedulefrequency->type == 1) {
                $first = 0; //$first =1 表示每周星期一为开始时间 0表示每周日为开始时间
                $w = date("w", strtotime($current_date)); //获取当前周的第几天 周日是 0 周一 到周六是 1 -6
                $d = $w ? $w - $first : 6; //如果是周日 -6天
                $date_start = date("Y-m-d", strtotime("$current_date -" . $d . " days")); //本周开始时间
                $date_end = date("Y-m-d", strtotime("$date_start +6 days")); //本周结束时间

            } elseif ($schedulefrequency->type == 2) {
                $date_start = date("Y-m-01", strtotime($current_date));
                $date_end = date("Y-m-d", strtotime("$date_start +1 month -1 day"));
            }

            $phql = "SELECT SUM(impression) as impression FROM VdClientFrequency WHERE campaign_id=" . $campaign_id . " and mac='" . $mac . "'
                    and vd_date>='" . $date_start . "'  and vd_date<='" . $date_end . "'";
            $clientfrequency = $this->modelsManager->executeQuery($phql);

            $impression = $clientfrequency[0]->impression ? $clientfrequency[0]->impression : 0;

            if ($schedulefrequency->frequency <= $impression) {
                $time = strtotime($schedulefrequency->time_end) + 86439 - time();
                $this->setMacToMem($campaign_id, $mac, $time);
                return false;
            }

        }

        return true;
    }

    private function setMacToMem($campaign_id, $mac, $time)
    {
        $key = CACHE_PREFIX . "_CLIENT_FREQUENCY_" . $campaign_id . $mac;
        if (!MD_CACHE_ENABLE)
            return false;

        $cacheKey = md5($key);

        $this->getDi()->get("cacheData")->save($cacheKey, true, $time);
    }

    private function updateActualImpression($campaign_id,$current_date){
        $conditions = "campaign_id=:campaign_id:  and vd_date=:vd_date:";
        $params = array("campaign_id"=>$campaign_id,
                        "vd_date"=>$current_date);
        $actualimpression = VdScheduleImpression::findFirst(array(
            "conditions"=>$conditions,
            "bind" => $params,
        ));
        if($actualimpression){
            $actualimpression->actual_impression = $actualimpression->actual_impression + 1;
            $actualimpression->save();
        }
        return true;
    }
    private function save_log($result, $date)
    {

        if (!ENABLE_DEVICE_LOG)
            return false;

        $devReqLog = new VDRequestLog();

        $zone_detail = null;
        $operation_type = null;

        $devReqLog->date = $date;
        $devReqLog->business_id = BUSINESS_ID;
        $devReqLog->client_ip = $this->request->getClientAddress(TRUE);
        $this->debugLog("[save_track_log] client_ip:" . $devReqLog->client_ip);

        $devReqLog->equipment_sn = '';
        $devReqLog->equipment_key = $result['equipment_key'];
        $devReqLog->device_name = $result['device_name'];
        $devReqLog->user_pattern = '';
        $devReqLog->operation_type = '003';
        $devReqLog->operation_extra = '';
        $devReqLog->publication_id = $result['publication_id'];
        $devReqLog->zone_id = $result['zone_id'];
        $devReqLog->campaign_id = $result['campaign_id'];
        $devReqLog->creative_id = $result['creative_id'];

        if (MAD_USE_BEANSTALK) {
            $log['equipment_sn'] = $devReqLog->equipment_sn;
            $log['equipment_key'] = $devReqLog->equipment_key;
            $log['device_name'] = $devReqLog->device_name;
            $log['user_pattern'] = $devReqLog->user_pattern;
            $log['date'] = $devReqLog->date;
            $log['operation_type'] = $devReqLog->operation_type;
            $log['operation_extra'] = $devReqLog->operation_extra;
            $log['publication_id'] = $devReqLog->publication_id;
            $log['zone_id'] = $devReqLog->zone_id;
            $log['campaign_id'] = $devReqLog->campaign_id;
            $log['creative_id'] = $devReqLog->creative_id;
            $log['client_ip'] = $devReqLog->client_ip;
            $log['business_id'] = $devReqLog->business_id;
            try {
                $queue = $this->getDi()->get('beanstalkVdRequestLogInfo');
                $queue->put(serialize($log));
            } catch (Exception $e) {
                $this->debugLog($e->getMessage());
            }
        } else {
            if ($devReqLog->save() == true) {
                return true;
            } else {
                $this->logoDBError($devReqLog);
                return false;
            }
        }
    }

} 