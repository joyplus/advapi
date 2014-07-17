<?php

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDRequestV3Controller extends MDRequestController{

    public function get(){

        $request_settings = array();
        $display_ad = array();

        $this->prepare_r_hash($request_settings);

        $param_rt = $this->request->get('rt');
        if (!isset($param_rt)){
            $param_rt='';
        }

        switch ($param_rt){
            case 'javascript':
                $request_settings['response_type']='json';
                $request_settings['ip_origin']='fetch';
                break;

            case 'json':
                $request_settings['response_type']='json';
                $request_settings['ip_origin']='fetch';
                break;

            case 'iphone_app':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='fetch';
                break;

            case 'android_app':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='fetch';
                break;

            case 'ios_app':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='fetch';
                break;

            case 'ipad_app':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='fetch';
                break;

            case 'xml':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='request';
                break;

            case 'api':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='request';
                break;

            case 'api-fetchip':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='fetch';
                break;

            default:
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='request';
                break;

        }
        $request_settings['referer'] = $this->request->get("p", null, '');
        $request_settings['device_name'] = $this->request->get("ds", null, '');
        $request_settings['device_movement'] = $this->request->get("dm", null, '');
        $request_settings['longitude'] = $this->request->get("longitude", null, '');
        $request_settings['latitude'] = $this->request->get("latitude", null, '');
        $request_settings['iphone_osversion'] = $this->request->get("iphone_osversion", null, '');
        $request_settings['i'] = $this->request->get("i", null, '');
        $request_settings['adv_type'] = $this->request->get("mt", null, null);
        $request_settings['screen'] = $this->request->get("screen", null, '');
        $request_settings['screen_size'] = Lov::getScreen($request_settings['screen']);

        $request_settings['pattern'] = $this->request->get("up",null,'');
        $request_settings['video_type'] = $this->request->get("vc",null,'');
        $request_settings['video_id'] = $this->request->get("v_id",null,null);
        $request_settings['zone_hash'] = $this->request->get('s');
        $request_settings['ip_address']=$this->request->getClientAddress(TRUE);


        $param_sdk = $this->request->get("sdk");


        if (MAD_MAINTENANCE){
            return $this->codeNoAds();
        }

        if($this->filterByBlock($request_settings['ip_address'],$request_settings['i'])){
            return $this->codeNoAds();
        }

        $zone = $this->getZoneByHash($request_settings['zone_hash']);
        if(!$zone){
            return $this->codeInputError();
        }
        $this->update_last_request($zone);
        $campaigns = $this->getCampaignsByZoneId($zone->entry_id);
        if($campaigns){
            $device = $this->getDevice($request_settings['device_name'],$request_settings['device_movement']);
            $location = $this->getCodeFromIp($request_settings['ip_address']);
            if($device){
                $campaigns = $this->filterByDevicePackage($campaigns,$device->device_quality);
            }
            if($location){
                $campaigns = $this->filterByLocation($campaigns,$location);
            }

            if($request_settings['video_id']){
                $video = $this->getContentByVideoId($request_settings['video_id']);
                if($video){
                    $campaigns = $this->filterByContent($campaigns,$video);
                }
            }
        }else{
            $this->updateRequsetLog(false,$zone,$display_ad,$request_settings);
        }
        $campaigns = $this->getCampaignFromTemp($campaigns);
        if($campaigns){
            $campaigns = $this->filterByFrequency($campaigns,$request_settings['i']);
        }else{
            $this->updateRequsetLog(false,$zone,$display_ad,$request_settings);
            return $this->codeNoAds();
        }
        //从符合条件的投放列表里面取出相关创意并按规定格式返回广告信息
        if($campaigns and count($campaigns)>0){
//            foreach($campaigns as $campaign){
//                $units = $this->getUnitByCampaign($campaign['id']);
//                //var_dump($units);
//            }
            $units = $this->getUnitByCampaign($campaigns[0]->campaign_id);
            if($units and count($units)>0){
                $adUnit= $units[0];
                $this->build_ad($display_ad,$zone,1,$adUnit);
                //$this->build_display_ad($zone,$adUnit,$display_ad);
                $display_ad['response_type'] = $request_settings['response_type'];
                $base_ctr="".MAD_ADSERVING_PROTOCOL . MAD_SERVER_HOST
                    ."/".MAD_TRACK_HANDLER_VD."?ad=".$display_ad['ad_hash']."&zone=".$display_ad['zone_hash']
                    ."&ds=".$request_settings['device_name']."&dm=".$request_settings['device_movement']."&i=".$request_settings['i'];

                $display_ad['final_impression_url']=$base_ctr;
                $display_ad['final_click_url']="".MAD_ADSERVING_PROTOCOL . MAD_SERVER_HOST
                    ."/".MAD_CLICK_HANDLER."?ad=".$display_ad['ad_hash']."&zone=".$display_ad['zone_hash']
                    ."&ds=".$request_settings['device_name']."&dm=".$request_settings['device_movement']."&i=".$request_settings['i'];
                $this->updateRequsetLog(true,$zone,$display_ad,$request_settings);
                return $display_ad;
            }

            //response
            //return $display_ad;
        }else{
            //no ad
            //save md_device_request_log
            $this->updateRequsetLog(false,$zone,$display_ad,$request_settings);
            return $this->codeNoAds();
        }
    }

    private function filterByBlock($ip,$mac_address){
        if($this->isMacAddressBlocked($mac_address) or $this->isIpBlocked($ip)) {
            return true;
        }else{
            return  false;
        }
    }

    private function getZoneByHash($zone_hash){

        $zone = VdZone::findFirst(array(
            "conditions" => "zone_hash=:zone_hash: ",
            "bind" => array("zone_hash" => $zone_hash)
        ));
        return $zone;
    }

    private function getContentByVideoId($video_id){

        $video->id = 1;
        $video->channel = 2;
        $video->subject = 3;
        $video->fromRegion = 4;
        $video->album = array(5,6);
        return $video;
    }


    private function getCampaignsByZoneId($zone_id){

        $cache_str = $this->getCacheAdData(CACHE_PREFIX."_TARGETING_ZONE_".$zone_id);
        if($cache_str){
            $data = json_decode($cache_str);
            //$campaigns = array();
            $campaigns = $data->targeting;
            return $campaigns;
        }else{
            return false;
        }

    }

    private  function filterByFrequency($campaigns,$mac_address){

        $campaigns_new = array();
        foreach($campaigns as $campaign){
            //$isFrequencyOk = getFrequencyCacheByMacAndCampaigns()
            //if($isFrequencyOk){
            $isFrequencyOk = $this->getCacheAdData(CACHE_PREFIX."_CLIENT_FREQUENCY_".$campaign->campaign_id.$mac_address);
            if(!$isFrequencyOk){
                $campaigns_new[] =  $campaign;
            }
        }
        return $campaigns;
    }

    private function filterByContent($campaigns,$video){

        //filterBy channel
        $campaigns = $this->filterByChannel($campaigns,$video);
        //filterBy subject
        $campaigns = $this->filterBySubject($campaigns,$video);
        //filterBy fromRegion
        $campaigns = $this->filterByFromRegion($campaigns,$video);
        //filterBy album
        $campaigns = $this->filterByAlbum($campaigns,$video);

        return $campaigns;
    }

    private function filterByChannel($campaigns,$video){
        $campaigns_new = array();
        foreach($campaigns as $campaign){
            $channel_video = $video->channel;
            $channel_campaign = $campaign->channel;
            if(empty($channel_campaign)){
                $campaigns_new[] = $campaign;
            }else{
                $channel_campaigns = explode(",",$channel_campaign);
                if(in_array($channel_video,$channel_campaigns)){
                    $campaigns_new[] = $campaign;
                }
            }
        }
        return $campaigns_new;
    }

    private function filterBySubject($campaigns,$video){
        $campaigns_new = array();
        foreach($campaigns as $campaign){
            $subject_video = $video->subject;
            $subject_campaign = $campaign->subject;
            if(empty($subject_campaign)){
                $campaigns_new[] = $campaign;
            }else{
                $subject_campaigns = explode(",",$subject_campaign);
                if(in_array($subject_video,$subject_campaigns)){
                    $campaigns_new[] = $campaign;
                }
            }
        }
        return $campaigns_new;
    }

    private function filterByFromRegion($campaigns,$video){
        $campaigns_new = array();
        foreach($campaigns as $campaign){
            $fromRegion_video = $video->fromRegion;
            $fromRegion_campaign = $campaign->fromRegion;
            if(empty($fromRegion_campaign)){
                $campaigns_new[] = $campaign;
            }else{
                $fromRegion_campaigns = explode(",",$fromRegion_campaign);
                if(in_array($fromRegion_video,$fromRegion_campaigns)){
                    $campaigns_new[] = $campaign;
                }
            }
        }
        return $campaigns_new;
    }

    private function filterByAlbum($campaigns,$video){
        $campaigns_new = array();
        foreach($campaigns as $campaign){
            $album_video = $video->album;
            $album_campaign = $campaign->album;
            if(empty($album_campaign)){
                $campaigns_new[] = $campaign;
            }else{
                $album_campaigns = explode(",",$album_campaign);
                foreach($album_video as $album){
                    if(in_array($album,$album_campaigns)){
                        $campaigns_new[] = $campaign;
                        break;
                    }
                }
            }
        }
        return $campaigns_new;
    }

    private function filterByDevicePackage($campaigns,$devicePackage){
        $campaigns_new = array();
        foreach($campaigns as $campaign){
            $devicePackage_target = $campaign->devicePackage;
            if(empty($devicePackage_target)){
                $campaigns_new[] =  $campaign;
            }else{
                $packages = explode(",",$devicePackage_target);
                if(in_array($devicePackage,$packages)){
                    $campaigns_new[] =  $campaign;
                }
            }
        }
        return $campaigns_new;
    }

    private function filterByLocation($campaigns,$location){
        $campaigns_new = array();
        foreach($campaigns as $campaign){
            $province_target = $campaign->province;
            $city_target = $campaign->city;
            if(empty($province_target) and empty($city_target)){
                $campaigns_new[] =  $campaign;
            }else{
                $provinces = explode(",",$province_target);
                $cities = explode(",",$city_target);
                if(in_array($location[0],$provinces) or in_array($location[1],$cities)){
                    $campaigns_new[] =  $campaign;
                }
            }
        }
        return $campaigns_new;
    }


    private function getCampaignFromTemp($campaigns){

        $conditions = "campaign_target = :campaign_target:";
        $ids = "";
        foreach($campaigns as $campaign){
            if($ids ===""){
                $ids =$campaign->campaign_id;
            }else{
                $ids .=','.$campaign->campaign_id;
            }
        }
        if($ids != ""){
            $conditions = $conditions." or campaign_id in($ids)";
        }
        $results = VdCampaignTemp::find(array(
            "conditions" => $conditions,
            "bind" => array("campaign_target" => 0),
            "order" => "campaign_priority,campaign_weights"
        ));
        return $results;
    }

    private function getUnitByCampaign($campaign_id){
        return VdUnit::find(array(
            "conditions" => "campaign_id=:campaign_id: ",
            "bind" => array("campaign_id" => $campaign_id)
        ));
    }

    private function updateRequsetLog($hasAd,$zone,$display_ad,$request_settings){
        //更新请求记录
        $time = time();
        if(empty($request_settings['device_name'])) {
            $result['device_name'] = $request_settings['device_movement'];
        }else{
            $result['device_name'] = $request_settings['device_name'];
        }

        $result['equipment_sn'] = '';
        $result['equipment_key'] = $request_settings['i'];
        $result['screen'] = $request_settings['screen'];
        $result['up'] = $request_settings['pattern'];

        $result['campaign_id'] = $display_ad['campaign_id'];
        $result['ad_id'] = $display_ad['ad_id'];
        $result['publication_id'] = $zone->publication_id;
        $result['zone_id'] = $zone->entry_id;
        if($hasAd){
            $result['available'] = 1;
        }else{
            $result['available'] = 0;
        }
        $this->save_request_log('request', $result, $time);
        if(DEBUG_LOG_ENABLE) {
            $this->di->get('logRequestProcess')->log("timestamp->".$time.", campaign_id->".$result['campaign_id'], Phalcon\Logger::DEBUG);
        }

        $this->track_request($time, $request_settings, $zone, $display_ad, 0);

    }
}