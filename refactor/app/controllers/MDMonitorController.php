<?php
class MDMonitorController extends RESTController {
	public function get() {
		$return_type = "json";
		$rq = $this->request->get("rq", null, 1);
		if($rq != 1) {
			$return_type = "xml";
		}
		
		$result = $this->monitor();
		
		if($return_type=='json') {
			return $this->executeXml("code/xml", $result);
		}else if($return_type=='xml'){
			$this->outputJson(array("code"=>$result['code']));
		}
	}
	public function monitor() {
		$data['param_ip'] = $this->request->get("ip", null, '');
		$data['zone_hash'] = $this->request->get("zone", null, '');
		$data['ad_hash'] = $this->request->get("ad", null, '');
		$data['device_name'] = $this->request->get("dm", null, '');
		$data['i'] = $this->request->get("i", null, '');
		$data['ex'] = $this->request->get("ex", null, '');
		$data['origin_ip'] = $this->request->getClientAddress(TRUE);
		
		$this->logDebug("[get] origin ip->" . $data['origin_ip']);
		$this->logDebug("[get] param ip->" . $data['param_ip']);
		
		if($this->configApp('md_monitor_ip_check')) {
			if($this->existIp($data['origin_ip'])) {
				if($this->isValidIp($data['param_ip'])) {
					$data['ip'] = $data['param_ip'];
				} else {
					$data['ip'] = $data['origin_ip'];
				}
			} else {
				$data['ip'] = $data['origin_ip'];
			}
		} else {
			if($this->isValidIp($data['param_ip'])) {
				$data['ip'] = $data['param_ip'];
			} else {
				$data['ip'] = $data['origin_ip'];
			}
		}
		
		$current_time = time();
		$current_date = date('Y-m-d H:i:s', $current_time);
		
		$zone_detail = Zones::findByHash($data['zone_hash']);
		if(!$zone_detail) {
			$results['code'] = "30001";
			return $results;
		}
		$this->logDebug("[get] get zone id->" . $zone_detail->entry_id);
		
		$ad = $this->getAdFromHash($data['ad_hash']);
		if(!$ad) {
			$results['code'] = "30001";
			return $results;
		}
		
		$reporting = $this->reportingDbUpdate($zone_detail, $ad, $data, $current_time);
		$reporting['monitor_ip'] = $data['ip'];
		if(empty($data['i'])) {
			$reporting['ex'] = $this->request->getUserAgent();
			$extra = $this->processMonitorExtraData($reporting['ex']);
			$reporting['equipment_key'] = $extra['Dnum'];
			$reporting['device_name'] = $extra['sn'];
			$reporting['ex'] = $extra['extra'];
		} else {
			$reporting['equipment_key'] = $data['i'];
			$reporting['device_name'] = $data['device_name'];
			$reporting['ex'] = $data['ex'];
		}
		
		// 记录device_log
		$this->sendToDeviceRequestLog($reporting, $current_time);
		$this->handleUrl($reporting);
		$results['code'] = "00000";
		return $results;
	}
	public function existIp($ip) {
		if(empty($ip))
			return false;
		$s = ServerIp::findFirst(array (
				"ip= ?0", 
				"bind" => array (
						0 => $ip 
				), 
				"cache" => array (
						"key" => CACHE_PREFIX . "_SERVERIP_" . $ip, 
						"lifetime" => CACHE_TIME 
				) 
		));
		return $s?true:false;
	}
	public function getAdFromHash($hash) {
		if(empty($hash))
			return false;
		$ad = AdUnits::findFirst(array (
				"unit_hash= ?0", 
				"bind" => array (
						0 => $hash 
				), 
				"cache" => array (
						"key" => CACHE_PREFIX . "_ADUNITS_" . $hash, 
						"lifetime" => CACHE_TIME 
				) 
		));
		return $ad?$ad:false;
	}
	public function getCampaign($id) {
		$c = Campaigns::findFirst(array (
				"campaign_id= ?0", 
				"bind" => array (
						0 => $id 
				), 
				"cache" => array (
						"key" => CACHE_PREFIX . "_CAMPAIGNS_" . $id, 
						"lifetime" => CACHE_TIME 
				) 
		));
		return $c?$c:false;
	}
	public function reportingDbUpdate($zone_detail, $ad, $data, $time) {
		$reporting['ip'] = $data['ip'];
		$reporting['device_name'] = $data['device_name'];
		$reporting['publication_id'] = $zone_detail->publication_id;
		$reporting['zone_id'] = $zone_detail->entry_id;
		$reporting['campaign_id'] = $ad->campaign_id;
		$reporting['creative_id'] = $ad->adv_id;
		$reporting['timestamp'] = $time;
		$reporting['requests'] = 1;
		$reporting['clicks'] = 0;
		$reporting['impressions'] = 1;
		$reporting['report_hash'] = md5(serialize($reporting));
		
		$this->sendToBeanstalk($this->config("beanstalk", "tube_reporting"), serialize($reporting));
		return $reporting;
	}
	
