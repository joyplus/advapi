<?php

class MDTopicGetController extends RESTController{

    public function get(){
    	$params['s'] = $this->request->get("s", null, '');
    	$this->log("[get] s->".$params['s']);
    	$topic = Topic::findFirst(array(
    		"hash=:s:",
    		"bind"=>$params,
    		"cache"=>array(
    			"key"=>CACHE_PREFIX."_TOPIC_HASH_".$params['s'],
    			"lifetime"=>MD_CACHE_TIME
    		)
    	));
    	if(!$topic) {
    		$result['code'] = "20001";
    		$this->outputJson("topic/items", $result);
    	}
    	$this->log("[get] topic id->".$topic->id);
    	$result['code'] = "00000";
    	$result['widget_pic_url'] = $topic->widget_pic_url;
    	$ad = $this->getAdunit($topic->zone_hash);
    	if($ad) {
    		$params = "rq=1&ad=".$ad->unit_hash."&zone=".$topic->zone_hash."&dm=%dm%&i=%mac%&ip=%ip%&ex=%ex%";
    		$result['creative_url'] = $ad->adv_creative_url;
    		$result['tracking_url'] = MAD_ADSERVING_PROTOCOL.MAD_SERVER_HOST."/".MAD_MONITOR_HANDLER."?".$params;;
    	}else{
    		$result['creative_url'] = $topic->background_url;
    	}

    	$items = TopicItems::find(array(
    		"topic_id = :topic_id:",
    		"bind"=>array("topic_id"=>$topic->id),
    		"cache"=>array(
    			"key"=>CACHE_PREFIX."_TOPIC_ITEMS_TOPICID_".$topic->id,
    			"lifetime"=>MD_CACHE_TIME
    		)
    	));
    	
    	foreach ($items as $item) {
    		$row = $this->arrayKeysToSnake($item->toArray());
    		$rows[] = $row;
    	}
    	if(count($rows)<1) {
    		$result['code'] = "20001";
    	}
    	$result['items'] = $rows;
    	$this->log("[get] results->".json_encode($result));
    	$this->outputJson("topic/items", $result);
    }
    
    private function getAdunit($zone_hash) {
    	$date = date("Y-m-d");
    	$zone = $this->get_placement($zone_hash);
    	if(!$zone) {
    		return false;
    	}
    	$this->log("[get] zone id->".$zone->entry_id);
    	$campaign = $this->findCampaign($zone->entry_id, $date);
    	if(!$campaign) {
    		return false;
    	}
    	$this->log("[get] campaign id->".$campaign->campaign_id);
    	$ads = $this->findAdUnit($campaign, $date);
    	if(!ads) {
    		return false;
    	}
    	$this->log("[get] count ads->".count($ads));
    	if($campaign->rule==1){ //创意随机排序
    		shuffle($ads);
    		$ad_id = $ads[0]['ad_id'];
    	}else{
    		$ad_id = $ads[0]['ad_id'];
    	}
    	$ad = AdUnits::findFirst($ad_id);
    	$this->log("[get] ad id->".$ad->adv_id);
    	return $ad;
    }
    
    private function findCampaign($zone_id, $date) {
    	$phql = "SELECT c.campaign_id AS campaign_id, c.creative_show_rule AS rule FROM Campaigns AS c 
    			LEFT JOIN CampaignTargeting AS t ON c.campaign_id=t.campaign_id
    			LEFT JOIN CampaignLimit AS ct ON c.campaign_id=ct.campaign_id 
    			LEFT JOIN AdUnits AS ad ON c.campaign_id=ad.campaign_id
    			WHERE (t.targeting_type='placement' AND t.targeting_code=:zone_id:)
    			AND c.campaign_status=1 AND c.campaign_start<=:campaign_start: 
    			AND c.campaign_end>=:campaign_end: AND (ad.adv_start<=:adv_start: 
    			AND ad.adv_end>=:adv_end: AND ad.adv_status=1 AND ad.adv_type='1')
    			AND ct.total_amount_left>=1 
    			ORDER BY c.campaign_priority";
    	$params['zone_id'] = $zone_id;
    	$params['campaign_start'] = $date;
    	$params['campaign_end'] = $date;
    	$params['adv_start'] = $date;
    	$params['adv_end'] = $date;
    	
    	$result = $this->modelsManager->executeQuery($phql, $params);
    	if(count($result)<1){
    		return false;
    	}
    	return $result[0];
    }
    
    private function findAdUnit($campaign, $date) {
    	$order = " adv_id";
    	//创意权重排序
    	if($campaign->rule==3){
    		$order = "creative_weight DESC";
    	}
    	$conditions = " campaign_id=:campaign_id: AND adv_type=1
    			AND adv_start<=:adv_start: AND adv_end>=:adv_end:
    			AND adv_status=1 AND del_flg<>1 ORDER BY ".$order;
    	$params['campaign_id'] = $campaign->campaign_id;
    	$params['adv_start'] = $date;
    	$params['adv_end'] = $date;
    	
    	$result = AdUnits::find(array(
    		$conditions,
    		"bind"=>$params,
    		"cache"=>array(
    			"key"=>CACHE_PREFIX."_ADUNITS_".$conditions.md5(serialize($params)),
    			"lifetime"=>MD_CACHE_TIME
    		)
    	));
    	$adarray = array();
    	foreach ($result as $item) {
    		$add = array('ad_id'=>$item->adv_id,
    				'width'=>$item->adv_width,
    				'height'=>$item->adv_height,
    				'weight'=>$item->creative_weight
    		);
    		$adarray[] = $add;
    	}
    	return count($adarray)<1?false:$adarray;
    }
    
    private function log($log) {
    	$this->debugLog("[TopicGetController]".$log);
    }
}