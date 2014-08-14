<?php
/**
 * Created by PhpStorm.
 * User: qibozhang
 * Date: 14-8-13
 * Time: 下午2:29
 */

class YZRequestController extends MDRequestV2Controller{

//    public function get(){
//        $result = $this->handleAdRequest();
//        return $result;
//    }

    public function post(){
        $result = $this->handleAdRequest();
        return $result;
    }

    protected function handleAdRequest(){
        $request_settings = array();
        $request_data = array();
        $display_ad = array();

        $request_data_xml = $this->request->getRawBody();

        //echo("abcdefg".$request_data);

        $request_data_client = simplexml_load_string($request_data_xml);
        if(!$request_data_client){
            return $this->codeNoAds();
        }
//        var_dump($request_data_client);
//        return exit;

        //$request_settings['referer'] = $this->request->get("p", null, '');
        //$request_settings['device_name'] = $this->request->get("ds", null, '');
        $request_settings['device_movement'] = "YZ";
        //$request_settings['longitude'] = $this->request->get("longitude", null, '');
        //$request_settings['latitude'] = $this->request->get("latitude", null, '');
        //$request_settings['iphone_osversion'] = $this->request->get("iphone_osversion", null, '');
        $request_settings['i'] = $request_data_client->parameters->chipId->__toString();
        $request_settings['placement_hash'] = $request_data_client->parameters->adtype->__toString();
        //$request_settings['adv_type'] = $this->request->get("mt", null, null);
        //$request_settings['screen'] = $this->request->get("screen", null, '');
        //$request_settings['screen_size'] = Lov::getScreen($request_settings['screen']);

        //$request_settings['pattern'] = $this->request->get("up",null,'');
        //$request_settings['video_type'] = $this->request->get("vc",null,'');
        $request_settings['ip_origin']='fetch';



        //echo($request_settings['placement_hash']);


        if (MAD_MAINTENANCE){
            return $this->codeNoAds();
        }

        if (!$this->check_input($request_settings, $errormessage)){
            //global $errormessage;
            //print_error(1, $errormessage, $request_settings['sdk'], 1);
            //TODO: Unchecked MD Functions
            echo($errormessage);
            var_dump($request_settings);
            return $this->codeInputError();
        }

        $this->handleImpression($request_data_client,$request_settings);

        if($this->isMacAddressBlocked($request_settings['i'])) {
            return $this->codeNoAds();
        }

        $request_data['ip']=$request_settings['ip_address'];
        if($this->isIpBlocked($request_data['ip'])) {
            return $this->codeNoAds();
        }

        $device_detail = $this->getDevice($request_settings['device_name'], $request_settings['device_movement']);
        if($device_detail) {
            $request_settings['device_type'] = $device_detail->device_type;
            $request_settings['device_brand'] = $device_detail->device_brands;
            $request_settings['device_quality']= $this->getDeviceQuality($device_detail->device_id);
            $this->debugLog("[handleAdRequest] found device,quality->".$request_settings['device_quality']);
        }

        $zone_detail=$this->get_placement($request_settings['placement_hash']);

        if (!$zone_detail){
            return $this->codeInputError();
        }

        $this->debugLog("[handleAdRequest] found zone, id->".$zone_detail->entry_id);

        $request_settings['adspace_width']=$zone_detail->zone_width;
        $request_settings['adspace_height']=$zone_detail->zone_height;

        //$request_settings['channel']=$this->getchannel($zone_detail);

        $this->update_last_request($zone_detail);

        $this->setGeo($request_settings);

        //处理试投放
        $this->debugLog("[handleAdRequest] i->".$request_settings['i']);
        $cacheKey = CACHE_PREFIX.'UNIT_DEVICE'.$request_settings['i'].$request_settings['placement_hash'];
        $this->debugLog("[handleAdRequest] cacheKey->".$cacheKey);
        $adv_id = $this->getCacheAdData($cacheKey);
        if($adv_id){
            $this->debugLog("[handleAdRequest] 找到试投放,key->".$cacheKey.", id->".$adv_id);
            if (!$final_ad = $this->get_ad_unit($adv_id)){
                return $this->codeNoAds();
            }
            if (!$this->build_ad($display_ad, $zone_detail, 1, $final_ad)){
                return $this->codeNoAds();
            }
            $request_settings['active_campaign_type'] = 'normal';
        }else{
            $this->buildQuery($request_settings, $zone_detail);

            if ($campaign_query_result=$this->launch_campaign_query($request_settings, $request_settings['campaign_conditions'], $request_settings['campaign_params'])){

                $this->process_campaignquery_result($zone_detail, $request_settings, $display_ad, $campaign_query_result);

                //TODO: Unchecked MD functions
                //            if (!$this->process_campaignquery_result($campaign_query_result, $zone_detail, $request_settings)){
                //
                //                launch_backfill();
                //            }
            }
            else {
                //TODO: Unchecked MD functions
                //launch_backfill();
            }
        }
        $time = time();
        if (isset($display_ad['available']) && $display_ad['available']==1){
            $this->track_request($time, $request_settings, $zone_detail, $display_ad, 0);
            //display_ad();
            $this->prepare_ad($display_ad, $request_settings, $zone_detail);
            $display_ad['response_type'] = $request_settings['response_type'];
            $base_ctr="".MAD_ADSERVING_PROTOCOL . MAD_SERVER_HOST
                ."/".MAD_TRACK_HANDLER."?ad=".$display_ad['ad_hash']."&zone=".$display_ad['zone_hash']."&ds=".$request_settings['device_name']."&dm=".$request_settings['device_movement']."&i=".$request_settings['i'];

            $display_ad['final_impression_url']=$base_ctr;
            $display_ad['final_click_url']="".MAD_ADSERVING_PROTOCOL . MAD_SERVER_HOST
                ."/".MAD_CLICK_HANDLER."?ad=".$display_ad['ad_hash']."&zone=".$display_ad['zone_hash']."&ds=".$request_settings['device_name']."&dm=".$request_settings['device_movement']."&i=".$request_settings['i'];
            $this->completionParams($request_settings,$display_ad);
        }
        else {
            $this->track_request($time, $request_settings, $zone_detail, $display_ad, 0);
            //$display_ad['return_code'] = "20001";
            return $this->codeNoAds();
        }


        /////记录device_log
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
        $result['publication_id'] = $zone_detail->publication_id;
        $result['zone_id'] = $zone_detail->entry_id;
        $result['available'] = $display_ad['available'];

        $this->save_request_log('request', $result, $time);
        if(DEBUG_LOG_ENABLE) {
            $this->di->get('logRequestProcess')->log("timestamp->".$time.", campaign_id->".$result['campaign_id'], Phalcon\Logger::DEBUG);
        }
        $display_ad['return_type']='yzxml';
        return $display_ad;
    }

