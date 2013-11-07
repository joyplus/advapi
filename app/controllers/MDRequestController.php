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


    public function put(){

        $diTest = $this->getDi();

        //Get the service
        $modelsManagerService = $diTest->getService('modelsManager');
        //Resolve the service (return a Phalcon\Http\Request instance)
        $modelsManager = $modelsManagerService->resolve();

        $result = $this->modelsManager->executeQuery("SELECT * FROM Campaigns");
        $test = 'sss';

    }
    public function post(){

        $request_settings = array();
        $request_data = array();

        $request_data = $this->dispatcher->getParams();



        // A raw SQL statement
        $sql = "select md_campaigns.creative_show_rule as showRule, md_campaigns.campaign_id as campaignId, md_campaigns.campaign_priority as campaignPriority, md_campaigns.campaign_type campaignType, md_campaigns.campaign_networkid as campaignNetworkid from md_campaigns LEFT JOIN md_campaign_targeting c1 ON md_campaigns.campaign_id = c1.campaign_id LEFT JOIN md_campaign_targeting c2 ON md_campaigns.campaign_id = c2.campaign_id LEFT JOIN md_campaign_targeting c3 ON md_campaigns.campaign_id = c3.campaign_id LEFT JOIN md_ad_units ON md_campaigns.campaign_id = md_ad_units.campaign_id LEFT JOIN md_campaign_limit ON md_campaigns.campaign_id = md_campaign_limit.campaign_id where (md_campaigns.country_target=1 OR (c1.targeting_type='geo' AND (c1.targeting_code='CN' OR c1.targeting_code='CN_23'))) AND (md_campaigns.channel_target=1 OR (c2.targeting_type='channel' AND c2.targeting_code='4')) AND (md_campaigns.publication_target=1 OR (c3.targeting_type='placement' AND c3.targeting_code='85')) AND md_campaigns.campaign_status=1 AND md_campaigns.campaign_start<='2013-09-25' AND md_campaigns.campaign_end>='2013-09-25' AND (md_campaigns.device_target=1 OR md_campaigns.target_android=1 OR md_campaigns.target_android_phone=1)  AND (md_campaigns.device_target=1 OR md_campaigns.target_devices_desc is null OR md_campaigns.target_devices_desc ='' OR md_campaigns.target_devices_desc like '%ShowKey%')  AND ( md_campaigns.device_target=1 OR ((md_campaigns.android_version_min<=4.1 OR md_campaigns.android_version_min='') AND (md_campaigns.android_version_max>=4.1 OR md_campaigns.android_version_max=''))) AND (md_campaigns.campaign_type='network' OR (md_ad_units.adv_start<='2013-09-25' AND md_ad_units.adv_end>='2013-09-25' and  md_ad_units.adv_status=1 AND md_ad_units.creative_unit_type='interstitial')) AND (md_campaign_limit.total_amount_left='' OR md_campaign_limit.total_amount_left>=1) group by md_campaigns.campaign_id";

        // Base model
        $campaigns = new Campaigns();

        // Execute the query
        $result = new Resultset(null, $campaigns, $campaigns->getReadConnection()->query($sql));

        $data = array();
        foreach ($result as $item) {
            $data[] = array(
                'showRule' => $item->showRule,
                'campaignId' => $item->campaignId
            );
        }

        return $this->respond($data);
    }

    public function get(){
      $result = $this->handleAdRequest();
      return $this->respond($result);
    }

    function handleAdRequest(){
        $request_settings = array();
        $request_data = array();
        $errormessage = '';

        $this->prepare_r_hash($request_settings);

        $param_rt = $this->request->get('rt');
        if (!isset($param_rt)){
            $param_rt='';
        }

        $request_settings['referer'] = $this->request->get("p", null, '');
        $request_settings['device_name'] = $this->request->get("ds", null, '');
        $request_settings['longitude'] = $this->request->get("longitude", null, '');
        $request_settings['latitude'] = $this->request->get("latitude", null, '');
        $request_settings['iphone_osversion'] = $this->request->get("iphone_osversion", null, '');

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

        //Unchecked MD Functions
//        if (MAD_MAINTENANCE  ){
//            noad();
//        }

        if (!$this->check_input($request_settings, $errormessage)){
            //global $errormessage;
            //print_error(1, $errormessage, $request_settings['sdk'], 1);
            //Unchecked MD Functions
            $result = array();
            $result[0] = $errormessage;
            return $result;
        }

        $request_data['ip']=$request_settings['ip_address'];

        $zone_detail=$this->get_placement($request_settings, $errormessage);

        if (!$zone_detail){
            $result = array();
            $result[0] = $errormessage;
            return $result;
        }

        $request_settings['adspace_width']=$zone_detail->zone_width;
        $request_settings['adspace_height']=$zone_detail->zone_height;

        $request_settings['channel']=$this->getchannel($zone_detail);

        $this->update_last_request($zone_detail);

        //Unchecked MD Functions
        //set_geo();

        //Final test need be removed
        $test_response = array();
        $test_response[0] = $request_data;
        $test_response[1] = $request_settings;
        $test_response[2] = $zone_detail;

        return $test_response;
    }

    public function respond($results){
//        if($this->isPartial){
//            $newResults = array();
//            $remove = array_diff(array_keys($this->exampleRecords[0]), $this->partialFields);
//            foreach($results as $record){
//                $newResults[] = $this->array_remove_keys($record, $remove);
//            }
//            $results = $newResults;
//        }
//        if($this->offset){
//            $results = array_slice($results, $this->offset);
//        }
//        if($this->limit){
//            $results = array_slice($results, 0, $this->limit);
//        }
        return $results;
    }

    /**
     * MDRequest Functions
     */

    function prepare_r_hash(&$request_settings){

        $request_settings["request_hash"]=md5(uniqid(microtime()));

    }

    function is_valid_ip($ip, $include_priv_res = true)
    {
        return $include_priv_res ?
            filter_var($ip, FILTER_VALIDATE_IP) !== false :
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }


    function check_forwarded_ip(){

        if ($this->request->has("h[X-Forwarded-For]")){
            $res_array = explode(",", $this->request->get("h[X-Forwarded-For]"));
            return $res_array[0];
        }

        //Unchecked MD Functions
        $server_xforworded = $this->request->getServer('HTTP_X_FORWARDED_FOR');
        if (isset($server_xforworded) && !empty($server_xforworded)){
            $res_array = explode(",", $server_xforworded);
            return $res_array[0];
        }
        return false;
    }

    function prepare_ip(&$request_settings){

        switch ($request_settings['ip_origin']){
            case 'request':
                if ($this->request->has('ip')){
                    $request_settings['ip_address']=$this->request->get('ip');
                }
                break;

            case 'fetch':

                //Unchecked MD functions
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


    function prepare_ua(&$request_settings){

        if ($this->request->has('h[User-Agent]')){
            $request_settings['user_agent']=$this->request->get('h[User-Agent]', null, '');
        }
        else if ($this->request->has('u')){
            $request_settings['user_agent']=$this->request->get('u');
        }


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

        $sql="SELECT entry_id, publication_id, zone_type, zone_width, zone_height, zone_refresh, zone_channel, zone_lastrequest, mobfox_backfill_active, mobfox_min_cpc_active, min_cpc, min_cpm, backfill_alt_1, backfill_alt_2, backfill_alt_3 FROM md_zones WHERE zone_hash='".$request_settings['placement_hash']."'";

        $zones = new Zones();

        // Execute the query
        $resultSet = new Resultset(null, $zones, $zones->getReadConnection()->query($sql));

        if ($resultSet->valid()){
            return $resultSet->getFirst();
        }
        else {
            $errormessage='Placement not found. Please check your Placement Hash (Variable "s")';
            return false;
        }

    }


    function get_publication_channel($publication_id){

        $publications = Publications::findFirst($publication_id);
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

    function getCacheData(){
        $cacheData = $this->getDi()->get("cacheData");
        //$cache = $cacheService->resolve();
        return $cacheData;
    }

    function getCacheDataValue($cacheKey){
        return $this->getDi()->get("cacheData")->get($cacheKey);
    }

    function saveCacheDataValue($cacheKey, $cacheValue){

        $this->getDi()->get("cacheData")->save($cacheKey, $cacheValue);
    }
}