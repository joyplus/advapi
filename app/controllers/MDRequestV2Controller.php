<?php

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDRequestV2Controller extends MDRequestController{

    public function get(){
		$result = $this->handleAdRequest();
		return $result;
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
    	
    	$conditions .= " AND Campaigns.campaign_status=1 AND Campaigns.del_flg<>1 AND Campaigns.campaign_class<>2 AND Campaigns.campaign_start<=:campaign_start: AND Campaigns.campaign_end>=:campaign_end:";
    	$params['campaign_start'] = date("Y-m-d");
    	$params['campaign_end'] = date("Y-m-d");
    	 
    	if($zone_detail->zone_type!='open'){
    		//广告类型
    		if(!empty($request_settings['adv_type'])) {
    			$conditions .= " AND (Campaigns.campaign_type='network' OR (ad.adv_type=:adv_type: AND ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: and  ad.adv_status=1";
    			$params['adv_type'] = $request_settings['adv_type'];
    			$params['adv_start'] = date("Y-m-d");
    			$params['adv_end'] = date("Y-m-d");
    		}else{
    			$conditions .= " AND (Campaigns.campaign_type='network' OR (ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: and  ad.adv_status=1";
    			$params['adv_start'] = date("Y-m-d");
    			$params['adv_end'] = date("Y-m-d");
    		}
    	}
    	
    	//广告位类型
    	switch ($zone_detail->zone_type){
    		case 'banner':
    			$conditions .= " AND ad.creative_unit_type='banner' AND ad.adv_width=:adv_width: AND ad.adv_height=:adv_height:))";
    			$params['adv_width'] = $zone_detail->zone_width;
    			$params['adv_height'] = $zone_detail->zone_height;
    			break;
    	
    		case 'interstitial':
    			$conditions .= " AND ad.creative_unit_type='interstitial'";
    			//尺寸匹配
    			if($request_settings['screen_size']) {
    				$conditions .= " AND ad.adv_width=:adv_width: AND ad.adv_height=:adv_height:))";
    				$params['adv_width'] = $request_settings['screen_size'][0];
    				$params['adv_height'] = $request_settings['screen_size'][1];
    			}else{
    				$conditions .= "))";
    			}
    			break;
    		case 'mini_interstitial':
    			$conditions .= " AND ad.creative_unit_type='mini_interstitial' AND ad.adv_width=:adv_width: AND ad.adv_height=:adv_height:))";
    			$params['adv_width'] = $zone_detail->zone_width;
    			$params['adv_height'] = $zone_detail->zone_height;
    			 
    			break;
    		case 'open':
    			$conditions .= " AND (Campaigns.campaign_type='network' OR (ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: AND ad.adv_status=1 AND ad.creative_unit_type='open'";
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
    				$conditions .= " AND (ad.adv_width='' OR ad.adv_width=:adv_width:) AND (ad.adv_height='' OR ad.adv_height=:adv_height:)))";
    				$params['adv_width'] = $request_settings['screen_size'][0];
    				$params['adv_height'] = $request_settings['screen_size'][1];
    			}else{
    				$conditions .= "))";
    			}
    			break;
    		case 'previous':
    			$conditions .= " AND ad.creative_unit_type='previous'))";
    			break;
    		case 'middle'://同banner处理
    			$conditions .= " AND ad.creative_unit_type='banner' AND ad.adv_width=:adv_width: AND ad.adv_height=:adv_height:))";
    			$params['adv_width'] = $zone_detail->zone_width;
    			$params['adv_height'] = $zone_detail->zone_height;
    			break;
    		case 'after':
    			$conditions .= " AND ad.creative_unit_type='after'))";
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