    function handleImpression($request_data , $request_settings){
        $records = $request_data->parameters->records->record;
        if($records){
            foreach($records as $record){
                $data = explode("|",$record->__toString());
                if(count($data)>=3){
                    $adv_hash = $data[0];
                    $impression = $data[1];
                    $zone_hash = $data[2];
                    for($i=0; $i<$impression; $i++){
                        $this->saveImpression($adv_hash,$zone_hash,$request_settings);
                    }
                }
            }
        }

    }

    function saveImpression($ad_hash,$zone_hash,$request_settings){
        $ad = AdUnits::findFirst(array(
            "unit_hash = '".$ad_hash."'",
            "cache"=>array("key"=>CACHE_PREFIX."_ADUNIT_HASH_".$ad_hash,"lifetime"=>MD_CACHE_TIME)
        ));
        if(!$ad) {
            return $this->codeInputError();
        }

        $zone = $this->get_placement($zone_hash);
        if(!$zone) {
            return $this->codeInputError();
        }

        $left = $this->deduct_impression_num($ad->campaign_id, 1);

        $current_time = time();
        $current_date = date('Y-m-d H:i:s', $current_time);

        $reporting['ip'] = $request_settings['ip'];
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

        $queue = $this->getDi()->get('beanstalkReporting');
        $queue->put(serialize($reporting));
        if(DEBUG_LOG_ENABLE) {
            $this->di->get('logTrackReporting')->log("timestamp->$current_time, campaign_id->".$reporting['campaign_id'], Phalcon\Logger::DEBUG);
        }


        $reporting['equipment_key'] = $request_settings['i'];

        $reporting['device_name'] = "YZ";

        $this->save_request_log('track', $reporting, $current_time);
        if(DEBUG_LOG_ENABLE) {
            $this->di->get('logTrackProcess')->log("timestamp->$current_time, campaign_id->".$reporting['campaign_id'], Phalcon\Logger::DEBUG);
        }
    }

} 