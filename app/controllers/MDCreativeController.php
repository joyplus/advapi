<?php

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDCreativeController extends RESTController{

    public function get(){
    	
    	$hash = $this->request->get("s", null, '');
    	$this->log("[get] get hash->".$hash);
    	$zone_detail=$this->get_placement($hash);
    	$this->log("[get] get zone id->".$zone_detail->entry_id);
    	if(!$zone_detail) {
    		exit;
    	}
    	$url = $this->process($zone_detail);
    	if(empty($url))
    		exit;
    	$this->response->redirect($url, true);
    }
    
    private function process($zone) {
    	$date = date("Y-m-d");
    	$ads = array();
    	$campaign = $this->findCampaigns($zone, $date);
    	if ($campaign) {
    		$this->log("[process] find campaigns id->".$campaign->campaiagn_id);
    		$ad = $this->findAds($campaign, $zone, $date);
    		if($ad) {
    			$this->sendBeanstalk($zone, $ad);
    			if($ad->creative_unit_type==='open') {
    				if(!empty($ad->adv_creative_url_2)) {
    					return $ad->adv_creative_url_2;
    				}else{
    					return $ad->adv_creative_url_3;
    				}
    			}else{
    				return $ad->adv_creative_url;
    			}
    		}
    	}
    	return false;
    }
    private function sendBeanstalk($zone, $ad) {
    		$log['equipment_sn'] = '';
        	$log['equipment_key'] = '';
        	$log['device_name'] = '';
        	$log['user_pattern'] = '';
        	$log['date'] = date("Y-m-d H:i:s");;
        	$log['operation_type'] = '004';
        	$log['operation_extra'] = '';
        	$log['publication_id'] = $zone->publication_id;
        	$log['zone_id'] = $zone->entry_id;
        	$log['campaign_id'] = $ad->campaign_id;
        	$log['creative_id'] = $ad->adv_id;
        	$log['client_ip'] = $this->request->getClientAddress(TRUE);
        	$log['business_id'] = BUSINESS_ID;
        	try{
        		$queue = $this->getDi()->get('beanstalkRequestDeviceLog');
        		$queue->put(serialize($log));
        	}catch (Exception $e) {
        	}
    }
    private function buildCampaignSql($zone, $date) {
    	$conditions .= "(c1.targeting_type='placement' AND c1.targeting_code=:entry_id:)";
    	$params['entry_id'] = $zone->entry_id;
    	
    	$conditions .= " AND Campaigns.del_flg<>1 AND Campaigns.campaign_status=1 AND Campaigns.campaign_class<>2 AND Campaigns.campaign_start<=:campaign_start: AND Campaigns.campaign_end>=:campaign_end:";
    	$params['campaign_start'] = $date;
    	$params['campaign_end'] = $date;
    	
    	$conditions .= " AND (ad.del_flg<>1 AND ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: AND ad.adv_status=1)";
    	$params['adv_start'] = $date;
    	$params['adv_end'] = $date;
    	
    	$conditions .= " AND c_limit.total_amount_left>=1";
    	
    	return array("conditions"=>$conditions, "params"=>$params);
    }
    
    private function findCampaigns($zone, $date) {
    	$sets = $this->buildCampaignSql($zone, $date);
    	$result = $this->modelsManager->createBuilder()
    	->from('Campaigns')
    	->leftjoin('CampaignTargeting', 'Campaigns.campaign_id = c1.campaign_id', 'c1')
    	->leftjoin('CampaignLimit', 'Campaigns.campaign_id = c_limit.campaign_id', 'c_limit')
    	->leftjoin('AdUnits', 'Campaigns.campaign_id = ad.campaign_id', 'ad')
    	->where($sets['conditions'], $sets['params'])
    	->orderBy('Campaigns.campaign_priority DESC')
    	->getQuery()
    	->execute();
    	return count($result)>0?$result[0]:false;
    }
    
    private function findAds($c, $zone, $date) {
    	$conditions = "campaign_id = :campaign_id:";
		$params['campaign_id'] = $c->campaign_id;
    	
    	$conditions .= " AND adv_start<= :adv_start:";
    	$params['adv_start'] = $date;
    	
    	$conditions .= " AND adv_end>= :adv_end:";
    	$params['adv_end'] = $date;
    	
    	$conditions .= " AND adv_status = 1 AND del_flg<>1";
    	
    	//创意顺序排序
        $order = "adv_id";
        if($c->creative_show_rule==3){ //创意权重排序
        	$order = "creative_weight DESC";
        }
    	
    	$query_param = array(
    			"conditions" => $conditions,
    			"bind" => $params,
    			"order"=>$order,
    			"cache"=>array("key"=>CACHE_PREFIX."_ADUNITS_".md5(serialize($params)), "lifetime"=>MD_CACHE_TIME)
    	);
    	
    	$adUnits = AdUnits::find($query_param);
    	$adarray = array();
    	
    	foreach ($adUnits as $item) {
    		$add = array('ad_id'=>$item->adv_id,
    				'width'=>$item->adv_width,
    				'height'=>$item->adv_height,
    				'weight'=>$item->creative_weight
    		);
    		$adarray[] = $add;
    	}
    	//创意随机排序
    	if($c->creative_show_rule==1) {
    		shuffle($adarray);
    	}
    	
    	return count($adarray)>0?AdUnits::findFirst($adarray[0]['ad_id']):false;
    }
    
    private function log($log) {
    	$this->debugLog("[MDCreativeController]".$log);
    }
}