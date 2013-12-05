<?php 

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
Phalcon\DI,
Phalcon\Cache\Backend\Memcache;

class MDClickController extends RESTController {
	public function get() {
		$result = $this->handleClick();
		return $this->respond($result);
	}
	
	public function handleClick() {
		$data_c = $this->request->get("c");
		$data_type = $this->request->get("type");
		$data_h = $this->request->get("h",null,"");
		if(!isset($data_c) or empty($data_c) or !isset($data_type))
			return $this->codeInputError(); 
		
		if (MAD_CLICK_IMMEDIATE_REDIRECT){
			ob_start();
			$size = ob_get_length();
		
			// send headers to tell the browser to close the connection
			$this->redirect();
			$response = $this->di->get('response');
			$response->setHeader("Content-Length",$size);
			$response->setHeader('Connection', 'close');
		
			// flush all output
			ob_end_flush();
			ob_flush();
			flush();
		
			$this->track();
		
		}
		
		else {
		
			$this->track();
			$this->redirect();
		
		}
		return $this->codeSuccess();
	}

	private function track(){
		$req = $this->request;
		$request_settings = array();
		$display_ad = array();
		$data_ds = $req->get('ds');
		if (isset($data_ds)){
			$request_settings['device_name']=$data_ds;
		}
		$request_settings['ip_origin']='fetch';
		$this->prepare_ip($request_settings);
		$this->setGeo($request_settings);
		
		$cache_key= CACHE_PREFIX.$data_h;
		 if (MAD_TRACK_UNIQUE_CLICKS){
		
			$cache_result=$this->getCacheDataValue($cacheKey);
		
			if ($cache_result && $cache_result==1){
				return $this->codeSuccess();
			}
			else {
				$this->saveCacheDataValue($cacheKey, 1);
			}
		
		} 
		$zone_id = $req->get("zone_id");
		if (!is_numeric($zone_id)){
			return false;
		}
		
		/* Get the Publication */
		$zone_detail = Zones::findFirst("entry_id = '".$zone_id."'");
		
		if (!$zone_detail or $zone_detail->publication_id<1){
			return false;
		}
		
		
		switch($req->get('type')){
		
			case 'normal':
				$this->reporting_db_update($display_ad, $request_settings, $zone_detail->publication_id, $zone_id, $req->get('campaign_id'), $req->get('ad_id'), '', 0, 0, 0, 1);
				break;
		
			case 'network':
				$this->reporting_db_update($display_ad, $request_settings, $zone_detail->publication_id, $zone_id, $req->get('campaign_id'), '', $req->get('network_id'), 0, 0, 0, 1);
				break;
		
			case 'backfill':
				$this->reporting_db_update($display_ad, $request_settings, $zone_detail->publication_id, $zone_id, '', '', $req->get('network_id'), 0, 0, 0, 1);
				break;
		
		}
	}
	
	function prepare_click_url($input){
		$output = base64_decode(strtr($input, '-_,', '+/='));
		return $output;
	}
	
	function redirect(){
		header ("Location: ".$this->prepare_click_url($this->request->get("c").""));
	}
}
?>