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

		if(!is_array($recordsArray)){
			// This is bad.  Throw a 500.  Responses should always be arrays.
			throw new HTTPException(
				"An error occured while retrieving records.",
				500,
				array(
					'dev' => 'The records returned were malformed.',
					'internalCode' => 'RESP1000',
					'more' => ''
				)
			);
		}

		// No records returned, so return an empty array
		if(count($recordsArray) < 1){
			return array();
		}

		return array($recordsArray);

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

                //TODO: Unchecked MD functions
                $forwarded_ip = $this->check_forwarded_ip();

                if ($forwarded_ip){
                    $request_settings['ip_address']=$this->request->getClientAddress(TRUE);
                }
                else {
                    $request_settings['ip_address']=$this->request->getClientAddress(FALSE);
                }
        }

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

    function getCacheDataValue($cacheKey){

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
        $cacheKey=md5($cacheKey);

        $this->getDi()->get("cacheData")->save($cacheKey, $cacheValue);
    }

    function set_geo(&$request_settings){

        $ip_address = $request_settings['ip_address'];
        $key='GEODATA_'.$ip_address.'';


        $cache_result=$this->getCacheDataValue($key);

        if ($cache_result){
            $request_settings['geo_country']=$cache_result['geo_country'];
            $request_settings['geo_region']=$cache_result['geo_region'];
            return true;
        }


        switch (MAD_MAXMIND_TYPE){
            case 'PHPSOURCE':

                // This code demonstrates how to lookup the country, region, city,
                // postal code, latitude, and longitude by IP Address.
                // It is designed to work with GeoIP/GeoLite City

                // Note that you must download the New Format of GeoIP City (GEO-133).
                // The old format (GEO-132) will not work.

                require_once( __DIR__ . "/../modules/maxmind_php/geoipcity.inc");
                require_once(__DIR__ . "/../modules/maxmind_php/geoipregionvars.php");

                // uncomment for Shared Memory support
                // geoip_load_shared_mem("/usr/local/share/GeoIP/GeoIPCity.dat");
                // $gi = geoip_open("/usr/local/share/GeoIP/GeoIPCity.dat",GEOIP_SHARED_MEMORY);

                //var $maxmind_datafile = __DIR__ . '/../data/geotargeting/GeoLiteCity.dat');

                if (!$gi = geoip_open(MAD_MAXMIND_DATAFILE_LOCATION,GEOIP_STANDARD)){
                    print_error(1, 'Could not open GEOIP Database supplied in constants.php File. Please make sure that the file is present and that the directory has the necessary rights applied.', $request_settings['sdk'], 1);
                    return false;
                }

                if (!$record = geoip_record_by_addr($gi,$ip_address)){
                    $request_settings['geo_country']='';
                    $request_settings['geo_region']='';
                    $request_settings['geo_city']='';

                    return false;
                }

                $geo_data=array();
                $geo_data['geo_country']=$record->country_code;
                $geo_data['geo_region']=$record->region;
                $geo_data['geo_city']=$record->city;

                geoip_close($gi);


                break;

            case 'NATIVE':

                if (!$record = geoip_record_by_name($ip_address)){
                    $request_settings['geo_country']='';
                    $request_settings['geo_region']='';
                    $request_settings['geo_city']='';
                    return false;
                }
                $geo_data['geo_country']=$record['country_code'];
                $geo_data['geo_region']=$record['region'];
                $geo_data['geo_city']=$record['city'];

                break;

        }

        $request_settings['geo_country']=$geo_data['geo_country'];
        $request_settings['geo_region']=$geo_data['geo_region'];
        $request_settings['geo_city']=$geo_data['geo_city'];

        $this->saveCacheDataValue($key, $geo_data);

        return true;

    }

    public function reporting_db_update(&$display_ad, &$request_settings, $publication_id,
                                        $zone_id, $campaign_id, $creative_id, $network_id,
                                        $add_request, $add_request_sec, $add_impression, $add_click){
        if (!is_numeric($publication_id)){$publication_id='';}
        if (!is_numeric($zone_id)){$zone_id='';}
        if (!is_numeric($campaign_id)){$campaign_id='';}
        if (!is_numeric($creative_id)){$creative_id='';}
        if (!is_numeric($network_id)){$network_id='';}

        if(is_null($request_settings['device_name']) || $request_settings['device_name'] ==''){
            $device_name='';
        }else {
            $device_name=$request_settings['device_name'];
        }

        if(is_null($request_settings['geo_region']) || $request_settings['geo_region'] ==''){
            $geo_region='';
        }else {
            $geo_region=$request_settings['geo_region'];
        }

        if(is_null($request_settings['geo_city']) || $request_settings['geo_city'] ==''){
            $geo_city='';
        }else {
            $geo_city=$request_settings['geo_city'];
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

        //With bound parameters
        $sql = "SELECT * FROM Reporting WHERE hours = :hours: AND publication_id = :publication_id: AND zone_id = :zone_id: AND campaign_id = :campaign_id: AND creative_id = :creative_id: AND date = :date: AND device_name = :device_name: ";
        //$sql = "SELECT * FROM Reporting WHERE hours = :hours: ";
        //$sql = "SELECT * FROM Reporting";
        $param = array(
            'hours' => $current_hours,
            'publication_id' => $publication_id,
            'zone_id' => $zone_id,
            'campaign_id' => $campaign_id,
            'creative_id' => $creative_id,
            'date' => $current_date,
            'device_name' => $device_name
        );


        if($geo_region!=''){
            $sql .= "AND geo_region = :geo_region: ";
            $param['geo_region'] = $geo_region;
        }

        if($geo_city!=''){
            $sql .= "AND geo_city = :geo_city: ";
            $param['geo_city'] = $geo_city;
        }

        if($network_id!=''){
            $sql .= "AND network_id = :network_id:";
            $param['network_id'] = $network_id;
        }

        $sql .= ' Limit 1';

        $query = $this->getDi()->get('modelsManager')->createQuery($sql);
        $resultSet = $query->execute($param);

        $reporting = $resultSet->getFirst();

        $add_impression=0;

        //TODO Moved to handler class
//        $base_ctr="".MAD_ADSERVING_PROTOCOL . MAD_SERVER_HOST . rtrim(dirname($_SERVER['PHP_SELF']), '/')."/".MAD_TRACK_HANDLER."?publication_id=".$publication_id."&zone_id=".$zone_id."&network_id=".$network_id."&campaign_id=".$campaign_id."&ad_id=".$creative_id."&h=".$request_settings['request_hash']."";
//        $display_ad['final_impression_url']=$base_ctr;


        if ($reporting){
            $reporting->total_requests = $reporting->total_requests + $add_request;
            $reporting->total_requests_sec = $reporting->total_requests_sec + $add_request_sec;
            $reporting->total_impressions = $reporting->total_impressions + $add_impression;
            $reporting->total_clicks = $reporting->total_clicks + $add_click;
            $reporting->total_impressions = $reporting->total_impressions + $add_impression;
            $result = $reporting->update();
        }
        else {
            $reporting = new Reporting();
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

        switch ($request_settings['active_campaign_type']){
            case 'normal':
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail->entry_id, $display_ad['campaign_id'], $display_ad['ad_id'], '', 1, 0, $impression, 0);
                break;

            case 'network':
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail['entry_id'], $request_settings['active_campaign'], '', $request_settings['network_id'], 1, 0, $impression, 0);
                break;

            case 'backfill':
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail['entry_id'], '', '', $request_settings['network_id'], 1, 0, $impression, 0);
                break;

            default:
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail['entry_id'], '', '', '', 1, 0, $impression, 0);
                break;
        }

        if ($impression>1){
            /*Deduct Impression from Limit Card*/
            switch ($request_settings['active_campaign_type']){

                case 'normal':
                    $this->deduct_impression_num($display_ad['campaign_id'], impression);
                    break;

                case 'network':
                    $this->deduct_impression_num($request_settings['active_campaign'], impression);
                    break;

            }

        }

    }

    function deduct_impression_num($campaign_id,$number){

        $sql="UPDATE md_campaign_limit SET total_amount_left = total_amount_left - :number WHERE campaign_id = :campaign_id AND total_amount>0";
    	
    	$cam = new CampaignLimit();
    	$result = $cam->getWriteConnection()->execute($sql, array(
    		"number"=>$number,
    		"campaign_id"=>$campaign_id
    	));

       	if($result==false) {
       		$this->logoDBError($cam);
       	}

    }

    function logoDBError($result){
        if ($result->success() == false) {
            foreach ($result->getMessages() as $message) {
                $this->getDi()->get('logger')->error($message->getMessage());
            }
        }
    }
}