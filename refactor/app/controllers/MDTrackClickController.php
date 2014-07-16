<?php
class MDTrackClickController extends RESTController {
	public function track() {
		$result = $this->handle('track');
		return $this->executeXml("code/xml", $result);
	}
	public function click() {
		$result = $this->handle('click');
		return $this->executeXml("code/xml", $result);
	}
	
	private function handle($type) {
		$ad_hash = $this->request->get("ad", null, '');
		$zone_hash = $this->request->get("zone", null, '');
		$mac = $this->request->get("i", null, '');
		$ds = $this->request->get("ds", null, '');
		$dm = $this->request->get("dm", null, '');
		
		$ad = AdUnits::findFirst(array (
				"unit_hash = '" . $ad_hash . "'", 
				"cache" => array (
						"key" => CACHE_PREFIX . "_ADUNIT_HASH_" . $ad_hash, 
						"lifetime" => CACHE_TIME 
				) 
		));
		if(!$ad) {
			return $this->codeInputError();
		}
		
		$zone = Zones::findByHash($zone_hash);
		if(!$zone) {
			return $this->codeInputError();
		}
		
		$left = $this->deductImpressionNum($ad->campaign_id, 1);
		
		$current_time = time();
		$current_date = date('Y-m-d H:i:s', $current_time);
		
		$reporting['ip'] = $this->request->getClientAddress(TRUE);
		$reporting['type'] = '1';
		$reporting['publication_id'] = $zone->publication_id;
		$reporting['zone_id'] = $zone->entry_id;
		$reporting['campaign_id'] = $ad->campaign_id;
		
		$reporting['creative_id'] = $ad->adv_id;
		$reporting['requests'] = 0;
		if($type=='track') {
			$reporting['impressions'] = 1;
			$reporting['clicks'] = 0;
			$reporting['operation_type'] = self::OPERATION_TYPE_IMPRESSION;
		}else if($type=='click'){
			$reporting['impressions'] = 0;
			$reporting['clicks'] = 1;
			$reporting['operation_type'] = self::OPERATION_TYPE_CLICK;
		}
		$reporting['timestamp'] = $current_time;
		
		$reporting['report_hash'] = md5(serialize($reporting));
		
		$this->sendToBeanstalk($this->config("beanstalk", "tube_reporting"), serialize($reporting));
		
		$reporting['equipment_key'] = $mac;
		
		if(empty($ds)) {
			$reporting['device_name'] = $dm;
		} else {
			$reporting['device_name'] = $ds;
		}
		$this->sendToDeviceRequestLog($reporting, $current_time);
		return $this->codeSuccess();
	}
	
	private function sendToDeviceRequestLog($reporting, $current_time) {
		$log['equipment_sn'] = '';
		$log['equipment_key'] = $reporting['equipment_key'];
		$log['device_name'] = $reporting['device_name'];
		$log['user_pattern'] = '';
		$log['date'] = $current_time;
		$log['operation_type'] = $reporting['operation_type'];
		$log['operation_extra'] = '';
		$log['publication_id'] = $reporting['publication_id'];
		$log['zone_id'] = $reporting['zone_id'];
		$log['campaign_id'] = $reporting['campaign_id'];
		$log['creative_id'] = $reporting['creative_id'];
		$log['client_ip'] = $reporting['ip'];
		$log['business_id'] = BUSINESS_ID;
		$this->sendToBeanstalk($this->config("beanstalk", "tube_request_device_log"), serialize($log));
	}
} 