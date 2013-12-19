<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-4
 * Time: 下午12:17
 */

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDRequestController extends RESTController{

    public function get(){
      $result = $this->handleAdRequest();
      return $this->respond($result);
    }

    private function handleAdRequest(){
        $request_settings = array();
        $request_data = array();
        $display_ad = array();
        $errormessage = '';

        $this->prepare_r_hash($request_settings);

        $param_rt = $this->request->get('rt');
        if (!isset($param_rt)){
            $param_rt='';
        }

        $request_settings['referer'] = $this->request->get("p", null, '');
        $request_settings['device_name'] = $this->request->get("ds", null, '');
        $request_settings['device_movement'] = $this->request->get("dm", null, '');
        $request_settings['longitude'] = $this->request->get("longitude", null, '');
        $request_settings['latitude'] = $this->request->get("latitude", null, '');
        $request_settings['iphone_osversion'] = $this->request->get("iphone_osversion", null, '');
        $request_settings['i'] = $this->request->get("i", null, '');
        
        $request_settings['pattern'] = $this->request->get("up",null,'');
        $request_settings['video_type'] = $this->request->get("vc",null,'');

        $param_sdk = $this->request->get("sdk");
        if (!isset($param_sdk) or ($param_sdk!='banner' && $param_sdk!='vad')){
            $request_settings['sdk']='banner';
        }
        else {
            $request_settings['sdk']=$param_sdk;
        }

        /*Identify Response Type*/
        switch ($param_rt){
            case 'javascript':
                $request_settings['response_type']='json';
                $request_settings['ip_origin']='fetch';
                break;

            case 'json':
                $request_settings['response_type']='json';
                $request_settings['ip_origin']='fetch';
                break;

            case 'iphone_app':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='fetch';
                break;

            case 'android_app':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='fetch';
                break;

            case 'ios_app':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='fetch';
                break;

            case 'ipad_app':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='fetch';
                break;

            case 'xml':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='request';
                break;

            case 'api':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='request';
                break;

            case 'api-fetchip':
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='fetch';
                break;

            default:
                $request_settings['response_type']='html';
                $request_settings['ip_origin']='request';
                break;

        }

       if (MAD_MAINTENANCE){
           return $this->codeNoAds();
       }

        if (!$this->check_input($request_settings, $errormessage)){
            //global $errormessage;
            //print_error(1, $errormessage, $request_settings['sdk'], 1);
            //TODO: Unchecked MD Functions
            return $this->codeInputError();
        }

        $request_data['ip']=$request_settings['ip_address'];
        
        $device_detail = $this->getDevice($request_settings['device_movement']);
        if($device_detail) {
        	$request_settings['device_type'] = $device_detail->device_type;
        	$request_settings['device_brand'] = $device_detail->device_brands;
        	$request_settings['device_quality']= $device_detail->device_quality;
        }

        $zone_detail=$this->get_placement($request_settings, $errormessage);

        if (!$zone_detail){
            return $this->codeInputError();
        }

        $request_settings['adspace_width']=$zone_detail->zone_width;
        $request_settings['adspace_height']=$zone_detail->zone_height;

        //$request_settings['channel']=$this->getchannel($zone_detail);

        $this->update_last_request($zone_detail);

        $this->setGeo($request_settings);
        
        //处理试投放
        $cacheKey = CACHE_PREFIX.'UNIT_DEVICE'.$request_settings['i'].$request_settings['placement_hash'];
        $adv_id = $this->getCacheAdData($cacheKey);
        if($adv_id){
        	if (!$final_ad = $this->get_ad_unit($adv_id)){
        		return $this->codeNoAds();
        	}
        	if (!$this->build_ad($display_ad, $zone_detail, 1, $final_ad)){
        		return $this->codeNoAds();
        	}
        }else{
	        $this->buildQuery($request_settings, $zone_detail);
	
	        if ($campaign_query_result=$this->launch_campaign_query($request_settings['left-video'], $request_settings['campaign_conditions'], $request_settings['campaign_params'])){
	
	            $this->process_campaignquery_result($zone_detail, $request_settings, $display_ad, $campaign_query_result);
	
	            //TODO: Unchecked MD functions
	//            if (!$this->process_campaignquery_result($campaign_query_result, $zone_detail, $request_settings)){
	//
	//                launch_backfill();
	//            }
	        }
	        else {
	            //TODO: Unchecked MD functions
	            //launch_backfill();
	        }
        }
        if (isset($display_ad['available']) && $display_ad['available']==1){
            $this->track_request($request_settings, $zone_detail, $display_ad, 0);
            //display_ad();
            $this->prepare_ad($display_ad, $request_settings, $zone_detail);
            $display_ad['response_type'] = $request_settings['response_type'];
            $base_ctr="".MAD_ADSERVING_PROTOCOL . MAD_SERVER_HOST
                ."/".MAD_TRACK_HANDLER."?rh=".$display_ad['rh'];

            $display_ad['final_impression_url']=$base_ctr;
        }
        else {
            $this->track_request($request_settings, $zone_detail, $display_ad, 0);
            $display_ad['code'] = "20001";
        }

        return $display_ad;
    }


    function check_input(&$request_settings, &$errormessage){


        $this->prepare_ip($request_settings);


        if (!isset($request_settings['ip_address']) or !$this->is_valid_ip($request_settings['ip_address'])){
            $errormessage='Invalid IP Address';
            return false;
        }

        $param_s = $this->request->get('s');
        if (!isset($param_s) or empty($param_s) or !$this->validate_md5($param_s)){
            $errormessage='No valid Integration Placement ID supplied. (Variable "s")';
            return false;
        }

        $request_settings['placement_hash']=$param_s;

        $this->prepare_ua($request_settings);

        if (!isset($request_settings['user_agent']) or empty($request_settings['user_agent'])){
            $errormessage='No User Agent supplied. (Variable "u")';
            return false;
        }

        return true;
    }

    function get_placement(&$request_settings, &$errormessage){
		$zone = Zones::findFirst(array(
			"zone_hash = ?0",
			"bind" => array(0=>$request_settings['placement_hash']),
			"cache" => array("key"=>CACHE_PREFIX.$request_settings['placement_hash'])
		));
    	
        return $zone;
    }
    
    /**
     * 获取设备信息
     */
    function getDevice($device_movement) {
    	if(!isset($device_movement) || empty($device_movement)) {
    		return false;
    	}
    	$device = Devices::findFirst(array(
    		"conditions"=>"device_movement= ?1",
    		"bind"=>array(1=>$device_movement),
    		"cache"=>array("key"=>md5(CACHE_PREFIX.$device_movement))
    	));
    	return $device;
    }


    function get_publication_channel($publication_id){

        $publications = Publications::findFirst(array(
        		"inv_id='".$publication_id."'",
        		"cache"=>array("key"=>md5(CACHE_PREFIX.$publication_id))
        ));
        if ($publications) {
            return $publications->inv_defaultchannel;
        } else {
            return 0;
        }
    }

    function getchannel($zone_detail){

        if (is_numeric($zone_detail->zone_channel)){
            return $zone_detail->zone_channel;
        }
        else {
            return $this->get_publication_channel($zone_detail->publication_id);
        }

    }

    function update_last_request($zone_detail){

        $lastreq_dif=0;
        $timestamp=time();

        if ($zone_detail->zone_lastrequest){
            $lastreq_dif=$timestamp-$zone_detail->zone_lastrequest;
        }

        if ($lastreq_dif>=600 or $zone_detail->zone_lastrequest<1){
            $zones = new Zones();

            $connection = $this->modelsManager->getWriteConnection($zones);
            $status1 = $connection->update('md_zones', array('zone_lastrequest'), array($timestamp), "`entry_id`=".$zone_detail->entry_id, null);
            $status2 = $connection->update('md_publications', array('md_lastrequest'), array($timestamp), "`inv_id`=".$zone_detail->publication_id, null);

            if ($status1 && $status2) {
                return true;
            }
        }

        return false;
    }


    function buildQuery(&$request_settings, $zone_detail){
    	$this->getDi()->get('logger')->log("Settings addr:".$request_settings['province_code']."--".$request_settings['city_code']);
    	$conditions = ' (Campaigns.country_target=1';
    	$params = array();
    	if (isset($request_settings['province_code']) && !empty($request_settings['province_code']) && isset($request_settings['city_code']) && !empty($request_settings['city_code'])){
    		$conditions .= " OR (c1.targeting_type='geo' AND (c1.targeting_code=:province_code: OR c1.targeting_code=:city_code:)))";
    		$params['province_code'] = $request_settings['province_code'];
    		$params['city_code'] = $request_settings['city_code'];
    	}
    	else if (isset($request_settings['province_code']) && !empty($request_settings['province_code'])){
    		$conditions .= " OR (c1.targeting_type='geo' AND c1.targeting_code=:province_code:))";
    		$params['province_code'] = $request_settings['province_code'];
    	}
    	else {
    		$conditions .= ')';
    	}
    	
    	
    	if(isset($request_settings['video_type']) && is_numeric($request_settings['video_type']) && ($zone_detail->zone_type=='previous' || $zone_detail->zone_type=='middle' || $zone_detail->zone_type=='after')) {
    		$conditions .= " AND (Campaigns.video_target=1 OR (c2.targeting_type='video' AND c2.targeting_code=:video_type:))";
    		$params['video_type'] = $request_settings['video_type'];
    		$request_settings['left-video'] = true;
    	}else{
    		$request_settings['left-video'] = false;
    	}
//     	else if (isset($request_settings['channel']) && is_numeric($request_settings['channel']) && ($zone_detail->zone_type=='interstitial' || $zone_detail->zone_type=='mini_interstitial' || $zone_detail->zone_type=='banner' || $zone_detail->zone_type=='open')){
//     		$conditions .= " AND (Campaigns.channel_target=1 OR (c2.targeting_type='channel' AND c2.targeting_code=:channel:))";
//     		$params['channel'] = $request_settings['channel'];
//     	}
    
    	$conditions .= " AND (Campaigns.publication_target=1 OR (c3.targeting_type='placement' AND c3.targeting_code=:entry_id:))";
    	$params['entry_id'] = $zone_detail->entry_id;
    
    	/* if(isset($request_settings['pattern']) && is_numeric($request_settings['pattern'])){
    		$conditions .= " AND (Campaigns.pattern_target=1 OR (c4.targeting_type='pattern' AND c4.targeting_code=:pattern:))";
    		$params['pattern'] = $request_settings['pattern'];
    	} 
    
    	if(isset($request_settings['device_type']) && is_numeric($request_settings['device_type'])) {
    		$conditions .= " AND (Campaigns.device_type_target=1 OR (c5.targeting_type='device_type' AND c5.targeting_code=:device_type:))";
    		$params['device_type'] = $request_settings['device_type'];
    	}
    
    	if(isset($request_settings['device_brand']) && is_numeric($request_settings['device_brand'])) {
    		$conditions .= " AND (Campaigns.brand_target=1 OR (c6.targeting_type='device_brand' AND c6.targeting_code=:device_brand:))";
    		$params['device_brand'] = $request_settings['device_brand'];
    	}*/
    
    	if(isset($request_settings['device_quality']) && is_numeric($request_settings['device_quality'])) {
    		$conditions .= " AND (Campaigns.quality_target=1 OR (c7.targeting_type='quality' AND c7.targeting_code=:device_quality:))";
    		$params['device_quality'] = $request_settings['device_quality'];
    	}
    
    	$conditions .= " AND Campaigns.campaign_status=1 AND Campaigns.campaign_start<=:campaign_start: AND Campaigns.campaign_end>=:campaign_end:";
    	$params['campaign_start'] = date("Y-m-d");
    	$params['campaign_end'] = date("Y-m-d");
    
    	switch ($zone_detail->zone_type){
    		case 'banner':
    			$conditions .= " AND (Campaigns.campaign_type='network' OR (ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: and  ad.adv_status=1 AND ad.creative_unit_type='banner' AND ad.adv_width=:adv_width: AND ad.adv_height=:adv_height:))";
    			$params['adv_start'] = date("Y-m-d");
    			$params['adv_end'] = date("Y-m-d");
    			$params['adv_width'] = $zone_detail->zone_width;
    			$params['adv_height'] = $zone_detail->zone_height;
    			break;
    
    		case 'interstitial':
    		case 'mini_interstitial':
    			$conditions .= " AND (Campaigns.campaign_type='network' OR (ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: and  ad.adv_status=1 AND ad.creative_unit_type='interstitial'))";
    			$params['adv_start'] = date("Y-m-d");
    			$params['adv_end'] = date("Y-m-d");
    			break;
    		case 'open':
    			$conditions .= " AND (Campaigns.campaign_type='network' OR (ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: and  ad.adv_status=1 AND ad.creative_unit_type='open'))";
    			$params['adv_start'] = date("Y-m-d");
    			$params['adv_end'] = date("Y-m-d");
    			break;
    		case 'previous':
    			$conditions .= "AND (Campaigns.campaign_type='network' OR (ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: and  ad.adv_status=1 AND ad.creative_unit_type='previous'))";
    			$params['adv_start'] = date("Y-m-d");
    			$params['adv_end'] = date("Y-m-d");
    			break;
    		case 'middle':
    			$conditions .= " AND (Campaigns.campaign_type='network' OR (ad.adv_start<=:adv_start: AND ad.adv_end>=:adv_end: and  ad.adv_status=1 AND ad.creative_unit_type='middle' AND ad.adv_width=:adv_width: AND ad.adv_height=:adv_height:))";
    			$params['adv_start'] = date("Y-m-d");
    			$params['adv_end'] = date("Y-m-d");
    			$params['adv_width'] = $zone_detail->zone_width;
    			$params['adv_height'] = $zone_detail->zone_height;
    			break;
    		case 'after':
    			$conditions .= " AND (Campaigns.campaign_type='network' OR (ad.adv_start<=:adv_start: AND ad.adv_end>='".date("Y-m-d")."' and  ad.adv_status=1 AND ad.creative_unit_type='after'))";
    			$params['adv_start'] = date("Y-m-d");
    			$params['adv_end'] = date("Y-m-d");
    			break;
    	}
    
    	if (MAD_IGNORE_DAILYLIMIT_NOCRON && !$this->check_cron_active()){
    		$conditions .= " AND ((c_limit.total_amount_left='' OR c_limit.total_amount_left>=1) OR (c_limit.cap_type=1))";
    	}else{
    		$conditions .= " AND (c_limit.total_amount_left='' OR c_limit.total_amount_left>=1)";
    	}
    
    	$request_settings['campaign_conditions'] = $conditions;
    	$request_settings['campaign_params'] = $params ;
    
    }

    function get_last_cron_exec(){

//        $key='last_cron_execution';
//        $query="select var_value from md_configuration where var_name='last_limit_update'";
//        $cache_result=get_cache($key);

        $configuration = Configuration::findFirst(array("var_name='last_limit_update'"));

        if ($configuration){
            return $configuration->var_value;
        }
        else {
            return 0;
        }

    }

    private function check_cron_active(){

        $last_exec=$this->get_last_cron_exec();

        $d=time()-$last_exec;

        if ($last_exec==0 or $last_exec<1 or $last_exec=='' or $d>87000){
            return false;
        }
        else {
            return true;
        }
    }

    function launch_campaign_query($type, $conditions, $params){

    	$resultData = $this->getCacheDataValue(CACHE_PREFIX.md5(serialize($params)));
    	if($resultData){
    		return $resultData;
    	}
    	
		$campaignarray = array();
    	$result = $this->modelsManager->createBuilder()
	    	->from('Campaigns')
	    	->leftjoin('CampaignTargeting', 'Campaigns.campaign_id = c1.campaign_id', 'c1');
    	
    	if($type) {
    		$result = $result->leftjoin('CampaignTargeting', 'Campaigns.campaign_id = c2.campaign_id', 'c2');
    	}
	    	
	    	$result = $result->leftjoin('CampaignTargeting', 'Campaigns.campaign_id = c3.campaign_id', 'c3')
	    	//->leftjoin('CampaignTargeting', 'Campaigns.campaign_id = c4.campaign_id', 'c4')
	    	//->leftjoin('CampaignTargeting', 'Campaigns.campaign_id = c5.campaign_id', 'c5')
	    	//->leftjoin('CampaignTargeting', 'Campaigns.campaign_id = c6.campaign_id', 'c6')
	    	->leftjoin('CampaignTargeting', 'Campaigns.campaign_id = c7.campaign_id', 'c7')
	    	->leftjoin('CampaignLimit', 'Campaigns.campaign_id = c_limit.campaign_id', 'c_limit')
	    	->leftjoin('AdUnits', 'Campaigns.campaign_id = ad.campaign_id', 'ad')
	    	->where($conditions, $params)
	    	->groupBy(array('Campaigns.campaign_id'))
	    	->getQuery()
	    	->execute();
        
        foreach ($result as $item) {
            $add = array(
                'creative_show_rule'=>$item->creative_show_rule,
                'campaign_id'=>$item->campaign_id,
                'priority'=>$item->campaign_priority,
                'type'=>$item->campaign_type,
                'network_id'=>$item->campaign_networkid
            );
            array_push($campaignarray, $add);
        }

        if (count($campaignarray)<1){
            return false;
        }

        foreach ($campaignarray as $key => $row) {
            $campaign_id[$key]  = $row['campaign_id'];
            $priority[$key] = $row['priority'];
            $type[$key] = $row['type'];
            $creative_show_rule[$key] = $row['creative_show_rule'];
            $network_id[$key] = $row['network_id'];
        }

        array_multisort($priority, SORT_DESC, $campaignarray);

        $highest_priority=$campaignarray[0]['priority'];

        $final_ads=array();

        foreach (range($highest_priority, 1) as $number) {
            unset($val);
            $val = $this->removeElementWithValue($campaignarray, "priority", $number);
            shuffle($val);
            foreach ($val as $value) {
                array_push($final_ads, $value);
            }
        }
        $this->saveCacheDataValue(CACHE_PREFIX.md5(serialize($params)), $final_ads);
        return $final_ads;
    }

    private function removeElementWithValue($array, $key, $value){
        foreach($array as $subKey => $subArray){
            if($subArray[$key] != $value){
                unset($array[$subKey]);
            }
        }
        return $array;
    }

    private function process_campaignquery_result($zone_detail, &$request_settings, &$display_ad, $result){

        foreach($result as $key=>$campaign_detail)
        {
            if ($campaign_detail['type']=='network'){
                //TODO: Unchecked MD functions
//                $mdManager->reporting_db_update($display_ad, $request_settings, $zone_detail['publication_id'], $zone_detail['entry_id'], $campaign_detail['campaign_id'], '', $campaign_detail['network_id'], 0, 1, 0, 0);
//                if (network_ad_request($campaign_detail['network_id'], 0)){
//                    global $request_settings;
//                    $request_settings['active_campaign_type']='network';
//                    $request_settings['active_campaign']=$campaign_detail['campaign_id'];
//                    $request_settings['network_id']=$campaign_detail['network_id'];
//                    return true;
//                    break;
//                }
            }
            else {
                if ($this->select_ad_unit($display_ad, $zone_detail, $request_settings, $campaign_detail)){
                    $request_settings['active_campaign_type']='normal';
                    $request_settings['active_campaign']=$campaign_detail['campaign_id'];
                    return true;
                    break;
                }
            }
        }
        return false;
    }


    private function select_adunit_query($zone_detail, $campaign_detail){
    	$params = array();
		$conditions = "campaign_id = :campaign_id:";
		$params['campaign_id'] = $campaign_detail['campaign_id'];
		
		$conditions .= " AND adv_start<= :adv_start:";
		$params['adv_start'] = date("Y-m-d");
		
		$conditions .= " AND adv_end>= :adv_end:";
		$params['adv_end'] = date("Y-m-d");
		
		$conditions .= " AND adv_status = 1";
		
		$zone_type = $zone_detail->zone_type;
		if($zone_type=="mini_interstitial")
			$zone_type="interstitial";
		
		$conditions .= " AND creative_unit_type = :creative_unit_type:";
		$params['creative_unit_type'] = $zone_type;
        switch ($zone_type){
            case 'banner':
            case 'middle':
                $conditions .= " AND adv_width = :adv_width: AND adv_height= :adv_height:";
                $params['adv_width'] = $zone_detail->zone_width;
                $params['adv_height'] = $zone_detail->zone_height;
                break; 
        }
        //创意顺序排序
        $order = "adv_id";
        if($campaign_detail['creative_show_rule']==3){ //创意权重排序
        	$order = "creative_weight DESC";
        }
        $query_param = array(
        		"conditions" => $conditions,
        		"bind" => $params,
        		"order"=>$order,
        		"cache"=>array("key"=>CACHE_PREFIX.md5(serialize($params)))
        );

        //global $repdb_connected,$display_ad;
        $adUnits = AdUnits::find($query_param);

        //$query="SELECT adv_id, adv_height, adv_width FROM md_ad_units WHERE campaign_id='".$campaign_id."' and adv_start<='".date("Y-m-d")."' AND adv_end>='".date("Y-m-d")."' AND adv_status=1 ".$query_part['size']." ORDER BY adv_width DESC, adv_height DESC";

        //writetofile("request.log",'ad_unit_array: '.$query);

        $adarray = array();

        foreach ($adUnits as $item) {
            $add = array('ad_id'=>$item->adv_id,
                'width'=>$item->adv_width,
                'height'=>$item->adv_height,
            	'weight'=>$item->creative_weight
            );
            $adarray[] = $add;
        }

//        while($ad_detail=mysql_fetch_array($usrres)){
//            $add = array('ad_id'=>$ad_detail['adv_id'],'width'=>$ad_detail['adv_width'],'height'=>$ad_detail['adv_height']);
//            array_push($adarray, $add);
//        }

        if ($total_ads_inarray=count($adarray)<1){
            return false;
        }

        return $adarray;

    }

    private function select_ad_unit(&$display_ad, $zone_detail, &$request_settings, $campaign_detail){

        if (!$ad_unit_array = $this->select_adunit_query($zone_detail, $campaign_detail)){
            return false;
        }

        if($campaign_detail['creative_show_rule']==1){ //创意随机排序
        	shuffle($ad_unit_array);
        	$ad_id = $ad_unit_array[0]['ad_id'];
        }else{
        	$ad_id = $ad_unit_array[0]['ad_id'];
        }
        

        //writetofile("request.log",'ad_unit_array result: '.json_encode($ad_unit_array));

        if (!$final_ad = $this->get_ad_unit($ad_id)){
            return false;
        }
        //writetofile("request.log",'final_ad  result: '.json_encode($final_ad));
        if ($this->build_ad($display_ad, $zone_detail, 1, $final_ad)){
            return true;
        }

        return false;
    }

    private function get_ad_unit($id){

        //$query="SELECT adv_id, campaign_id, unit_hash, adv_type,adv_creative_extension, adv_click_url, adv_click_opentype, adv_chtml, adv_mraid, adv_bannerurl, adv_impression_tracking_url, adv_clickthrough_type, adv_creative_extension, creativeserver_id, adv_height, adv_width FROM md_ad_units WHERE adv_id='".$id."'";

        //$ad_detail=simple_query_maindb($query, true, 250);
        //writetofile("request.log",'final_ad: '.$query);
        $ad_detail = AdUnits::findFirst(array(
        	"adv_id = '".$id."'",
        	"cache"=>array("key"=>CACHE_PREFIX.$id)
        ));
        if (!$ad_detail){
            return false;
        }
        else {
            if (is_null($ad_detail->adv_creative_extension) || $ad_detail->adv_creative_extension==''){
                $bannerUrl=$ad_detail->adv_bannerurl;
                if(is_null($bannerUrl)){
                    $bannerUrl='';
                }
                $tempArray= explode(".", $bannerUrl);
                $ad_detail->adv_creative_extension=$tempArray[count($tempArray)-1];
            }
            return $ad_detail;
        }

    }

    private function build_ad(&$display_ad, $zone_detail, $type, $adUnit){
    	//素材类型 1普通上传 3富媒体
    	if($adUnit->adv_type==3) {
    		$display_ad['add_impression'] = true;
    	} else {
    		$display_ad['add_impression'] = false;
    	}

        if ($type==1){
            $display_ad['trackingpixel']=$adUnit->adv_impression_tracking_url;
            $display_ad['tracking_iresearch']=$this->changeParams($adUnit->adv_impression_tracking_url_iresearch);
            $display_ad['tracking_admaster']=$this->changeParams($adUnit->adv_impression_tracking_url_admaster);
            $display_ad['tracking_nielsen']=$this->changeParams($adUnit->adv_impression_tracking_url_nielsen);
            $display_ad['available']=1;
            $display_ad['ad_id']=$adUnit->adv_id;
            $display_ad['campaign_id']=$adUnit->campaign_id;

            switch ($zone_detail->zone_type){
                case 'banner':
                case 'middle':
                    $display_ad['main_type']='display';

                    $display_ad['trackingpixel']=$adUnit->adv_impression_tracking_url;
                    $display_ad['refresh']=$zone_detail->zone_refresh;
                    $display_ad['width']=$zone_detail->adv_width;
                    $display_ad['height']=$zone_detail->adv_height;
                    if (MAD_CLICK_ALWAYS_EXTERNAL or $adUnit->adv_click_opentype=='external'){
                        $display_ad['clicktype']='safari';
                        $display_ad['skipoverlay']=0;
                        $display_ad['skippreflight']='yes';
                    }
                    else {
                        $display_ad['clicktype']='inapp';
                        $display_ad['skipoverlay']=0;
                        $display_ad['skippreflight']='no';
                    }

                    switch ($adUnit->adv_type){
                        case 1:
                            $display_ad['type']='hosted';
                            $display_ad['click_url']=$adUnit->adv_click_url;

                            $display_ad['image_url']=$this->get_creative_url($adUnit,"",$adUnit->adv_creative_extension);
                            $display_ad['interstitial-creative_res_url']=$display_ad['image_url'];

                            /*if ($content['creativeserver_id']==1){
                             $display_ad['image_url']="".MAD_ADSERVING_PROTOCOL . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'])."".MAD_CREATIVE_DIR."".$content['unit_hash'].".".$content['adv_creative_extension']."";
                             }
                             else {
                             $server_detail=get_creativeserver($content['creativeserver_id']);
                             $display_ad['image_url']="".$server_detail['server_default_url']."".$content['unit_hash'].".".$content['adv_creative_extension']."";
                             }*/

                            break;

                        case 2:
                            $display_ad['type']='image-url';
                            $display_ad['image_url']=$adUnit->adv_bannerurl;
                            $display_ad['interstitial-creative_res_url']=$display_ad['image_url'];
                            $display_ad['click_url']=$adUnit->adv_click_url;
                            break;

                        case 3:
                            $display_ad['html_markup']=$adUnit->adv_chtml;
                            if ($adUnit->adv_mraid==1){
                                $display_ad['type']='mraid-markup';
                                $display_ad['skipoverlay']=1;
                            } else {
                                $display_ad['type']='markup';
                                if ($display_ad['click_url']=$this->extract_url($display_ad['html_markup'])){
                                    $display_ad['skipoverlay']=0;
                                }
                                else {
                                    $display_ad['skipoverlay']=1;
                                    $display_ad['click_url']='';
                                }

                            }
                            break;
                    }
                    break;
                case 'interstitial':
                case 'mini_interstitial':
                case 'previous':
                case 'after':
                    if('mini_interstitial'===$zone_detail->zone_type){
                        $display_ad['video-creative-width']=$zone_detail->zone_width;
                        $display_ad['video-creative-height']=$zone_detail->zone_height;
                    }
                    $display_ad['main_type']='interstitial';
                    if($zone_detail->zone_type=='interstitial' || $zone_detail->zone_type=='mini_interstitial'){
                    	$display_ad['type']='interstitial';
                    }else{
                    	$display_ad['type']=$zone_detail->zone_type;
                    }
                    $display_ad['animation']='none';
                    $display_ad['interstitial-orientation']='portrait';
                    $display_ad['interstitial-preload']=0;
                    $display_ad['interstitial-autoclose']=0;
                    $display_ad['interstitial-type']='markup';
                    $display_ad['interstitial-skipbutton-show']=1;
                    $display_ad['interstitial-skipbutton-showafter']=0;
                    $display_ad['interstitial-navigation-show']=0;
                    $display_ad['interstitial-navigation-topbar-show']=0;
                    $display_ad['interstitial-navigation-bottombar-show']=0;
                    $display_ad['interstitial-navigation-topbar-custombg']='';
                    $display_ad['interstitial-navigation-bottombar-custombg']='';
                    $display_ad['interstitial-navigation-topbar-titletype']='fixed';
                    $display_ad['interstitial-navigation-topbar-titlecontent']='';
                    $display_ad['interstitial-navigation-bottombar-backbutton']=0;
                    $display_ad['interstitial-navigation-bottombar-forwardbutton']=0;
                    $display_ad['interstitial-navigation-bottombar-reloadbutton']=0;
                    $display_ad['interstitial-navigation-bottombar-externalbutton']=0;
                    $display_ad['interstitial-navigation-bottombar-timer']=0;

                    if (!empty($adUnit->adv_impression_tracking_url)){
                        $tracking_pixel_html=$this->generate_trackingpixel($adUnit->adv_impression_tracking_url);
                    }
                    else {
                        $tracking_pixel_html='';
                    }

                    switch ($adUnit->adv_type){
                        case 1:
                            $creative_res_url=$this->get_creative_url($adUnit,"",$adUnit->adv_creative_extension); //<a href="mfox:external:'.$content['adv_click_url'].'">
                            $display_ad['interstitial-content']='<meta content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" name="viewport" />
<meta name="viewport" content="width=device-width" /><div style="position:absolute;top:0;left:0;"><a href="#">'.$this->getHtmlForCreativeResUrl($display_ad,$adUnit->adv_creative_extension,$creative_res_url).'</a>' . $tracking_pixel_html . '</div>';
                            break;

                        case 2:
                            $display_ad['interstitial-content']='<meta content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" name="viewport" />
<meta name="viewport" content="width=device-width" /><div style="position:absolute;top:0;left:0;"><a href="#">'.$this->getHtmlForCreativeResUrl($display_ad,$adUnit->adv_creative_extension,$adUnit->adv_bannerurl).'</a>' . $tracking_pixel_html . '</div>';
                            break;

                        case 3:
                            $display_ad['interstitial-content']=$adUnit->adv_chtml . $tracking_pixel_html;
                            break;

                    }

                    break;
                case 'open':
                	$display_ad['main_type']='interstitial';
                	$display_ad['type']='open';
                	$display_ad['animation']='none';
                	
                	$server_detail=$this->get_creativeserver($adUnit->creativeserver_id);
                	if($server_detail) {
                		if(isset($adUnit->adv_creative_extension) && !empty($adUnit->adv_creative_extension)){
                			$display_ad['creative-url']="".$server_detail->server_default_url."".$adUnit->unit_hash.".".$adUnit->adv_creative_extension;
                		}
                		if(isset($adUnit->adv_creative_extension_2) && !empty($adUnit->adv_creative_extension_2)){
                			$display_ad['creative-url_2']="".$server_detail->server_default_url."".$adUnit->adv_creative_extension_2;
                		}
                		if(isset($adUnit->adv_creative_extension_3) && !empty($adUnit->adv_creative_extension_3)){
                			$display_ad['creative-url_3']="".$server_detail->server_default_url."".$adUnit->adv_creative_extension_3;
                		}
                	}
                	$display_ad['interstitial-creative_res_url']=$url;
                	
                	break;
                
            }

            return true;
        }
        else if ($type==2){
            $valid_ad=0;
            //TODO object to array
            $display_ad=$adUnit->getSnapshotData();
            $display_ad['available']=1;

            switch ($display_ad['main_type']){
                case 'display':

                    switch ($display_ad['type']){
                        case 'markup':
                            $valid_ad=1;
                            if (!isset($display_ad['html_markup']) or empty($display_ad['html_markup'])){return false;}
                            if (!isset($display_ad['click_url']) or empty($display_ad['click_url'])){if (!$display_ad['click_url']=$this->extract_url($display_ad['html_markup'])){return false;} }
                            if (!isset($display_ad['clicktype']) or empty($display_ad['clicktype'])){$display_ad['clicktype']='safari';}
                            if (!isset($display_ad['refresh']) or !is_numeric($display_ad['refresh'])){$display_ad['refresh']=$zone_detail->zone_refresh;}
                            if (!isset($display_ad['skipoverlay']) or empty($display_ad['skipoverlay'])){$display_ad['skipoverlay']=0;}
                            if (!isset($display_ad['skippreflight']) or empty($display_ad['skippreflight'])){$display_ad['skippreflight']='yes';}
                            break;

                        case 'mraid-markup':
                            $valid_ad=1;
                            if (!isset($display_ad['html_markup']) or empty($display_ad['html_markup'])){return false;}
                            if (!isset($display_ad['clicktype']) or empty($display_ad['clicktype'])){$display_ad['clicktype']='safari';}
                            if (!isset($display_ad['refresh']) or !is_numeric($display_ad['refresh'])){$display_ad['refresh']=$zone_detail->zone_refresh;}
                            if (!isset($display_ad['skipoverlay']) or empty($display_ad['skipoverlay'])){$display_ad['skipoverlay']=1;}
                            if (!isset($display_ad['skippreflight']) or empty($display_ad['skippreflight'])){$display_ad['skippreflight']='yes';}
                            break;

                        case 'image-url':
                            $valid_ad=1;
                            if (!isset($display_ad['click_url']) or empty($display_ad['click_url'])){return false;}
                            if (!isset($display_ad['image_url']) or empty($display_ad['image_url'])){return false;}
                            if (!isset($display_ad['clicktype']) or empty($display_ad['clicktype'])){$display_ad['clicktype']='safari';}
                            if (!isset($display_ad['refresh']) or !is_numeric($display_ad['refresh'])){$display_ad['refresh']=$zone_detail->zone_refresh;}
                            if (!isset($display_ad['skipoverlay']) or empty($display_ad['skipoverlay'])){$display_ad['skipoverlay']=0;}
                            if (!isset($display_ad['skippreflight']) or empty($display_ad['skippreflight'])){$display_ad['skippreflight']='yes';}
                            break;

                    }

                    break;

                case 'interstitial':
                case 'mini_interstitial':
                    if('mini_interstitial' ===$zone_detail->zone_type){
                        $display_ad['video-creative-width']=$zone_detail->zone_width;
                        $display_ad['video-creative-height']=$zone_detail->zone_height;
                    }
                    $valid_ad=1;

                    /*switch ($display_ad['type']){
                     We might add some validation for Interstitials later.
                     }*/

                    break;
            }

            if ($valid_ad!=1){return false;}

            return true;
        }
        else {
            return false;
        }

    }

    private function get_creative_url($adUnit, $type, $extension){
        $server_detail=$this->get_creativeserver($adUnit->creativeserver_id);
        $image_url="".$server_detail->server_default_url."".$adUnit->unit_hash.$type.".".$extension."";

        return $image_url;
    }

    private function get_creativeserver($id){

        //$query="select server_default_url from md_creative_servers where entry_id='".$id."'";
        $creativeServers = CreativeServers::findFirst($id);

        if ($creativeServers){
            return $creativeServers;
        } else {
            return false;
        }
    }

    private function generate_trackingpixel($url){
        //return '<img style="display:none;" src="'.$url.'"/>';
        return '';
    }

    private function getHtmlForCreativeResUrl(&$display_ad, $extension,$url){

        if(strpos('png,jpeg,jpg,gif,bmp', $extension) !==false){
            $display_ad['interstitial-creative_res_url']=$url;
            return '<img src="'.$url.'">';
        }else {
            $display_ad['interstitial-creative_res_url']=$url;
            $display_ad['video-creative-url']=$url;
            $display_ad['type']='video';
            return '<video src="'.$url.'"  autoplay="" width="100%" height="100%"></video>';
        }

    }

    private function extract_url($input){

        if (preg_match("/href='([^']*)'/i", $input , $regs)){
            return $regs[1]; }

        else if (preg_match('/href="([^"]*)"/i', $input , $regsx)){
            return $regsx[1];
        }

        return false;
    }

    function prepare_ad(&$display_ad, &$request_settings, $zone_detail){

        $this->prepare_ctr($display_ad, $request_settings, $zone_detail);
        if (!empty($display_ad['click_url'])){
            $display_ad['final_click_url']=$display_ad['final_click_url'].'&o='.urlencode($display_ad['click_url']);
        }

        if (!empty($display_ad['impression_url'])){
            $display_ad['final_impression_url']=$display_ad['final_impression_url'].'&o='.urlencode($display_ad['impression_url']);
        }
        $this->prepare_markup($display_ad, $request_settings);

    }


    function prepare_ctr(&$display_ad, &$request_settings, $zone_detail){

        //$base_ctr="".MAD_ADSERVING_PROTOCOL . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'])."/".MAD_CLICK_HANDLER."?zone_id=".$zone_detail['entry_id']."&h=".$request_settings['request_hash']."";
        $base_ctr="".MAD_ADSERVING_PROTOCOL .MAD_SERVER_HOST ."/".MAD_CLICK_HANDLER."?zone_id=".$zone_detail->entry_id."&h=".$request_settings['request_hash']."";

        if ($display_ad['main_type']=='display'){

            switch ($request_settings['active_campaign_type']){
                case 'normal':
                    $base_ctr=$base_ctr . "&type=normal&campaign_id=".$display_ad['campaign_id']."&ad_id=".$display_ad['ad_id']."";
                    break;

                case 'network':
                    $base_ctr=$base_ctr . "&type=network&campaign_id=".$request_settings['active_campaign']."&network_id=".$request_settings['network_id']."";
                    break;

                case 'backfill':
                    $base_ctr=$base_ctr . "&type=backfill&network_id=".$request_settings['network_id']."";
                    break;
            }
            $base_ctr=$base_ctr . "&c=".strtr(base64_encode($this->get_destination_url($display_ad)), '+/=', '-_,')."";

            $display_ad['final_click_url']=$base_ctr;
        }
    }

    function prepare_markup(&$display_ad, &$request_settings){

        if ($display_ad['main_type']=='display'){

            switch ($display_ad['type']){
                case 'hosted':
                case 'image-url':
                    if ($request_settings['response_type']!='xml'){ //'.$display_ad['final_click_url'].'
                        $final_markup='<a id="mAdserveAdLink" href="#" target="_self"><img id="mAdserveAdImage" src="'.$display_ad['image_url'].'" border="0"/></a><br>';
                    }
                    else {
                        $final_markup='<body style="text-align:center;margin:0;padding:0;"><div align="center"><a id="mAdserveAdLink" href="#" target="_self"><img id="mAdserveAdImage" src="'.$display_ad['image_url'].'" border="0"/></a></div></body>';
                    }
                    break;


                case 'markup':
                    $final_markup=$this->generate_final_markup($display_ad, $$request_settings);
                    break;

                case 'mraid-markup':
                    $final_markup=$display_ad['html_markup'];
                    break;
            }

            if (isset($display_ad['trackingpixel']) && !empty($display_ad['trackingpixel']) && $display_ad['trackingpixel']!=''){
                $final_markup=$final_markup . $this->generate_trackingpixel($display_ad['trackingpixel']);
            }

            $display_ad['final_markup']=$final_markup;

        }

    }

    function generate_final_markup(&$display_ad, &$request_settings){

        if (isset($display_ad['click_url']) && !empty($display_ad['click_url'])){
            $markup=str_replace($display_ad['click_url'], $display_ad['final_click_url'], $display_ad['html_markup']);
        }
        else {
            $markup=$display_ad['html_markup'];
        }
        return $markup;
    }
    
    function get_destination_url(&$display_ad) {
    	if (isset($display_ad['click_url'])){
    		return $display_ad['click_url'];
    	} else {
    		return '';
    	}
    }
    
    function changeParams($url) {
    	if(!isset($url) || empty($url))
    		return "";
    	if(strpos($url,"?")) {
    		$u = str_replace("?","?mac=%mac%&dm=%dm%&",$url);
    	}else{
    		$u = $url."?mac=%mac%&dm=%dm%";
    	}
    	return $u;
    }
}