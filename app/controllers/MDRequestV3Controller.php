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


        var_dump($campaigns);

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
            return false;
        }else{
            return  true;
        }
    }

    private function getZoneByHash($zone_hash){

        $zone = Zones::findFirst(array(
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


        $cache_str = $this->getCacheDataValue(CACHE_PREFIX."_TARGETING_ZONE_".$zone_id);
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
        $sql = "select * from vd_campaign where campaign_target = 0";
        if(count($campaigns)>0){
            foreach($campaigns as $campaign){
                $ids.=$campaign->campaignId;
            }
            $sql = $sql." or id in($ids)";
        }
        $sql = $sql." order by campaign_priority,campaign_weights";
//        $sql = "select * from vd_campaign where id in($ids) or campaign_target = 0 order by campaign_weights,campaign_priority";
        echo($sql);
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


    public function buildQuery(&$request_settings, $zone_detail) {
    	$params = array();
    	
    	$provinceTarget = $this->existTargeting("geo", $request_settings['province_code']);
    	$cityTarget = $this->existTargeting("geo", $request_settings['city_code']);
    	if($geoTarget || $cityTarget) {
    		$request_settings['left_geo'] = true;
	    	$conditions = ' (Campaigns.country_target=1';
	    	if (isset($request_settings['province_code']) && !empty($request_settings['province_code']) && isset($request_settings['city_code']) && !empty($request_settings['city_code'])){
	    		$conditions .= " OR (c1.targeting_type='geo' AND (c1.targeting_code=:province_code: OR c1.targeting_code=:city_code:)))";
	    		$params['province_code'] = $request_settings['province_code'];
	    		$params['city_code'] = $request_settings['city_code'];
	    	}
	    	else if (isset($request_settings['province_code']) && !empty($request_settings['province_code'])){
	    		$conditions .= " OR (c1.targeting_type='geo' AND c1.targeting_code=:province_code:))";
	    		$params['province_code'] = $request_settings['province_code'];
	    	}
	    	else {
	    		$conditions .= ')';
	    	}
    	}else{
    		$request_settings['left_geo'] = false;
    		$conditions .= "(Campaigns.country_target=1)";
    	}
    	 
    	 
    	if(isset($request_settings['video_type']) && is_numeric($request_settings['video_type']) && ($zone_detail->zone_type=='previous' || $zone_detail->zone_type=='middle' || $zone_detail->zone_type=='after')) {
    		$conditions .= " AND (Campaigns.video_target=1 OR (c2.targeting_type='video' AND c2.targeting_code=:video_type:))";
    		$params['video_type'] = $request_settings['video_type'];
    		$request_settings['left_video'] = true;
    	}else{
    		$request_settings['left_video'] = false;
    	}
    	
    	$publicationTarget = $this->existTargeting("placement", $zone_detail->entry_id);
    	if($publicationTarget) {
    		$request_settings['left_publication'] = true;
    		$conditions .= " AND (Campaigns.publication_target=1 OR (c3.targeting_type='placement' AND c3.targeting_code=:entry_id:))";
    		$params['entry_id'] = $zone_detail->entry_id;
    	}else{
    		$request_settings['left_publication'] = false;
    		$conditions .= " AND (Campaigns.publication_target=1)";
    	}
    	
    	
    	$qualityTarget = $this->existTargetingQuality($request_settings['device_quality']);
    	if($qualityTarget) {
	    	if(is_array($request_settings['device_quality'])) {
	    		$request_settings['left_quality'] = true;
	    		$conditions .= " AND (Campaigns.quality_target=1 OR (c7.targeting_type='quality' AND c7.targeting_code IN (".implode(",",$request_settings['device_quality']).")))";
	    	}else{
	    		$conditions .= " AND (Campaigns.quality_target=1)";
	    		$request_settings['left_quality'] = false;
	    	}
    	}else{
    		$conditions .= " AND (Campaigns.quality_target=1)";
    		$request_settings['left_quality'] = false;
    	}
    	
    	$conditions .= " AND Campaigns.campaign_status=1 AND Campaigns.campaign_class<>2 AND Campaigns.campaign_start<=:campaign_start: AND Campaigns.campaign_end>=:campaign_end:";
    	if(!MAD_USE_CAMPAIGN_TMP) {
    		$conditions .= " AND Campaigns.del_flg<>1" ;
    	}
    	
    	$params['campaign_start'] = date("Y-m-d");
    	$params['campaign_end'] = date("Y-m-d");
    	 
    	if($zone_detail->zone_type!='open'){
    		//广告类型
    		if(!empty($request_settings['adv_type'])) {
    			$conditions .= " AND (ad.adv_type=:adv_type: AND ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: and  ad.adv_status=1";
    			$params['adv_type'] = $request_settings['adv_type'];
    			$params['adv_start'] = date("Y-m-d");
    			$params['adv_end'] = date("Y-m-d");
    		}else{
    			$conditions .= " AND (ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: and  ad.adv_status=1";
    			$params['adv_start'] = date("Y-m-d");
    			$params['adv_end'] = date("Y-m-d");
    		}
    	}
    	
    	//广告位类型
    	switch ($zone_detail->zone_type){
    		case 'banner':
    			$conditions .= " AND ad.creative_unit_type='banner' AND ad.adv_width=:adv_width: AND ad.adv_height=:adv_height:)";
    			$params['adv_width'] = $zone_detail->zone_width;
    			$params['adv_height'] = $zone_detail->zone_height;
    			break;
    	
    		case 'interstitial':
    			$conditions .= " AND ad.creative_unit_type='interstitial'";
    			//尺寸匹配
    			if($request_settings['screen_size']) {
    				$conditions .= " AND ad.adv_width=:adv_width: AND ad.adv_height=:adv_height:)";
    				$params['adv_width'] = $request_settings['screen_size'][0];
    				$params['adv_height'] = $request_settings['screen_size'][1];
    			}else{
    				$conditions .= ")";
    			}
    			break;
    		case 'mini_interstitial':
    			$conditions .= " AND ad.creative_unit_type='mini_interstitial' AND ad.adv_width=:adv_width: AND ad.adv_height=:adv_height:)";
    			$params['adv_width'] = $zone_detail->zone_width;
    			$params['adv_height'] = $zone_detail->zone_height;
    			 
    			break;
    		case 'open':
    			$conditions .= " AND (ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: AND ad.adv_status=1 AND ad.creative_unit_type='open'";
    			$params['adv_start'] = date("Y-m-d");
    			$params['adv_end'] = date("Y-m-d");
    			switch($request_settings['adv_type']) {
    				case 1:
    					$conditions .= " AND ad.adv_type = 1";
    					break;
    				case 3:
    					$conditions .= " AND ad.adv_type = 3";
    					break;
    				case 2: //视频
    					$conditions .= " AND (ad.adv_type = 2 OR ad.adv_type = 5)";
    					break;
    				case 4: //zip包
    					$conditions .= " AND (ad.adv_type = 4 OR ad.adv_type = 5)";
    					break;
    				case 5: //视频及zip包
    					$conditions .= " AND ad.adv_type = 5";
    					break;
    				default: //默认
    					$conditions .= " AND (ad.adv_type =2 OR ad.adv_type = 4 OR ad.adv_type = 5)";
    					break;
    			}
    			//尺寸匹配
    			if($request_settings['screen_size']) {
    				$conditions .= " AND (ad.adv_width='' OR ad.adv_width=:adv_width:) AND (ad.adv_height='' OR ad.adv_height=:adv_height:))";
    				$params['adv_width'] = $request_settings['screen_size'][0];
    				$params['adv_height'] = $request_settings['screen_size'][1];
    			}else{
    				$conditions .= ")";
    			}
    			break;
    		case 'previous':
    			$conditions .= " AND ad.creative_unit_type='previous')";
    			break;
    		case 'middle'://同banner处理
    			$conditions .= " AND ad.creative_unit_type='banner' AND ad.adv_width=:adv_width: AND ad.adv_height=:adv_height:)";
    			$params['adv_width'] = $zone_detail->zone_width;
    			$params['adv_height'] = $zone_detail->zone_height;
    			break;
    		case 'after':
    			$conditions .= " AND ad.creative_unit_type='after')";
    			break;
    	}
    	
    	if (MAD_IGNORE_DAILYLIMIT_NOCRON && !$this->check_cron_active()){
    		$conditions .= " AND ((c_limit.total_amount_left>=1) OR (c_limit.cap_type=1))";
    	}else{
    		$conditions .= " AND (c_limit.total_amount_left>=1)";
    	}
    	 
    	//时段定向
    	$current_hours=pow(2,date('H'));
    	$conditions .= " AND (Campaigns.time_target=0 OR (Campaigns.time_target & :h1:)=:h2:)";
    	$params['h1'] = $current_hours;
    	$params['h2'] = $current_hours;
    	 
    	$request_settings['campaign_conditions'] = $conditions;
    	$request_settings['campaign_params'] = $params ;
    	 
    	$this->debugLog("[v2][build query->]".$conditions);
    }
    
    
    public function launch_campaign_query($request_settings, $conditions, $params){
    
    	$resultData = $this->getCacheDataValue(CACHE_PREFIX."_CAMPAIGNS_".md5(serialize($params)));
    	if($resultData){
    		return $resultData;
    	}
    	
    	if(MAD_USE_CAMPAIGN_TMP) {
    		$campaign_table = "CampaignsTmp";
    	}else{
    		$campaign_table = "Campaigns";
    	}
    	
    	$sql = "SELECT Campaigns.campaign_id AS campaign_id,
    			Campaigns.creative_show_rule AS creative_show_rule,
    			Campaigns.campaign_priority AS campaign_priority,
    			Campaigns.campaign_type AS campaign_type FROM $campaign_table AS Campaigns";
    	if($request_settings['left_geo']) {
    		$sql .= " LEFT JOIN CampaignTargeting AS c1 ON Campaigns.campaign_id=c1.campaign_id";
    	}
    	if($request_settings['left_video']) {
    		$sql .= " LEFT JOIN CampaignTargeting AS c2 ON Campaigns.campaign_id=c2.campaign_id";
    	}
    	if($request_settings['left_publication']) {
    		$sql .= " LEFT JOIN CampaignTargeting AS c3 ON Campaigns.campaign_id=c3.campaign_id";
    	}
    	if($request_settings['left_quality']) {
    		$sql .= " LEFT JOIN CampaignTargeting AS c7 ON Campaigns.campaign_id=c7.campaign_id";
    	}
    	$sql .= " LEFT JOIN CampaignLimit AS c_limit ON Campaigns.campaign_id = c_limit.campaign_id";
    	$sql .= " LEFT JOIN AdUnits AS ad ON Campaigns.campaign_id = ad.campaign_id";
    	$sql .= " WHERE " . $conditions;
    	
    	$campaignarray = array();
    	$result = $this->modelsManager->executeQuery($sql, $params);
    
    	foreach ($result as $item) {
    		$add = array(
    				'creative_show_rule'=>$item->creative_show_rule,
    				'campaign_id'=>$item->campaign_id,
    				'priority'=>$item->campaign_priority,
    				'type'=>$item->campaign_type
    		);
    		array_push($campaignarray, $add);
    	}
    
    	if (count($campaignarray)<1){
    		return false;
    	}
    	$this->debugLog("[v2][launch_campaign_query] found campaigns, num->".count($campaignarray));
    	foreach ($campaignarray as $key => $row) {
    		$campaign_id[$key]  = $row['campaign_id'];
    		$priority[$key] = $row['priority'];
    		$type[$key] = $row['type'];
    		$creative_show_rule[$key] = $row['creative_show_rule'];
    	}
    
    	array_multisort($priority, SORT_DESC, $campaignarray);
    
    	$highest_priority=$campaignarray[0]['priority'];
    
    	$final_ads=array();
    
    	foreach (range($highest_priority, 1) as $number) {
    		unset($val);
    		$val = $this->removeElementWithValue($campaignarray, "priority", $number);
    		shuffle($val);
    		foreach ($val as $value) {
    			array_push($final_ads, $value);
    		}
    	}
    	$this->saveCacheDataValue(CACHE_PREFIX."_CAMPAIGNS_".md5(serialize($params)), $final_ads);
    	return $final_ads;
    }
    
    /**
     * 是否存在定向条件
     * @param unknown $type
     * @param unknown $code
     * @return boolean
     */
    private function existTargeting($type, $code) {
    	$data = $this->getCacheAdData(CACHE_PREFIX."_TARGETING_OBJECT_".$type.$code); 	
    	return $data?true:false;
    }
    
    private function existTargetingQuality($rows) {
    	if(!is_array($rows)) {
    		return false;
    	}
    	foreach ($rows as $row) {
    		if($this->existTargeting("quality", $row)){
    			return true;
    		}
    	}
    	
    	return false;
    }
      
}