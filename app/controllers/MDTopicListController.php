<?php

class MDTopicListController extends RESTController{

	public function get(){
    	$params['business_id'] = $this->request->get("bid", null, '');
    	$this->log("[get] bid->".$params['business_id']);
    	$topics = Topic::find(array(
    		"business_id = :business_id:",
    		"bind"=>$params,
    		"cache"=>array(
    				"key"=>CACHE_PREFIX."_TOPIC_BUSINESS_".$params['business_id'],
    				"lifetime"=>MD_CACHE_TIME
    		),
    		"order"=>"id"
    	));
    	foreach ($topics as $t) {
    		$row = $this->arrayKeysToSnake($t->toArray());
    		
    		$row['url'] = "".MAD_ADSERVING_PROTOCOL . MAD_SERVER_HOST
                ."/".MAD_TOPIC_GET_HANDLER."?s=".$row['hash'];
    		$rows[] = $row;
    	}
    	if(count($rows)<1){
    		$result['code'] = "20001";
    	}else{
    		$result['code'] = "00000";
    	}
    	$result['data'] = $rows;
    	$this->log("[get] data->".json_encode($result));
    	$this->outputJson("topic/index", $result);
	}
	private function log($log) {
		$this->debugLog("[TopicListController]".$log);
	}
    
}