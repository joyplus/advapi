<?php

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDNetworkBatchController extends RESTController{

    public function get(){
    	$results = array();
    	$results['return_type'] = 'json';
    	
    	$hash = $this->request->get("s", null, '');
    	$this->log("[get] get hash->".$hash);
    	$zone_detail=$this->get_placement($hash);
    	$this->log("[get] get zone id->".$zone_detail->entry_id);
    	if(!$zone_detail) {
    		return $results;
    	}
    	$ads = $this->process($zone_detail);
    	$results['data'] = $ads;
    	return $results;
    }
    
    public function process($zone) {
    	$ads = array();
    	$campaigns = $this->findCampaigns($zone);
    	$this->log("[process] find campaigns num->".count($campaigns));
    	foreach ($campaigns as $c) {
    		$as = $this->findAds($c, $zone);
    		$ads = $ads + $as;
    	}
    	return $ads;
    }
    
    public function buildCampaignSql($zone) {
    	$conditions .= "(c1.targeting_type='placement' AND c1.targeting_code=:entry_id:)";
    	$params['entry_id'] = $zone->entry_id;
    	
    	$conditions .= " AND Campaigns.campaign_status=1 AND Campaigns.campaign_start<=:campaign_start: AND Campaigns.campaign_end>=:campaign_end:";
    	$params['campaign_start'] = date("Y-m-d");
    	$params['campaign_end'] = date("Y-m-d");
    	
    	$conditions .= " AND (ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: and  ad.adv_status=1)";
    	$params['adv_start'] = date("Y-m-d");
    	$params['adv_end'] = date("Y-m-d");
    	
    	$conditions .= " AND (c_limit.total_amount_left='' OR c_limit.total_amount_left>=1)";
    	
    	return array("conditions"=>$conditions, "params"=>$params);
    }
    
    public function findCampaigns($zone) {
    	$sets = $this->buildCampaignSql($zone);
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
    
    public function findAds($c, $zone) {
    	$conditions = "campaign_id = :campaign_id:";
		$params['campaign_id'] = $c->campaign_id;
    	
    	$conditions .= " AND adv_start<= :adv_start:";
    	$params['adv_start'] = date("Y-m-d");
    	
    	$conditions .= " AND adv_end>= :adv_end:";
    	$params['adv_end'] = date("Y-m-d");
    	
    	$conditions .= " AND adv_status = 1";
    	
    	//创意权重排序
    	$order = "creative_weight DESC";
    	
    	$query_param = array(
    			"conditions" => $conditions,
    			"bind" => $params,
    			"order"=>$order,
    			"cache"=>array("key"=>CACHE_PREFIX."_ADUNITS_".md5(serialize($params)))
    	);
    	
    	$adUnits = AdUnits::find($query_param);
    	$this->log("[findAds] campaign id->".$c->campaign_id);
    	$this->log("[findAds] adUnits num->".count($adUnits));
    	$ads = $this->processAds($adUnits, $c, $zone);
    	
    	return $ads;
    }
    
    /**
     * 生成ad的json数据
     * @param $adUnits
     * @param $c
     * @param $zone
     * @return array
     */
    public function processAds($adUnits, $c, $zone) {
    	if(count($adUnits) < 1)
    		return array();
    	$extra = $this->adExtra($c);
    	foreach ($adUnits as $a) {
    		$this->log("[processAds] ad id->".$a->adv_id.", ad extension->".$a->adv_creative_extension);
    		$video = $this->isVideo($a->adv_creative_extension);
    		if(!$video)
    			continue;
    		$ad = $extra;
    		$ad['adv_url'] = $this->get_creative_url($a,"",$a->adv_creative_extension);
    		$ad['adv_hash'] = $a->unit_hash;
    		if(isset($a->adv_impression_tracking_url) && !empty($a->adv_impression_tracking_url))
    			$ad['adv_impression_tracking_url_miaozhen'] = $a->adv_impression_tracking_url;
    		if(isset($a->adv_impression_tracking_url_iresearch) && !empty($a->adv_impression_tracking_url_iresearch))
    			$ad['adv_impression_tracking_url_iresearch'] = $a->adv_impression_tracking_url_iresearch;
    		if(isset($a->adv_impression_tracking_url_admaster) && !empty($a->adv_impression_tracking_url_admaster))
    			$ad['adv_impression_tracking_url_admaster'] = $a->adv_impression_tracking_url_admaster;
    		if(isset($a->adv_impression_tracking_url_nielsen) && !empty($a->adv_impression_tracking_url_nielsen))
    			$ad['adv_impression_tracking_url_nielsen'] = $a->adv_impression_tracking_url_nielsen;
    		$params = "ad=".$a->unit_hash."&zone=".$zone->zone_hash."&dm=%dm%&i=%mac%&ip=%ip%&ex=%ex%";
    		$ad['adv_impression_tracking_url'] = MAD_ADSERVING_PROTOCOL.MAD_SERVER_HOST."/".MAD_MONITOR_HANDLER."?".$params;
    		
    		$ads[] = $ad;
    	}
    	if(isset($ads))
    		return $ads;
    	return array();
    }
    
    /**
     * 判断是否是视频
     * @param $extension
     * @return boolean
     */
    public function isVideo($extension) {
    	//$exts = array("3gp","avi","flv","mp4","png");
    	$exts = array("3gp","avi","flv","mp4");
    	if(!isset($extension) || empty($extension))
    		return false;
    	foreach ($exts as $e) {
    		$index = strcasecmp($extension, $e);
    		if($index == 0) {
    			return true;
    		}
    	}
    	return false;
    }
    
    /**
     * 获取广告素材的其他信息
     * @param $c
     */
    public function adExtra($c) {
    	$extra['time_target'] = $this->getTimeTarget($c->time_target);
    	$extra['country_target'] = $this->getAddressTarget($c->campaign_id);
    	
    	return $extra;
    }
    
    /**
     * 获取地域信息
     * @param $id
     */
    public function getAddressTarget($id) {
    	$targetings = CampaignTargeting::find(array(
    		"campaign_id = '".$id."' AND targeting_type='geo'",
    		"cache"=>array("key"=>CACHE_PREFIX."_CAMPAIGNTARGETING_".$id)
    	));
    	
    	if($targetings) {
    		$this->log("[getAddressTarget] geo target num->".count($targetings));
    		foreach($targetings as $t) {
    			$r = Regions::findFirst(array(
    				"targeting_code='".$t->targeting_code."'",
    				"cache"=>array("key"=>CACHE_PREFIX."_REGIONS_".$t->targeting_code)
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
    
    public function log($log) {
    	$this->debugLog("[MDNetworkBatchController]".$log);
    }
}