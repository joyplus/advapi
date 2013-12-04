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
        return $result;
    }

    private function handleImpression(){

        $request_settings = array();
        $param_rh = $this->request->get("rh");
        if (!isset($param_rh)){
            return $this->codeInputError();
        }
        $param_rt = $this->request->get('rt');
        if (!isset($param_rt)){
            $param_rt='';
        }

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
                $request_settings['response_type']='xml';
                $request_settings['ip_origin']='fetch';
                break;

        }

        $this->prepare_ip($request_settings);
        $this->setGeo($request_settings);

        $impression='1';
        
        $report = Reporting::findFirst("report_hash='".$param_rh."'");
        if($report==null)
        	return $this->codeInputError();
    
		$this->reporting_db_update_impression($request_settings, $report, $impression);
        
        //TODO:check this code
        /* if ($data['o'] !=null || trim($data['o']) !=''){
        	$url = urldecode($data['o']);
        	require_once MAD_PATH . '/modules/http/class.http.php';
        	$http = new Http();
        	$http->execute($url);
        	if ($http->error){
        		writetofile('request.track.netword.error.log', $url);
        	}else {
        		//writetofile('request.track.netword.succ.log', $url);
        	}
        } */
        
        
        //TODO:MIAOZHEN
        /* if ($this->request->get('ad_id') !=null && is_numeric($this->request->get('ad_id'))){
        	$query="SELECT ad.adv_impression_tracking_url as tracking_url FROM md_ad_units AS ad, md_campaigns AS camp WHERE ad.campaign_id = camp.campaign_id and campaign_type='2' AND ad.adv_id ='".$data['ad_id']."'";
        	if ($ad_detail=simple_query_maindb($query, true, 1000)){
        		$tracking_url = $ad_detail['tracking_url'];
        		$ip=$request_settings['ip_address'];
        		$mac=$data['i'];
        		$tracking_url=explode("{ARRAY_ARRAY}", $tracking_url);
        		if(is_array($tracking_url)){
        			foreach ($tracking_url as $item){
        				if($item!=null && trim($item) !='' && strpos(trim($item), "http") !==false){
        					$url = track_thirdpart($ip,$mac,$data['h'],trim($item));
        					require_once MAD_PATH . '/modules/http/class.http.php';
        					$http = new Http();
        					$http->execute($url);
        					if ($http->error){
        						writetofile('request.track.netword.error.log', $url);
        					}else {
        						writetofile('request.track.netword.succ.log', $url);
        					}
        				}
        			}
        		}
        
        	}
        } */
        return $this->codeSuccess();
    }
    
    private function reporting_db_update_impression(&$request_settings, &$report, $add_impression){
		
	    if($report->campaign_id !==''){
	       $this->deduct_impression_num($report->campaign_id, $add_impression);
	    }
	    
		if ($report->entry_id>0){ 
			$sql = "UPDATE md_reporting SET total_impressions=total_impressions+ :add_impression WHERE entry_id= :entry_id";
			$reporting = new Reporting();
			$result = $reporting->getWriteConnection()->execute($sql,array(
				"add_impression"=>$add_impression,
				"entry_id"=>$report->entry_id
			));
			if(!$result) {
				$this->logoDBError($reporting);
			}
		}
	}

} 