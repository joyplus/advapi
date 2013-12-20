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
		if ($resultget){
			return $resultget;
		}
		else {
			return false;
		}
	}
    function getCacheDataValue($cacheKey){
		if(!MD_CACHE_ENABLE)
			return false;
		
        $cacheKey=md5($cacheKey);

        $resultget = $this->getDi()->get("cacheData")->get($cacheKey);
        if ($resultget){
            return $resultget;
        }
        else {
            return false;
        }
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
        if (!is_numeric($network_id)){$network_id='';}

        if(!isset($request_settings['device_name']) || $request_settings['device_name'] ==''){
            $device_name='';
        }else {
            $device_name=$request_settings['device_name'];
        }

        if(!isset($request_settings['geo_region']) || $request_settings['geo_region'] ==''){
            $geo_region='';
        }else {
            $geo_region=$request_settings['geo_region'];
        }

        if(!isset($request_settings['geo_city']) || $request_settings['geo_city'] ==''){
            $geo_city='';
        }else {
            $geo_city=$request_settings['geo_city'];
        }
        
        if(!isset($request_settings['province_code']) || $request_settings['province_code'] ==''){
        	$province_code='';
        }else {
        	$province_code=$request_settings['province_code'];
        }
        
        if(!isset($request_settings['city_code']) || $request_settings['city_code'] ==''){
        	$city_code='';
        }else {
        	$city_code=$request_settings['city_code'];
        }

        $current_date=date("Y-m-d");
        $current_day=date("d");
        $current_month=date("m");
        $current_hours=date('H');
        $current_year=date("Y");
        $current_timestamp=time();

        //$select_query="select entry_id from md_reporting where hours='".$current_hours."' AND geo_city='".$geo_city."' AND  publication_id='".$publication_id."' AND zone_id='".$zone_id."' AND campaign_id='".$campaign_id."' AND creative_id='".$creative_id."' AND network_id='".$network_id."' AND date='".$current_date."' AND device_name='".$device_name."' LIMIT 1";

        //global $repdb_connected,$display_ad;
//        $reporting = Reporting::findFirst(array(
//            "hours = '".$current_hours."'",
//            "geo_city = '".$geo_city."'",
//            "publication_id = '".$publication_id."'",
//            "zone_id = '".$zone_id."'",
//            "campaign_id = '".$campaign_id."'",
//            "creative_id = '".$creative_id."'",
//            "network_id = '".$network_id."'",
//            "date = '".$current_date."'",
//            "device_name = '".device_name."'"
//        ));

//        $reporting = Reporting::findFirst(array(
//            "conditions" => "hours = ?1 and publication_id = ?2 and zone_id = ?3 and campaign_id=?4 and creative_id=?5 and network_id=?6 and date=?7 and device_name=?8 and geo_city = ?9",
//            "bind"       => array(1 =>$current_hours,
//                                  2 =>$publication_id,
//                                 3 =>$zone_id,
//                                4 =>$campaign_id,
//                                5 =>$creative_id,
//                                6 =>$network_id,
//                                7 =>$current_date,
//                                8 =>device_name,
//                                9 =>$geo_city
//
//            )
//        ));

        $conditions = "hours = :hours: AND publication_id = :publication_id: AND zone_id = :zone_id: AND campaign_id = :campaign_id: AND creative_id = :creative_id: AND date = :date: AND device_name = :device_name: ";
        $param = array(
            'hours' => $current_hours,
            'publication_id' => $publication_id,
            'zone_id' => $zone_id,
            'campaign_id' => $campaign_id,
            'creative_id' => $creative_id,
            'date' => $current_date,
            'device_name' => $device_name
        );


        if($province_code!=''){
            $conditions .= "AND province_code = :province_code: ";
            $param['province_code'] = $province_code;
        }

        if($city_code!=''){
            $conditions .= "AND city_code = :city_code: ";
            $param['city_code'] = $city_code;
        }

        if($network_id!=''){
            $conditions .= "AND network_id = :network_id:";
            $param['network_id'] = $network_id;
        }


        $reporting = Reporting::findFirst(array(
        	"conditions"=>$conditions,
        	"bind"=>$param
        	//"cache"=>array("key"=>CACHE_PREFIX.md5(serialize($param)))
        ));

        //$reporting = $resultSet->getFirst();

        //TODO Moved to handler class
//        $base_ctr="".MAD_ADSERVING_PROTOCOL . MAD_SERVER_HOST . rtrim(dirname($_SERVER['PHP_SELF']), '/')."/".MAD_TRACK_HANDLER."?publication_id=".$publication_id."&zone_id=".$zone_id."&network_id=".$network_id."&campaign_id=".$campaign_id."&ad_id=".$creative_id."&h=".$request_settings['request_hash']."";
//        $display_ad['final_impression_url']=$base_ctr;


        if ($reporting){
//             $reporting->total_requests = $reporting->total_requests + $add_request;
//             $reporting->total_requests_sec = $reporting->total_requests_sec + $add_request_sec;
//             $reporting->total_impressions = $reporting->total_impressions + $add_impression;
//             $reporting->total_clicks = $reporting->total_clicks + $add_click;
//             $result = $reporting->update();
        	$sql = "UPDATE md_reporting SET total_impressions=total_impressions+ ".$add_impression." ,total_requests=total_requests+ ".$add_request." ,total_requests_sec=total_requests_sec+ ".$add_request_sec." , total_clicks=total_clicks+ ".$add_click." WHERE entry_id= '".$reporting->entry_id."'";
        	$result = $reporting->getWriteConnection()->execute($sql);
        	if(!$result) {
        		$this->logoDBError($reporting);
        	}
        }
        else {
            $reporting = new Reporting();
            $reporting->province_code = $province_code;
            $reporting->city_code = $city_code;
            $reporting->geo_city = $geo_city;
            $reporting->hours = $current_hours;
            $reporting->geo_region = $geo_region;
            $reporting->device_name = $device_name;
            $reporting->type = '1';
            $reporting->date = $current_date;
            $reporting->day = $current_day;
            $reporting->month = $current_month;
            $reporting->year = $current_year;
            $reporting->publication_id = $publication_id;
            $reporting->zone_id = $zone_id;
            $reporting->campaign_id = $campaign_id;

            $reporting->creative_id = $creative_id;
            $reporting->network_id = $network_id;
            $reporting->total_requests = $add_request;
            $reporting->total_requests_sec = $add_request_sec;
            $reporting->total_impressions = $add_impression;
            $reporting->total_clicks = $add_click;
            $reporting->report_hash = md5(serialize($reporting));


            $result = $reporting->create();
        }
        if ($result == false) {
			$this->logoDBError($reporting);
        }
        $display_ad['rh'] = $reporting->report_hash;
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
    			$this->getDi()->get('logger')->log("match code:".$code1."--".$code2);
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
    		"cache"=>array("key"=>md5(CACHE_PREFIX.$region_name),"lifetime"=>86400)
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
    	return array("code"=>"00000");
    }
    function codeInputError() {
    	return array("code"=>"30001");
    }
    function codeNoAds() {
    	return array("code"=>"20001");
    }
    function debugLog($log) {
    	if(DEBUG_LOG_ENABLE) {
    		$this->getDi()->get('debugLogger')->log($log);
    	}
    }
}