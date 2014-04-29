<?php


/**
 * Base RESTful Controller.
 * Supports queries with the following paramters:
 *   Searching:
 *     q=(searchField1:value1,searchField2:value2)
 *   Partial Responses:
 *     fields=(field1,field2,field3)
 *   Limits:
 *     limit=10
 *   Partials:
 *     offset=20
 *
 */
class RESTController extends BaseController{


	/**
	 * Provides a base CORS policy for routes like '/users' that represent a Resource's base url
	 * Origin is allowed from all urls.  Setting it here using the Origin header from the request
	 * allows multiple Origins to be served.  It is done this way instead of with a wildcard '*'
	 * because wildcard requests are not supported when a request needs credentials.
	 *
	 * @return true
	 */
	public function optionsBase(){
		$response = $this->di->get('response');
		$response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, HEAD');
		$response->setHeader('Access-Control-Allow-Origin', $this->di->get('request')->header('Origin'));
		$response->setHeader('Access-Control-Allow-Credentials', 'true');
		$response->setHeader('Access-Control-Allow-Headers', "origin, x-requested-with, content-type");
		$response->setHeader('Access-Control-Max-Age', '86400');
		return true;
	}

	/**
	 * Provides a CORS policy for routes like '/users/123' that represent a specific resource
	 *
	 * @return true
	 */
	public function optionsOne(){
		$response = $this->di->get('response');
		$response->setHeader('Access-Control-Allow-Methods', 'GET, PUT, PATCH, DELETE, OPTIONS, HEAD');
		$response->setHeader('Access-Control-Allow-Origin', $this->di->get('request')->header('Origin'));
		$response->setHeader('Access-Control-Allow-Credentials', 'true');
		$response->setHeader('Access-Control-Allow-Headers', "origin, x-requested-with, content-type");
		$response->setHeader('Access-Control-Max-Age', '86400');
		return true;
	}

	/**
	 * Should be called by methods in the controllers that need to output results to the HTTP Response.
	 * Ensures that arrays conform to the patterns required by the Response objects.
	 *
	 * @param  array $recordsArray Array of records to format as return output
	 * @return array               Output array.  If there are records (even 1), every record will be an array ex: array(array('id'=>1),array('id'=>2))
	 */
	protected function respond($recordsArray){

		return $recordsArray;

	}

    function check_forwarded_ip(){

        if ($this->request->has("h[X-Forwarded-For]")){
            $res_array = explode(",", $this->request->get("h[X-Forwarded-For]"));
            return $res_array[0];
        }

        //TODO: Unchecked MD Functions
        $server_xforworded = $this->request->getServer('HTTP_X_FORWARDED_FOR');
        if (isset($server_xforworded) && !empty($server_xforworded)){
            $res_array = explode(",", $server_xforworded);
            return $res_array[0];
        }
        return false;
    }

