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
			return $results;
		}
		$this->log("[get] get zone id->".$zone_detail->entry_id);
		
		$ad = $this->getAdFromHash($data['ad_hash']);
		if(!$ad) {
			$results['return_code'] = "30001";
			$results['data']['status'] = "error";
			return $results;
		}

		
		$reporting = $this->reportingDbUpdate($zone_detail, $ad, $data);
		$reporting['monitor_ip'] = $data['ip'];
		if(empty($data['i'])) {
			$reporting['ex'] = $this->request->getUserAgent();
			$extra = $this->processMonitorExtraData($reporting['ex']);
			$reporting['equipment_key'] = $extra['Dnum'];
			$reporting['device_name'] = $extra['sn'];
			$reporting['ex'] = $extra['extra'];
			
		}else{
			$reporting['equipment_key'] = $data['i'];
			$reporting['device_name'] = $data['device_name'];
			$reporting['ex'] = $data['ex'];
		}
		
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
    	$reporting['requests'] = 1;
    	$reporting['clicks'] = 0;
    	$reporting['impressions'] = 1;
    	$reporting['report_hash'] = md5(serialize($reporting));
    	 	
    	$queue = $this->getDi()->get('beanstalkReporting');
    	$queue->put(serialize($reporting));
    	
    	return $reporting;
    }
    
    //第一部分：浏览器原User-Agent
    //第二部分：#2.0#号，用于分隔，并表示本UA规范的版本目前是2.0
    //第三部分：终端品牌/终端型号/主系统版本/浏览器版本/终端分辨率
    //第四部分：(Dnum,Didtoken; DID,HuanID,用户token)
    public function processMonitorExtraData($data) {
    	$this->debugLog("[processMonitorExtraData] data->".$data);
    	$ex = array();
		
		//#分割，获取原user-agent，ua版本，其他信息
		$parts = explode('#',$data);
		if(!is_array($parts)) {
			return $ex;
		}
		$ex['userAgent'] = trim($parts[0]," ");
		$ex['userAgentVersion'] = trim($parts[1]," ");
		if(!isset($parts[2]))
			return $ex;
		
		// /分割，获取终端品牌/终端型号/主系统版本/浏览器版本/其他信息
		$part3s = explode('/', $parts[2]);
		if(is_array($part3s)) {
			$ex['brands'] = trim($part3s[0]," ");
			$ex['sn'] = trim($part3s[1]," ");
			$ex['systemVersion'] = trim($part3s[2]," ");
			$ex['browseVersion'] = trim($part3s[3]," ");
			$part4 = trim($part3s[4]," ");
		}
		
		if(isset($part4)) {
			//正则匹配，获取 终端分辨率，第四部分信息
			$pattern = "/(.+?)\((.+)\)$/";
			if(preg_match($pattern, $part4, $matchs)) {
				$part3 = $matchs[1];
				$part5 = $matchs[2];
			}
			$ex['quality'] = $part3;
			
			// ;分割，分别获取第四部分信息
			$part4s = explode(';', $part5);
			if(is_array($part4s)){
				$part4s1s = explode(',', $part4s[0]);
				if(is_array($part4s1s)) {
					$ex['Dnum'] = trim($part4s1s[0]," ");
					$ex['Didtoken'] = trim($part4s1s[1]," ");
				}
				$part4s2s = explode(',', $part4s[1]);
				if(is_array($part4s2s)) {
					$ex['DID'] = trim($part4s2s[0]," ");
					$ex['HuanID'] = trim($part4s2s[1]," ");
					$ex['Usertoken'] = trim($part4s2s[2]," ");
				}
			}
		}
		return $ex;
    }
    
    public function log($log) {
    	$this->debugLog("[MDMonitorController]".$log);
    }
}