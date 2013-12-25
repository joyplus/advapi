<?php

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDMonitorController extends RESTController{

    public function get(){
		$data['param_ip'] = $this->request->get("ip", null, '');
		$data['zone_hash'] = $this->request->get("zone", null, '');
		$data['ad_hash'] = $this->request->get("ad", null, '');
		$data['device_name'] = $this->request->get("dm", null, '');
		$data['i'] = $this->request->get("i", null, '');
		$data['ex'] = $this->request->get("ex", null, '');
		$data['origin_ip'] = $this->request->getClientAddress(TRUE);
		
		$this->log("[get] origin ip->".$data['origin_ip']);
		$this->log("[get] param ip->".$data['param_ip']);
		if(MAD_MONITOR_IP_CHECK && $this->existIp($data['origin_ip'])) {
			$data['ip'] = $data['param_ip'];
		}else{
			$data['ip'] = $data['origin_ip'];
		}
		$geo_codes = $this->getCodeFromIp($data['ip']);
		$data['province_code'] = $geo_codes[0];
		$data['city_code'] = $geo_codes[1];
		
		$zone_detail = $this->get_placement($data['zone_hash']);
		if(!$zone_detail) {
			return $this->codeInputError();
		}
		$this->log("[get] get zone id->".$zone_detail->entry_id);
		
		$ad = $this->getAdFromHash($data['ad_hash']);
		if(!$ad) {
			return $this->codeInputError();
		}
		
		$campaign = $this->getCampaign($ad->campaign_id);
		if(!$campaign) {
			return $this->codeInputError();
		}
		$this->log("[get] find campaign id->".$campaign->campaign_id);
		$display_ad = array();
		$this->reporting_db_update($display_ad, $data, $zone_detail->publication_id, $zone_detail->entry_id, $campaign->campaign_id, $ad->adv_id, "", 1, 0, 1, 0);
    	return $this->codeSuccess();
    }

    public function existIp($ip) {
    	if(empty($ip))
    		return false;
    	$s = ServerIp::findFirst(array(
    		"ip= ?0",
    		"bind"=>array(0=>$ip),
    		"cache"=>array("key"=>CACHE_PREFIX."existIp".$ip)
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
    		"cache"=>array("key"=>CACHE_PREFIX."getAdFromHash".$hash)
    	));
    	if($ad)
    		return $ad;
    	return false;
    }
    
    public function getCampaign($id) {
    	$c = Campaigns::findFirst(array(
    		"campaign_id= ?0",
    		"bind"=>array(0=>$id),
    		"cache"=>array("key"=>CACHE_PREFIX."getCampaign".$id)
    	));
    	if($c)
    		return $c;
    	return false;
    }
    
    public function log($log) {
    	$this->debugLog("[MDMonitorController]".$log);
    }
}