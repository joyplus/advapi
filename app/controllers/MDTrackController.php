<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-15
 * Time: 下午3:36
 */

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDTrackController extends RESTController{

    public function get(){
        $result = $this->handleImpression();
        return $this->respond($result);
    }

    private function handleImpression(){

        $ad_hash = $this->request->get("ad", null, '');
        $zone_hash = $this->request->get("zone", null, '');
        $mac = $this->request->get("i", null, '');
        $dm = $this->request->get("dm", null, '');
        
        
        $ad = AdUnits::findFirst(array(
        	"unit_hash = '".$ad_hash."'",
        	"cache"=>array("key"=>CACHE_PREFIX."_ADUNIT_HASH_".$ad_hash,"lifetime"=>MD_CACHE_TIME)
        ));
        if(!$ad) {
        	return $this->codeInputError();
        }
        
        $zone = $this->get_placement($zone_hash);
        if(!$zone) {
        	return $this->codeInputError();
        }
        
        $left = $this->deduct_impression_num($ad->campaign_id, 1);
        
        if ($left){
        	$current_timestamp = time();
	        $reporting['ip'] = $this->request->getClientAddress(TRUE);
			$reporting['type'] = '1';
			$reporting['publication_id'] = $zone->publication_id;
			$reporting['zone_id'] = $zone->entry_id;
			$reporting['campaign_id'] = $ad->campaign_id;
			
			$reporting['creative_id'] = $ad->adv_id;
			$reporting['requests'] = 0;
			$reporting['impressions'] = 1;
			$reporting['clicks'] = 0;
			$reporting['timestamp'] = $current_timestamp;
			
			$reporting['report_hash'] = md5(serialize($reporting));
			
			$queue = $this->getDi()->get('beanstalkReporting');
			$queue->put(serialize($reporting));
			
			$reporting['equipment_key'] = $mac;
			$reporting['device_name'] = $dm;
			$this->save_request_log('track', $reporting);
        }

        return $this->codeSuccess();
    }

} 