    function is_valid_ip($ip, $include_priv_res = true)
    {
        return $include_priv_res ?
            filter_var($ip, FILTER_VALIDATE_IP) !== false :
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    function prepare_ip(&$request_settings){

        switch ($request_settings['ip_origin']){
            case 'request':
                if ($this->request->has('ip')){
                    $request_settings['ip_address']=$this->request->get('ip');
                }
                break;

            case 'fetch':
            	$request_settings['ip_address']=$this->request->getClientAddress(TRUE);
        }
		$this->debugLog("[prepare_ip] ip address->".$request_settings['ip_address']);
    }

    function validate_md5($hash){
        if(!empty($hash) && preg_match('/^[a-f0-9]{32}$/', $hash)){
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * MDRequest Functions
     */

    function prepare_r_hash(&$request_settings){

        $request_settings["request_hash"]=md5(uniqid(microtime()));

    }

    function prepare_ua(&$request_settings){

        if ($this->request->has('h[User-Agent]')){
            $request_settings['user_agent']=$this->request->get('h[User-Agent]', null, '');
        }
        else if ($this->request->has('u')){
            $request_settings['user_agent']=$this->request->get('u');
        }
    }

    function getCacheData(){
        $cacheData = $this->getDi()->get("cacheData");
        //$cache = $cacheService->resolve();
        return $cacheData;
    }
    
	function getCacheAdData($cacheKey) {
		$cacheKey=md5($cacheKey);
		
		$resultget = $this->getDi()->get("cacheAdData")->get($cacheKey);
			return $resultget?$resultget:false;
	}
	
	function setCacheAdData($key, $value, $time=MD_CACHE_TIME) {
		if(!MD_CACHE_ENABLE)
			return false;
		$cacheKey=md5($key);
		$this->getDi()->get("cacheAdData")->save($cacheKey, $value, $time);
	}
	
    function getCacheDataValue($cacheKey){
		if(!MD_CACHE_ENABLE)
			return false;
		
        $cacheKey=md5($cacheKey);

        $resultget = $this->getDi()->get("cacheData")->get($cacheKey);
        return $resultget?$resultget:false;
    }

    function saveCacheDataValue($cacheKey, $cacheValue){
    	if(!MD_CACHE_ENABLE)
    		return false;
    	
        $cacheKey=md5($cacheKey);

        $this->getDi()->get("cacheData")->save($cacheKey, $cacheValue);
    }

    function setGeo(&$request_settings) {
    	$ip = $request_settings['ip_address'];
    	$codes = $this->getCodeFromIp($ip);
    	$request_settings['province_code'] = $codes[0];
    	$request_settings['city_code'] = $codes[1];
    	$this->debugLog("[setGeo] code1->".$codes[0].", code2->".$codes[1]);
    }
    

    public function reporting_db_update(&$display_ad, &$request_settings, $publication_id,
                                        $zone_id, $campaign_id, $creative_id, $network_id,
                                        $add_request, $add_request_sec, $add_impression, $add_click){
        if (!is_numeric($publication_id)){$publication_id='';}
        if (!is_numeric($zone_id)){$zone_id='';}
        if (!is_numeric($campaign_id)){$campaign_id='';}
        if (!is_numeric($creative_id)){$creative_id='';}

        $current_timestamp = time();
        $reporting['ip'] = $request_settings['ip_address'];
        
        if(empty($request_settings['device_name'])){
        	$reporting['device_name'] = $request_settings['device_movement'];
        }else{
       		$reporting['device_name'] = $request_settings['device_name'];
        }
		$reporting['type'] = '1';
		$reporting['publication_id'] = $publication_id;
		$reporting['zone_id'] = $zone_id;
		$reporting['campaign_id'] = $campaign_id;
		
		$reporting['creative_id'] = $creative_id;
		$reporting['requests'] = $add_request;
		$reporting['impressions'] = $add_impression;
		$reporting['clicks'] = $add_click;
		$reporting['timestamp'] = $current_timestamp;
		
		$reporting['report_hash'] = md5(serialize($reporting));
		
		$queue = $this->getDi()->get('beanstalkReporting');
		$queue->put(serialize($reporting));
		
		$display_ad['rh'] = $reporting['report_hash'];
    }

    function track_request(&$request_settings, $zone_detail, &$display_ad, $impression){

        if (!isset($request_settings['active_campaign_type'])){$request_settings['active_campaign_type']='';}
        if($display_ad['add_impression']){
        	$impression = 1;
        }
        switch ($request_settings['active_campaign_type']){
            case 'normal':
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail->entry_id, $display_ad['campaign_id'], $display_ad['ad_id'], '', 1, 0, $impression, 0);
                break;

            case 'network':
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail->entry_id, $request_settings['active_campaign'], '', $request_settings['network_id'], 1, 0, $impression, 0);
                break;

            case 'backfill':
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail->entry_id, '', '', $request_settings['network_id'], 1, 0, $impression, 0);
                break;

            default:
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail->entry_id, '', '', '', 1, 0, $impression, 0);
                break;
        }

