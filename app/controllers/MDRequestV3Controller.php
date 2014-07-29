<?php

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDRequestV3Controller extends MDRequestController{

    public function get(){

        $request_settings = array();
        $display_ad = array();

        $this->prepare_r_hash($request_settings);

        $request_settings['response_type']='xml';
        $request_settings['ip_origin']='request';
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


//        $param_sdk = $this->request->get("sdk");


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
            return $this->codeNoAds();
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

            $hasUnit = $this->select_ad_unit($display_ad,$zone,$request_settings,$campaigns[0]);
            if($hasUnit){
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
            }else{
                $this->updateRequsetLog(false,$zone,$display_ad,$request_settings);
                return $this->codeNoAds();
            }
//            $units = $this->getUnitByCampaign($campaigns[0]->campaign_id);
//            if($units and count($units)>0){
//                $adUnit= $units[0];
//                $this->build_ad($display_ad,$zone,1,$adUnit);
//                $display_ad['response_type'] = $request_settings['response_type'];
//                $base_ctr="".MAD_ADSERVING_PROTOCOL . MAD_SERVER_HOST
//                    ."/".MAD_TRACK_HANDLER_VD."?ad=".$display_ad['ad_hash']."&zone=".$display_ad['zone_hash']
//                    ."&ds=".$request_settings['device_name']."&dm=".$request_settings['device_movement']."&i=".$request_settings['i'];
//
//                $display_ad['final_impression_url']=$base_ctr;
//                $display_ad['final_click_url']="".MAD_ADSERVING_PROTOCOL . MAD_SERVER_HOST
//                    ."/".MAD_CLICK_HANDLER."?ad=".$display_ad['ad_hash']."&zone=".$display_ad['zone_hash']
//                    ."&ds=".$request_settings['device_name']."&dm=".$request_settings['device_movement']."&i=".$request_settings['i'];
//                $this->updateRequsetLog(true,$zone,$display_ad,$request_settings);
//                return $display_ad;
//            }
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

    private function getUnitById($unit_id){
        return VdUnit::findFirst(array(
            "conditions" => "adv_id=1",
            "bind" => array("a" => $unit_id)
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

    protected function select_adunit_query($request_settings, $zone_detail, $campaign_detail){
        $this->debugLog("[select_adunit_query] campaign_detail, id->".$campaign_detail->campaign_id);
        $params = array();
        $conditions = "campaign_id = :campaign_id:";
        $params['campaign_id'] = $campaign_detail->campaign_id;

        $conditions .= " AND adv_start<= :adv_start:";
        $params['adv_start'] = date("Y-m-d");

        $conditions .= " AND adv_end>= :adv_end:";
        $params['adv_end'] = date("Y-m-d");

        $conditions .= " AND adv_status = 1 AND del_flg<>1";

        $zone_type = $zone_detail->zone_type;
        //暂停同banner处理
        if($zone_type=='middle'){
            $zone_type = 'banner';
        }

        $conditions .= " AND creative_unit_type = :creative_unit_type:";
        $params['creative_unit_type'] = $zone_type;
        switch ($zone_type){
            case 'banner':
            case 'middle':
            case 'mini_interstitial' :
                $conditions .= " AND adv_width = :adv_width: AND adv_height= :adv_height:";
                $params['adv_width'] = $zone_detail->zone_width;
                $params['adv_height'] = $zone_detail->zone_height;
                break;
            case 'open':
                if($request_settings['screen_size']) {
                    $conditions .= " AND (adv_width='' OR adv_width = :adv_width:) AND (adv_height='' OR adv_height= :adv_height:)";
                    $params['adv_width'] = $request_settings['screen_size'][0];
                    $params['adv_height'] = $request_settings['screen_size'][1];
                }
                break;
            case 'interstitial':
                if($request_settings['screen_size']) {
                    $conditions .= " AND adv_width = :adv_width: AND adv_height= :adv_height:";
                    $params['adv_width'] = $request_settings['screen_size'][0];
                    $params['adv_height'] = $request_settings['screen_size'][1];
                }
                break;
        }
        //创意顺序排序
        $order = "adv_id";
        if($campaign_detail->creative_show_rule==3){ //创意权重排序
            $order = "creative_weight DESC";
        }
        $query_param = array(
            "conditions" => $conditions,
            "bind" => $params,
            "order"=>$order,
            "cache"=>array("key"=>CACHE_PREFIX."_VDUNITS_".md5(serialize($params)), "lifetime"=>MD_CACHE_TIME)
        );

        //global $repdb_connected,$display_ad;
        $adUnits = VdUnit::find($query_param);

        //$query="SELECT adv_id, adv_height, adv_width FROM md_ad_units WHERE campaign_id='".$campaign_id."' and adv_start<='".date("Y-m-d")."' AND adv_end>='".date("Y-m-d")."' AND adv_status=1 ".$query_part['size']." ORDER BY adv_width DESC, adv_height DESC";


        $adarray = array();

        foreach ($adUnits as $item) {
            $add = array('ad_id'=>$item->adv_id,
                'width'=>$item->adv_width,
                'height'=>$item->adv_height,
                'weight'=>$item->creative_weight
            );
            $adarray[] = $add;
        }

        if ($total_ads_inarray=count($adarray)<1){
            return false;
        }
        $this->debugLog("[select_adunit_query] found ad_units, num->".count($adarray));
        return $adarray;

    }

    protected function select_ad_unit(&$display_ad, $zone_detail, &$request_settings, $campaign_detail){

        if (!$ad_unit_array = $this->select_adunit_query($request_settings, $zone_detail, $campaign_detail)){
            return false;
        }

        if($campaign_detail->creative_show_rule==1){ //创意随机排序
            shuffle($ad_unit_array);
            $ad_id = $ad_unit_array[0]['ad_id'];
        }else{
            $ad_id = $ad_unit_array[0]['ad_id'];
        }

        if (!$final_ad = $this->get_ad_unit($ad_id)){
            return false;
        }

        if ($this->build_ad($display_ad, $zone_detail, 1, $final_ad)){
            return true;
        }

        return false;
    }

    protected function get_ad_unit($id){

        $ad_detail = VdUnit::findFirst(array(
            "adv_id = '".$id."'",
            "cache"=>array("key"=>CACHE_PREFIX."_ADUNITS_".$id, "lifetime"=>MD_CACHE_TIME)
        ));
        if (!$ad_detail){
            return false;
        }

        $this->debugLog("[get_ad_unit] found ad_unit, id->".$id);
        if (is_null($ad_detail->adv_creative_extension) || $ad_detail->adv_creative_extension==''){
            $bannerUrl=$ad_detail->adv_bannerurl;
            if(is_null($bannerUrl)){
                $bannerUrl='';
            }
            $tempArray= explode(".", $bannerUrl);
            $ad_detail->adv_creative_extension=$tempArray[count($tempArray)-1];
        }
        return $ad_detail;
    }
}