	// 第一部分：浏览器原User-Agent
	// 第二部分：#2.0#号，用于分隔，并表示本UA规范的版本目前是2.0
	// 第三部分：终端品牌/终端型号/主系统版本/浏览器版本/终端分辨率
	// 第四部分：(Dnum,Didtoken; DID,HuanID,用户token)
	public function processMonitorExtraData($data) {
		$this->logDebug("[processMonitorExtraData] data->" . $data);
		$ex = array ();
		
		// #分割，获取原user-agent，ua版本，其他信息
		$parts = explode('#', $data);
		if(!is_array($parts)) {
			return $ex;
		}
		$ex['userAgent'] = trim($parts[0], " ");
		$ex['userAgentVersion'] = trim($parts[1], " ");
		if(!isset($parts[2]))
			return $ex;
			
			// /分割，获取终端品牌/终端型号/主系统版本/浏览器版本/其他信息
		$part3s = explode('/', $parts[2]);
		if(is_array($part3s)) {
			$ex['brands'] = trim($part3s[0], " ");
			$ex['sn'] = trim($part3s[1], " ");
			$ex['systemVersion'] = trim($part3s[2], " ");
			$ex['browseVersion'] = trim($part3s[3], " ");
			$part4 = trim($part3s[4], " ");
		}
		
		if(isset($part4)) {
			// 正则匹配，获取 终端分辨率，第四部分信息
			$pattern = "/(.+?)\((.+)\)$/";
			if(preg_match($pattern, $part4, $matchs)) {
				$part3 = $matchs[1];
				$part5 = $matchs[2];
			}
			$ex['quality'] = $part3;
			
			// ;分割，分别获取第四部分信息
			$part4s = explode(';', $part5);
			if(is_array($part4s)) {
				$part4s1s = explode(',', $part4s[0]);
				if(is_array($part4s1s)) {
					$ex['Dnum'] = trim($part4s1s[0], " ");
					$ex['Didtoken'] = trim($part4s1s[1], " ");
				}
				$part4s2s = explode(',', $part4s[1]);
				if(is_array($part4s2s)) {
					$ex['DID'] = trim($part4s2s[0], " ");
					$ex['HuanID'] = trim($part4s2s[1], " ");
					$ex['Usertoken'] = trim($part4s2s[2], " ");
				}
			}
		}
		return $ex;
	}
	public function handleUrl($data) {
		$current_timestamp = time();
		
		$url['ip'] = $data['ip'];
		$url['device_name'] = $data['device_name'];
		$url['publication_id'] = $data['publication_id'];
		$url['zone_id'] = $data['zone_id'];
		$url['campaign_id'] = $data['campaign_id'];
		$url['creative_id'] = $data['creative_id'];
		$url['equipment_key'] = $data['equipment_key'];
		$url['ex'] = $data['ex'];
		$url['timestamp'] = $current_timestamp;
		
		$this->sendToBeanstalk($this->config("beanstalk", "tube_tracking_url"), $url);
	}
	
	private function sendToDeviceRequestLog($reporting, $current_time) {
		$log['equipment_sn'] = '';
		$log['equipment_key'] = $reporting['equipment_key'];
		$log['device_name'] = $reporting['device_name'];
		$log['user_pattern'] = '';
		$log['date'] = $current_time;
		$log['operation_type'] = self::OPERATION_TYPE_IMPRESSION;
		$log['operation_extra'] = '';
		$log['publication_id'] = $reporting['publication_id'];
		$log['zone_id'] = $reporting['zone_id'];
		$log['campaign_id'] = $reporting['campaign_id'];
		$log['creative_id'] = $reporting['creative_id'];
		$log['client_ip'] = $reporting['ip'];
		$log['business_id'] = BUSINESS_ID;
		$this->sendToBeanstalk($this->config("beanstalk", "tube_request_device_log"), serialize($log));
	}
	
	public function logDebug($log) {
		$this->log("[MDMonitorController]" . $log, Phalcon\Logger::DEBUG);
	}
}