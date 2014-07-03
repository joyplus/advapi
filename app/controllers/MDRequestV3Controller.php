<?php

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDRequestV3Controller extends MDRequestController{

    public function get(){

        $request_settings = array();
        $display_ad = array();
        $errormessage = '';

        $this->prepare_r_hash($request_settings);

        $param_rt = $this->request->get('rt');
        if (!isset($param_rt)){
            $param_rt='';
        }

        $request_settings['referer'] = $this->request->get("p", null, '');
        $request_settings['device_name'] = $this->request->get("ds", null, '');
        $request_settings['device_movement'] = $this->request->get("dm", null, '');
        $request_settings['longitude'] = $this->request->get("longitude", null, '');
        $request_settings['latitude'] = $this->request->get("latitude", null, '');
        $request_settings['iphone_osversion'] = $this->request->get("iphone_osversion", null, '');
        $request_settings['md5_mac_address'] = $this->request->get("i", null, '');
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

        if($this->filterByBlock($request_settings['ip_address'],$request_settings['md5_mac_address'])){
            return $this->codeNoAds();
        }

        $zone = $this->getZoneByHash($request_settings['zone_hash']);
        if(!$zone){
            return $this->codeInputError();
        }
        $campaigns = $this->getCampaignsByZoneId($zone->id);
        //echo(count($campaigns));
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
        }
        $campaigns = $this->getCampaignFromTemp($campaigns);
        if($campaigns){
            $campaigns = $this->filterByFrequency($campaigns,$request_settings['md5_mac_address']);
        }


        //var_dump($campaigns);


        //从符合条件的投放列表里面取出相关创意并按规定格式返回广告信息
        if(count($campaigns)>0){

            foreach($campaigns as $campaign){
                $units = $this->getUnitByCampaign($campaign->campaign_id);

            }
            //response
        }else{
            //no ad
            //save md_device_request_log

            return $this->codeNoAds();
        }

        exit;
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

//        $a = array();
//        $b->campaignId = 2;
//        $b->province = "CN_09";
//        $b->city = "CN_09";
//        $b->channel = 2;
//        $b->subject = 3;
//        $b->fromRegion = 4;
//        $b->album = "5,6,7";
//        $b->devicePackage = 5;
//        $a[]=$b;
//        $obj->targeting = $a;
//
//        $cache_str = json_encode($obj);
//        //$cache_str = getCacheDataByZoneID();


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
            if(false){
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

        //addCampaignWithoutTarget();
        //getCampaignDetailFromTemp();
        $sql = "select * from vd_campaign_temp where campaign_target = 0";
        if(count($campaigns)>0){
            $ids = "";
            foreach($campaigns as $campaign){
                if($ids ===""){
                    $ids =$campaign->campaign_id;
                }else{
                    $ids .=','.$campaign->campaign_id;
                }
            }
            $sql = $sql." or id in($ids)";
        }
        $sql = $sql." order by campaign_priority,campaign_weights";
//        $sql = "select * from vd_campaign where id in($ids) or campaign_target = 0 order by campaign_weights,campaign_priority";
       // echo($sql);
        $db = $this->di->get("dbMaster");
//        //查询
        $results_temp = $db->query($sql);
        $results = $results_temp->fetchAll();
        return $results;
    }


    private function sortCampaigns($campaigns){

    }

    private function getUnitByCampaign($campain_id){

    }
}