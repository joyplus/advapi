<?php

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDMonitorController extends RESTController{

    public function get(){
    	$results['return_type'] = "json";
    	
		$data['param_ip'] = $this->request->get("ip", null, '');
		$data['zone_hash'] = $this->request->get("zone", null, '');
		$data['ad_hash'] = $this->request->get("ad", null, '');
		$data['device_name'] = $this->request->get("dm", null, '');
		$data['i'] = $this->request->get("i", null, '');
		$data['ex'] = $this->request->get("ex", null, '');
		$data['origin_ip'] = $this->request->getClientAddress(TRUE);
		
		$rq = $this->request->get("rq", null, 1);
		if($rq!=1){
			$results['return_type'] = "xml";
		}
		
		$this->log("[get] origin ip->".$data['origin_ip']);
		$this->log("[get] param ip->".$data['param_ip']);
		
		if(MAD_MONITOR_IP_CHECK){
			if($this->existIp($data['origin_ip'])) {
				if($this->is_valid_ip($data['param_ip'])){
					$data['ip'] = $data['param_ip'];
				}else{
					$data['ip'] = $data['origin_ip'];
				}
			}else{
				$data['ip'] = $data['origin_ip'];
			}
		}else{
			if($this->is_valid_ip($data['param_ip'])){
				$data['ip'] = $data['param_ip'];
			}else{
				$data['ip'] = $data['origin_ip'];
			}
		}
	
		$zone_detail = $this->get_placement($data['zone_hash']);
		if(!$zone_detail) {
			$results['return_code'] = "30001";
			$results['data']['status'] = "error";
			return $reqults;
		}
		$this->log("[get] get zone id->".$zone_detail->entry_id);
		
		$ad = $this->getAdFromHash($data['ad_hash']);
		if(!$ad) {
			$results['return_code'] = "30001";
			$results['data']['status'] = "error";
			return $reqults;
		}

		
		$reporting = $this->reportingDbUpdate($zone_detail, $ad, $data);
		$reporting['monitor_ip'] = $data['ip'];
		//记录device_log
		$this->save_request_log('monitor', $reporting);
		$results['return_code'] = "00000";
		$results['data']['status'] = "success";
		return $results;
    }

    public function existIp($ip) {
    	if(empty($ip))
    		return false;
    	$s = ServerIp::findFirst(array(
    		"ip= ?0",
    		"bind"=>array(0=>$ip),
    		"cache"=>array("key"=>CACHE_PREFIX."_SERVERIP_".$ip, "lifetime"=>MD_CACHE_TIME)
    	));
    	if($s)
    		return true;
    	return false;
    }
    
    public function getAdFromHash($hash) {
    	if(empty($hash))
    		return false;
    	$ad = AdUnits::findFirst(array(
    		"unit_hash= ?0",
    		"bind"=>array(0=>$hash),
    		"cache"=>array("key"=>CACHE_PREFIX."_ADUNITS_".$hash, "lifetime"=>MD_CACHE_TIME)
    	));
    	if($ad)
    		return $ad;
    	return false;
    }
    
    public function getCampaign($id) {
    	$c = Campaigns::findFirst(array(
    		"campaign_id= ?0",
    		"bind"=>array(0=>$id),
    		"cache"=>array("key"=>CACHE_PREFIX."_CAMPAIGNS_".$id, "lifetime"=>MD_CACHE_TIME)
    	));
    	if($c)
    		return $c;
    	return false;
    }
    
    public function reportingDbUpdate($zone_detail, $ad, $data) {
    	$current_timestamp = time();
    	
    	$reporting['ip'] = $data['ip'];
    	$reporting['device_name'] = $data['device_name'];
    	$reporting['publication_id'] = $zone_detail->publication_id;
    	$reporting['zone_id'] = $zone_detail->entry_id;
    	$reporting['campaign_id'] = $ad->campaign_id;
    	$reporting['creative_id'] =$ad->adv_id;
    	$reporting['timestamp'] = $current_timestamp;
    	$reporting['report_hash'] = md5(serialize($reporting));
    	 	
    	$queue = $this->getDi()->get('beanstalkReporting');
    	$queue->put(serialize($reporting));
    	
    	return $reporting;
    }
    
    public function log($log) {
    	$this->debugLog("[MDMonitorController]".$log);
    }
}