<?php

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDNetworkBatchController extends RESTController{

    public function get(){
    	$results = array();
    	$results['return_type'] = 'json';
    	
    	$hash = $this->request->get("s", null, '');
    	$rq = $this->request->get("rq", null, 0);
    	if($rq!=1){
    		$results['return_code'] = "30001";
    		$results['data']['status'] = "error";
    		return $results;
    	}
    	$this->log("[get] get hash->".$hash);
    	$zone_detail=$this->get_placement($hash);
    	$this->log("[get] get zone id->".$zone_detail->entry_id);
    	if(!$zone_detail) {
    		$results['return_code'] = "30001";
    		$results['data']['status'] = "error";
    		return $results;
    	}
    	$ads = $this->process($zone_detail);
    	$results['data'] = $ads;
    	if(count($ads)<1) {
    		$results['return_code'] = "20001";
    	}else{
    		$results['return_code'] = "00000";
    	}
    	return $results;
    }
    
    public function process($zone) {
    	$date = date("Y-m-d",strtotime ("+1 day"));
    	$ads = array();
    	$campaigns = $this->findCampaigns($zone, $date);
    	$this->log("[process] find campaigns num->".count($campaigns));
    	foreach ($campaigns as $c) {
    		$as = $this->findAds($c, $zone, $date);
    		$ads = $ads + $as;
    	}
    	return $ads;
    }
    
    public function buildCampaignSql($zone, $date) {
    	$conditions .= "(c1.targeting_type='placement' AND c1.targeting_code=:entry_id:)";
    	$params['entry_id'] = $zone->entry_id;
    	
    	$conditions .= " AND Campaigns.campaign_status=1 AND Campaigns.campaign_start<=:campaign_start: AND Campaigns.campaign_end>=:campaign_end:";
    	$params['campaign_start'] = $date;
    	$params['campaign_end'] = $date;
    	
    	$conditions .= " AND (ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: and  ad.adv_status=1 and ad.adv_type='2')";
    	$params['adv_start'] = $date;
    	$params['adv_end'] = $date;
    	
    	//$conditions .= " AND (c_limit.total_amount_left='' OR c_limit.total_amount_left>=1)";
    	
    	return array("conditions"=>$conditions, "params"=>$params);
    }
    
    public function findCampaigns($zone, $date) {
    	$sets = $this->buildCampaignSql($zone, $date);
    	$result = $this->modelsManager->createBuilder()
    	->from('Campaigns')
    	->leftjoin('CampaignTargeting', 'Campaigns.campaign_id = c1.campaign_id', 'c1')
    	->leftjoin('CampaignLimit', 'Campaigns.campaign_id = c_limit.campaign_id', 'c_limit')
    	->leftjoin('AdUnits', 'Campaigns.campaign_id = ad.campaign_id', 'ad')
    	->where($sets['conditions'], $sets['params'])
    	->groupBy(array('Campaigns.campaign_id'))
    	->getQuery()
    	->execute();
    	return $result;
    }
    
    public function findAds($c, $zone, $date) {
    	$conditions = "campaign_id = :campaign_id:";
		$params['campaign_id'] = $c->campaign_id;
    	
    	$conditions .= " AND adv_start<= :adv_start:";
    	$params['adv_start'] = $date;
    	
    	$conditions .= " AND adv_end>= :adv_end:";
    	$params['adv_end'] = $date;
    	
    	$conditions .= " AND adv_status = 1 AND adv_type=2";
    	
    	//创意权重排序
    	$order = "creative_weight DESC";
    	
    	$query_param = array(
    			"conditions" => $conditions,
    			"bind" => $params,
    			"order"=>$order,
    			"cache"=>array("key"=>CACHE_PREFIX."_ADUNITS_".md5(serialize($params)), "lifetime"=>MD_CACHE_TIME)
    	);
    	
    	$adUnits = AdUnits::find($query_param);
    	$this->log("[findAds] campaign id->".$c->campaign_id);
    	$this->log("[findAds] adUnits num->".count($adUnits));
    	$ads = $this->processAds($adUnits, $c, $zone, $date);
    	
    	return $ads;
    }
    
    /**
     * 生成ad的json数据
     * @param $adUnits
     * @param $c
     * @param $zone
     * @return array
     */
    public function processAds($adUnits, $c, $zone, $date) {
    	if(count($adUnits) < 1)
    		return array();
    	$extra = $this->adExtra($c);
    	foreach ($adUnits as $a) {
    		$ad = $extra;
    		$ad['adv_url'] = $a->adv_creative_url;
    		$ad['adv_hash'] = $a->unit_hash;
    		$ad['adv_name'] = $a->adv_name;
    		$ad['adv_weight'] = $a->creative_weight;
    		$ad['adv_creative_time'] = $a->creative_time;
    		$ad['adv_date'] = $date;
    		$limit = CampaignLimit::findByCampaignId($c->campaign_id);
    		if($limit){
    			$ad['daliy_amount'] = $limit->total_amount;
    		}
    		if(isset($a->adv_impression_tracking_url) && !empty($a->adv_impression_tracking_url))
    			$ad['adv_impression_tracking_url_miaozhen'] = $a->adv_impression_tracking_url;
    		if(isset($a->adv_impression_tracking_url_iresearch) && !empty($a->adv_impression_tracking_url_iresearch))
    			$ad['adv_impression_tracking_url_iresearch'] = $a->adv_impression_tracking_url_iresearch;
    		if(isset($a->adv_impression_tracking_url_admaster) && !empty($a->adv_impression_tracking_url_admaster))
    			$ad['adv_impression_tracking_url_admaster'] = $a->adv_impression_tracking_url_admaster;
    		if(isset($a->adv_impression_tracking_url_nielsen) && !empty($a->adv_impression_tracking_url_nielsen))
    			$ad['adv_impression_tracking_url_nielsen'] = $a->adv_impression_tracking_url_nielsen;
    		$params = "rq=1&ad=".$a->unit_hash."&zone=".$zone->zone_hash."&dm=%dm%&i=%mac%&ip=%ip%&ex=%ex%";
    		$ad['adv_impression_tracking_url'] = MAD_ADSERVING_PROTOCOL.MAD_SERVER_HOST."/".MAD_MONITOR_HANDLER."?".$params;
    		
    		$ads[] = $ad;
    	}
    	if(isset($ads))
    		return $ads;
    	return array();
    }
    /**
     * 获取广告素材的其他信息
     * @param $c
     */
    public function adExtra($c) {
    	$extra['time_target'] = $this->getTimeTarget($c->time_target);
    	$extra['region_target'] = $this->getAddressTarget($c->campaign_id);
    	$extra['channel_target'] = $this->getChannelTarget($c->campaign_id);
    	return $extra;
    }
    
    /**
     * 获取地域信息
     * @param $id
     */
    public function getAddressTarget($id) {
    	$targetings = CampaignTargeting::find(array(
    		"campaign_id = '".$id."' AND targeting_type='geo'",
    		"cache"=>array("key"=>CACHE_PREFIX."_CAMPAIGNTARGETING_GEO_".$id, "lifetime"=>MD_CACHE_TIME)
    	));
    	$rs = array();
    	if($targetings) {
    		$this->log("[getAddressTarget] geo target num->".count($targetings));
    		foreach($targetings as $t) {
    			$r = Regions::findFirst(array(
    				"targeting_code='".$t->targeting_code."'",
    				"cache"=>array("key"=>CACHE_PREFIX."_REGIONS_".$t->targeting_code, "lifetime"=>MD_CACHE_TIME)
    			));
    			if($r){
    				$this->log("[getAddressTarget] region id->".entry_id);
    				$rs[] = array("name"=>$r->region_name, "code"=>$t->targeting_code);
    			}
    		}
    	}
    	return $rs;
    }
    
    /**
     * 时间段格式转换
     * @param $time
     */
    public function getTimeTarget($time) {
    	if($time<1 || $time>=(pow(2,24)-1))
    		return array();
    	for($i=0; $i<24; $i++) {
    		if(($time>>$i) & 1 == 1) {
    			$times[] = $i;
    		}
    	}
    	$this->log("[getTimeTarget] time->".implode(",", $times));
    	return $times;
    }
    
    /**
     * channel target
     * @param unknown $id
     */
    public function getChannelTarget($id) {
    	$targetings = CampaignTargeting::find(array(
    			"campaign_id = '".$id."' AND targeting_type='channel'",
    			"cache"=>array("key"=>CACHE_PREFIX."_CAMPAIGNTARGETING_CHANNEL_".$id, "lifetime"=>MD_CACHE_TIME)
    	));
    	$cs = array();
    	if($targetings) {
    		$this->log("[getChannelTarget] channel target num->".count($targetings));
    		foreach($targetings as $t) {
    			$c = Channels::findFirst(array(
    					"channel_id='".$t->targeting_code."'",
    					"cache"=>array("key"=>CACHE_PREFIX."_CHANNELS_".$t->targeting_code, "lifetime"=>MD_CACHE_TIME)
    			));
    			if($c){
    				$this->log("[getChannelTarget] id->".entry_id);
    				$cs[] = $c->channel_name;
    			}
    		}
    	}
    	return $cs;
    }
    
    public function log($log) {
    	$this->debugLog("[MDNetworkBatchController]".$log);
    }
}