        if ($impression>0){
            /*Deduct Impression from Limit Card*/
            switch ($request_settings['active_campaign_type']){

                case 'normal':
                    $this->deduct_impression_num($display_ad['campaign_id'], $impression);
                    break;

                case 'network':
                    $this->deduct_impression_num($request_settings['active_campaign'], $impression);
                    break;

            }

        }

    }

    function deduct_impression_num($campaign_id,$number){
    	
        $sql="UPDATE md_campaign_limit SET total_amount_left = total_amount_left - :number WHERE campaign_id = :campaign_id AND total_amount_left>0";
    	
    	$cam = new CampaignLimit();
    	$connection = $cam->getWriteConnection();
    	$result = $connection->execute($sql, array(
    		"number"=>$number,
    		"campaign_id"=>$campaign_id
    	));

       	if($result==false) {
       		$this->logoDBError($cam);
       		return false;
       	}
       	$row = $connection->affectedRows();
       	return $row>0;

    }

    function logoDBError($result){
        if ($result) {
            foreach ($result->getMessages() as $message) {
                $this->getDi()->get('logger')->error($message->getMessage());
            }
        }
    }
    
    function getCodeFromIp($ip) {
    	$cities = array(
	    		'CN_01'=>'北京市',
	    		'CN_02'=>'天津市',
	    		'CN_09'=>'上海市',
	    		'CN_22'=>'重庆市',
	    		'CN_32'=>'香港',
	    		'CN_33'=>'澳门'
    	);
    	$regions = array(
    			'CN_05'=>'内蒙古',
    			'CN_20'=>'广西',
    			'CN_26'=>'西藏',
    			'CN_30'=>'宁夏',
    			'CN_31'=>'新疆'
    	);
    	$address = $this->getAddressFromIp($ip);
    	$this->debugLog("[getCodeFromIp] find address->".$address);
    	if(!empty($address)){
    		foreach($cities as $key=>$value) {
    			$pattern = "/^".$value."\.*/iu";
    			if(preg_match($pattern, $address))
    				return array($key, $key);
    		}
    		
    		foreach ($regions as $key=>$value) {
    			$pattern = "/^".$value."([\x{4e00}-\x{9fa5}]*)/iu";
    			if(preg_match($pattern, $address, $matchs)) {
    				if(!empty($matchs[1])) {
    					$code = $this->getCodeFromAddress($matchs[1]);
    					return array($key, $code);
    				}
    				return array($key);
    			}
    		}
    		
    		$pattern = "/([\x{4e00}-\x{9fa5}]+省)([\x{4e00}-\x{9fa5}]*)/iu";
    		if(preg_match($pattern, $address, $matchs)) {
    			$code1 = "";
    			$code2 = "";
    			if(!empty($matchs[1])) {
    				$code1 = $this->getCodeFromAddress($matchs[1]);
    			}
    			if(!empty($matchs[2])) {
    				$code2 = $this->getCodeFromAddress($matchs[2]);
    			}
    			$this->debugLog("match code:".$code1."--".$code2);
    			return array($code1, $code2);
    		}
    	}
    	return array();
    }

    function getCodeFromAddress($region_name) {
    	//$sql = "select * from md_regional_targeting where region_name= '".$region_name."'";
    	//$r = new Regions();
		//$region = $r->getReadConnection()->fetchOne($sql);
    	//if($region){
    	//	return $region['targeting_code'];
    	//}
    	$region = Regions::findFirst(array(
    		"conditions"=>"region_name= '".$region_name."'",
    		"cache"=>array("key"=>md5(CACHE_PREFIX."_REGIONS_".$region_name), "lifetime"=>MD_CACHE_TIME)
    	));
    	if($region){
    		return $region->targeting_code;
    	}
    	return "";
    }
    
    
    function getAddressFromIp($ip) {
    	$ip_origin = $ip;
    	$ip1num = 0;
    	$ip2num = 0;
    	$ipAddr1 = "";
    	$ipAddr2 = "";
    	$dat_path = __DIR__.'/../data/geotargeting/ip.dat';
    	if (! preg_match ( "/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $ip )) {
    		return 'IP Address Error';
    	}
    	if (! $fd = @fopen ( $dat_path, 'rb' )) {
    		return 'IP date file not exists or access denied';
    	}
    	$ip = explode ( '.', $ip );
    	$ipNum = $ip [0] * 16777216 + $ip [1] * 65536 + $ip [2] * 256 + $ip [3];
    	$DataBegin = fread ( $fd, 4 );
    	$DataEnd = fread ( $fd, 4 );
    	$ipbegin = implode ( '', unpack ( 'L', $DataBegin ) );
    	if ($ipbegin < 0)
    		$ipbegin += pow ( 2, 32 );
    	$ipend = implode ( '', unpack ( 'L', $DataEnd ) );
    	if ($ipend < 0)
    		$ipend += pow ( 2, 32 );
    	$ipAllNum = ($ipend - $ipbegin) / 7 + 1;
    	$BeginNum = 0;
    	$EndNum = $ipAllNum;
    	while ( $ip1num > $ipNum || $ip2num < $ipNum ) {
    		$Middle = intval ( ($EndNum + $BeginNum) / 2 );
    		fseek ( $fd, $ipbegin + 7 * $Middle );
    		$ipData1 = fread ( $fd, 4 );
    		if (strlen ( $ipData1 ) < 4) {
    			fclose ( $fd );
    			return 'System Error';
    		}
    		$ip1num = implode ( '', unpack ( 'L', $ipData1 ) );
    		if ($ip1num < 0)
    			$ip1num += pow ( 2, 32 );
    	
    		if ($ip1num > $ipNum) {
    			$EndNum = $Middle;
    			continue;
    		}
    		$DataSeek = fread ( $fd, 3 );
    		if (strlen ( $DataSeek ) < 3) {
    			fclose ( $fd );
    			return 'System Error';
    		}
    		$DataSeek = implode ( '', unpack ( 'L', $DataSeek . chr ( 0 ) ) );
    		fseek ( $fd, $DataSeek );
    		$ipData2 = fread ( $fd, 4 );
    		if (strlen ( $ipData2 ) < 4) {
    			fclose ( $fd );
    			return 'System Error';
    		}
    		$ip2num = implode ( '', unpack ( 'L', $ipData2 ) );
    		if ($ip2num < 0)
    			$ip2num += pow ( 2, 32 );
    		if ($ip2num < $ipNum) {
    			if ($Middle == $BeginNum) {
    				fclose ( $fd );
    				return 'Unknown';
    			}
    			$BeginNum = $Middle;
    		}
    	}
    	$ipFlag = fread ( $fd, 1 );
    	if ($ipFlag == chr ( 1 )) {
    		$ipSeek = fread ( $fd, 3 );
    		if (strlen ( $ipSeek ) < 3) {
    			fclose ( $fd );
    			return 'System Error';
    		}
    		$ipSeek = implode ( '', unpack ( 'L', $ipSeek . chr ( 0 ) ) );
    		fseek ( $fd, $ipSeek );
    		$ipFlag = fread ( $fd, 1 );
    	}
    	if ($ipFlag == chr ( 2 )) {
    		$AddrSeek = fread ( $fd, 3 );
    		if (strlen ( $AddrSeek ) < 3) {
    			fclose ( $fd );
    			return 'System Error';
    		}
    		$ipFlag = fread ( $fd, 1 );
    		if ($ipFlag == chr ( 2 )) {
    			$AddrSeek2 = fread ( $fd, 3 );
    			if (strlen ( $AddrSeek2 ) < 3) {
    				fclose ( $fd );
    				return 'System Error';
    			}
    			$AddrSeek2 = implode ( '', unpack ( 'L', $AddrSeek2 . chr ( 0 ) ) );
    			fseek ( $fd, $AddrSeek2 );
    		} else {
    			fseek ( $fd, - 1, SEEK_CUR );
    		}
    		while ( ($char = fread ( $fd, 1 )) != chr ( 0 ) )
    			$ipAddr2 .= $char;
    		$AddrSeek = implode ( '', unpack ( 'L', $AddrSeek . chr ( 0 ) ) );
    		fseek ( $fd, $AddrSeek );
    		while ( ($char = fread ( $fd, 1 )) != chr ( 0 ) )
    			$ipAddr1 .= $char;
    	} else {
    		fseek ( $fd, - 1, SEEK_CUR );
    		while ( ($char = fread ( $fd, 1 )) != chr ( 0 ) )
    			$ipAddr1 .= $char;
    		$ipFlag = fread ( $fd, 1 );
    		if ($ipFlag == chr ( 2 )) {
    			$AddrSeek2 = fread ( $fd, 3 );
    			if (strlen ( $AddrSeek2 ) < 3) {
    				fclose ( $fd );
    				return 'System Error';
    			}
    			$AddrSeek2 = implode ( '', unpack ( 'L', $AddrSeek2 . chr ( 0 ) ) );
    			fseek ( $fd, $AddrSeek2 );
    		} else {
    			fseek ( $fd, - 1, SEEK_CUR );
    		}
    		while ( ($char = fread ( $fd, 1 )) != chr ( 0 ) ) {
    			$ipAddr2 .= $char;
    		}
    	}
    	fclose ( $fd );
    	if (preg_match ( '/http/i', $ipAddr1 )) {
    		$ipAddr1 = '';
    	}
    	if (preg_match ( '/http/i', $ipAddr2 )) {
    		$ipAddr2 = '';
    	}
    	
    	$ipAddr1 = preg_replace ( '/CZ88.NET/is', '', $ipAddr1 );
    	$ipAddr1 = preg_replace ( '/^s*/is', '', $ipAddr1 );
    	$ipAddr1 = preg_replace ( '/s*$/is', '', $ipAddr1 );
    	$ipAddr1 = iconv("GBK","UTF-8//IGNORE",$ipAddr1);
    	
    	return $ipAddr1;
    }
    
    function codeSuccess() {
    	return array("return_code"=>"00000");
    }
    function codeInputError() {
    	return array("return_code"=>"30001");
    }
    function codeNoAds() {
    	return array("return_code"=>"20001");
    }
    function debugLog($log) {
    	if(DEBUG_LOG_ENABLE) {
    		$this->getDi()->get('debugLogger')->log($log);
    	}
    }
    
    function get_placement($placement_hash){
    	$zone = Zones::findFirst(array(
    			"zone_hash = ?0",
    			"bind" => array(0=>$placement_hash),
    			"cache" => array("key"=>CACHE_PREFIX."_ZONES_".$placement_hash, "lifetime"=>MD_CACHE_TIME)
    	));
    	return $zone;
    }
    
    public function get_creative_url($adUnit, $type, $extension){
    	$server_detail=$this->get_creativeserver($adUnit->creativeserver_id);
    	$image_url="".$server_detail->server_default_url."".$adUnit->unit_hash.$type.".".$extension."";
    
    	return $image_url;
    }
    
    public function get_creativeserver($id){
    
    	//$query="select server_default_url from md_creative_servers where entry_id='".$id."'";
    	$creativeServers = CreativeServers::findFirst($id);
    
    	if ($creativeServers){
    		return $creativeServers;
    	} else {
    		return false;
    	}
    }

    function save_request_log($type, $result){
    	
    	//不记录device_log
    	if(!ENABLE_DEVICE_LOG)
    		return false;

//      $phql="INSERT INTO md_device_request_log (equipment_sn, equipment_key, device_id, device_name, user_pattern, date, operation_type, operation_extra, publication_id, zone_id, campaign_id, creative_id, client_ip) VALUES (:equipment_sn, :equipment_key, :device_id, :device_name, :user_pattern, :date, :operation_type, :operation_extra, :publication_id, :zone_id, :campaign_id, :creative_id, :client_ip)";

        $devReqLog = new DeviceRequestLog();

        $zone_detail = null;
        $operation_type = null;
        
        $devReqLog->date = date("Y-m-d H:i:s");
        $devReqLog->business_id = BUSINESS_ID;
        if($type=="monitor") {
        	$devReqLog->client_ip = $result["monitor_ip"];
        }else{
        	$devReqLog->client_ip = $this->request->getClientAddress(TRUE);
        }
         $this->debugLog("[save_request_log] client_ip:".$devReqLog->client_ip);
        if($type == 'request')
        {
            if(isset($result['available']) && $result['available']==1) {
                $operation_type = '002';
                $devReqLog->campaign_id = $result['campaign_id'];
                $devReqLog->creative_id = $result['ad_id'];
            }else {
                $operation_type = '001';
                $devReqLog->campaign_id = 0;
                $devReqLog->creative_id = 0;
            }

            $zone_hash = $this->request->get('s'); //此值已验证过
            $zone_detail = $this->get_placement($zone_hash);

            $devReqLog->equipment_sn = $result['equipment_sn'];
            $devReqLog->equipment_key = $result['equipment_key']; //mac address
            $devReqLog->device_name = $result['device_name'];
            $devReqLog->user_pattern = $result['up'];
            $devReqLog->operation_type = $operation_type;
            $devReqLog->operation_extra = '';
            $devReqLog->publication_id = $result['publication_id'];
            $devReqLog->zone_id = $result['zone_id'];
        }
        else if($type == 'track')
        {
            $devReqLog->equipment_sn = '';
            $devReqLog->equipment_key = $result['equipment_key'];
            $devReqLog->device_name = $result['device_name'];
            $devReqLog->user_pattern = '';
            $devReqLog->operation_type = '003';
            $devReqLog->operation_extra = '';
            $devReqLog->publication_id = $result['publication_id'];
            $devReqLog->zone_id = $result['zone_id'];
            $devReqLog->campaign_id = $result['campaign_id'];
            $devReqLog->creative_id = $result['creative_id'];
        }
        else if ($type == 'monitor') {
        	$operation_type = '003';
        	$devReqLog->equipment_sn = '';
        	$devReqLog->equipment_key = $result['equipment_key']; //mac address
        	$devReqLog->device_name = $result['device_name'];
        	$devReqLog->user_pattern = '';
        	$devReqLog->operation_type = $operation_type;
        	$devReqLog->operation_extra = $result['ex'];
        	$devReqLog->publication_id = $result['publication_id'];
        	$devReqLog->zone_id = $result['zone_id'];
        	$devReqLog->campaign_id = $result['campaign_id'];
        	$devReqLog->creative_id = $result['creative_id'];

        }
        else
            return false;
        
        if(MAD_USE_BEANSTALK) {
        	$log['equipment_sn'] = $devReqLog->equipment_sn;
        	$log['equipment_key'] = $devReqLog->equipment_key;
        	$log['device_name'] = $devReqLog->device_name;
        	$log['user_pattern'] = $devReqLog->user_pattern;
        	$log['date'] = $devReqLog->date;
        	$log['operation_type'] = $devReqLog->operation_type;
        	$log['operation_extra'] = $devReqLog->operation_extra;
        	$log['publication_id'] = $devReqLog->publication_id;
        	$log['zone_id'] = $devReqLog->zone_id;
        	$log['campaign_id'] = $devReqLog->campaign_id;
        	$log['creative_id'] = $devReqLog->creative_id;
        	$log['client_ip'] = $devReqLog->client_ip;
        	$log['business_id'] = $devReqLog->business_id;
        	try{
        		$queue = $this->getDi()->get('beanstalkRequestDeviceLog');
        		$queue->put(serialize($log));
        	}catch (Exception $e) {
        		$this->debugLog($e->getMessage());
        	}
        }else{
	        if ($devReqLog->save() == true) {
	            return true;
	        } else {
	            $this->logoDBError($devReqLog);
	            return false;
	        }
        }
    }
    
    protected function outputJson(array $data) {
    	$response = $this->response;
    	$response->setHeader("Content-Type", 'application/json;charset=UTF-8');
    	//$html = $this->view->render($template, $data);
    	$response->setJsonContent($data);
    	$response->send();
    	exit();
    }
    /**
     * 渲染xml数据并输出
     * @param unknown $template
     * @param unknown $data
     */
    protected function executeXml($template, $data) {
    	$html = $this->executeTemplate($template, $data);
    	$this->outputXml($html);
    }
    /**
     * 渲染模板
     * @param unknown $template
     */
    protected function executeTemplate($template, $data) {
    	return $this->view->render($template, $data);
    }
    /**
     * 输出xml数据
     * @param unknown $template
     * @param unknown $data
     */
    protected function outputXml($data) {
    	$response = $this->response;
    	$response->setContentType('text/xml;charset=UTF-8');
    	$response->setContent($data);
    	$response->send();
    	exit();
    }
    
    /**
     * In-Place, recursive conversion of array keys in snake_Case to camelCase
     * @param  array $snakeArray Array with snake_keys
     * @return  no return value, array is edited in place
     */
    protected function arrayKeysToSnake($snakeArray){
    	foreach($snakeArray as $k=>$v){
    		if (is_array($v)){
    			$v = $this->arrayKeysToSnake($v);
    		}
    		$snakeArray[$this->snakeToCamel($k)] = $v;
    		if($this->snakeToCamel($k) != $k){
    			unset($snakeArray[$k]);
    		}
    	}
    	return $snakeArray;
    }
    
    /**
     * Replaces underscores with spaces, uppercases the first letters of each word,
     * lowercases the very first letter, then strips the spaces
     * @param string $val String to be converted
     * @return string     Converted string
     */
    protected function snakeToCamel($val) {
    	return str_replace(' ', '', lcfirst(ucwords(str_replace('_', ' ', $val))));
    }
    
    
    /**
     * 判断ip是否进入黑名单
     * @param unknown $ip
     * @return boolean
     */
    function isIpBlocked($ip) {
    	$blockIpInstance = $this->di->get("BlockIp");
    	$key = CACHE_PREFIX."_IP_BLOCK_LIST";
    	$ip2long = bindec(decbin(ip2long($ip)));
    	
    	$cache = $this->getCacheAdData($key);
    	if($cache=="noData") {
    		return false;
    	}
    	if($cache) {
    		$list = $cache;
    	}else{
    		$list = $blockIpInstance->getIpBlockList();
    		if(!$list) {
    			$list = "noData";
    		}
    		$this->setCacheAdData($key, $list);
    	}
    	if(is_array($list)) {
	    	$keys = $blockIpInstance->getTwoBlocksKey($ip2long, array_keys($list));
	    	if($blockIpInstance->exist($ip2long, $list[$keys[0]]) || $blockIpInstance->exist($ip2long, $list[$keys[1]])) {
	    		return true;
	    	}
    	}
    	return false;
    }
}