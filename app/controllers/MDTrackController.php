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

        $request_settings = array();
        $param_ds = $this->request->get("ds");
        if (isset($param_ds)){
            $request_settings['device_name']=$param_ds;
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
        $this->set_geo($request_settings);

        $zone_id = $this->request->get('zone_id');
        if (!is_numeric($zone_id)){
            return false;
        }

        $impression = $this->request->get('impression');
        if ($impression ==null || !is_numeric($impression)){
            $impression='1';
        }
        
    
		$this->reporting_db_update_impression($request_settings,
        		$this->request->get('publication_id'), 
        		$this->request->get('zone_id'), 
        		$this->request->get('campaign_id'), 
        		$this->request->get('ad_id'), 
        		$impression,
        		$this->request->get('network_id'));
        
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
        return array();
    }
    
    private function reporting_db_update_impression(&$request_settings, $publication_id, $zone_id, $campaign_id, $creative_id,  $add_impression, $network_id){
		if (!is_numeric($publication_id)){$publication_id='';}
		if (!is_numeric($zone_id)){$zone_id='';}
		if (!is_numeric($campaign_id)){$campaign_id='';}
		if (!is_numeric($creative_id)){$creative_id='';}
		if (!is_numeric($network_id)){$network_id='';}
				
		if(is_null($request_settings['device_name']) || $request_settings['device_name'] ==''){
			$device_name=' ';
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

//		$select_query="select entry_id from md_reporting where hours='".$current_hours."' AND geo_city='".$geo_city."' AND  network_id='".$network_id."'  AND  publication_id='".$publication_id."' AND zone_id='".$zone_id."' AND campaign_id='".$campaign_id."' AND creative_id='".$creative_id."' AND date='".$current_date."' AND device_name='".$device_name."' LIMIT 1";
		
// 	    $reporting = new Reporting();
// 	    $resultSet = new ResultSet(null, $reporting, $reporting->getReadConnection()->query($select_query));    
		
// 	    if($resultSet->valid()) {
// 	    	$repcard_detail = $resultSet->getFirst();
// 	    }
		$conditions = "";
		$conditions .= "hours='".$current_hours."'";
		$conditions .= " AND geo_city='".$geo_city."'";
		$conditions .= " AND network_id='".$network_id."'";
		$conditions .= " AND publication_id='".$publication_id."'";
		$conditions .= " AND zone_id='".$zone_id."'";
		$conditions .= " AND campaign_id='".$campaign_id."'";
		$conditions .= " AND creative_id='".$creative_id."'";
		$conditions .= " AND date='".$current_date."'";
		$conditions .= " AND device_name='".$device_name."'";
		$repcard_detail = Reporting::findFirst($conditions);
		/* if ($exec=mysql_query($select_query, $maindb))
		{
			//yay
			$repcard_detail = mysql_fetch_array($exec);
			//set_cache($select_query, $repcard_detail, 1500);
		}
		else
		{
			//return false;
		} */
	
	//	}
	    if($campaign_id !==''){
	       $this->deduct_impression_num($campaign_id, $add_impression);
	    }
	    
		if ($repcard_detail !=null && $repcard_detail->entry_id>0){ 
			//writetofile("request.track.report.log", " campaign_id=".$campaign_id." track sql : UPDATE md_reporting set  total_impressions=total_impressions+".$add_impression." WHERE entry_id='".$repcard_detail['entry_id']."'");
			//mysql_query("UPDATE md_reporting set  total_impressions=total_impressions+".$add_impression." WHERE entry_id='".$repcard_detail['entry_id']."'", $maindb);
			$sql = "UPDATE md_reporting SET total_impressions=total_impressions+ :add_impression WHERE entry_id= :entry_id";
			$reporting = new Reporting();
			$result = $reporting->getWriteConnection()->execute($sql,array(
				"add_impression"=>$add_impression,
				"entry_id"=>$repcard_detail->entry_id
			));
		}
		else { 
			//writetofile("request.track.report.log", '  campaign_id='.$campaign_id.' track sql : '."INSERT INTO md_reporting (network_id,geo_city,hours,geo_region,device_name,type, date, day, month, year, publication_id, zone_id, campaign_id, creative_id,  total_requests, total_requests_sec, total_impressions, total_clicks )  VALUES ('".$network_id."','".$geo_city."','".$current_hours."','".$geo_region."','".$device_name."', '1', '".$current_date."', '".$current_day."', '".$current_month."', '".$current_year."', '".$publication_id."', '".$zone_id."', '".$campaign_id."', '".$creative_id."',  '0', '0', '".$add_impression."', '0')");
			//mysql_query("INSERT INTO md_reporting (network_id,geo_city,hours,geo_region,
			//device_name,type, date, day, month, year, publication_id, zone_id, campaign_id, 
			//creative_id,  total_requests, total_requests_sec, total_impressions, total_clicks )  VALUES 
			//('".$network_id."','".$geo_city."','".$current_hours."','".$geo_region."','".$device_name
			//."', '1', '".$current_date."', '".$current_day."', '".$current_month."', '".$current_year
			//."', '".$publication_id."', '".$zone_id."', '".$campaign_id."', '".$creative_id."',  '0', '0', '"
			//.$add_impression."', '0')", $maindb);	
			$reporting = new Reporting();
			$reporting->network_id = $network_id;
			$reporting->geo_city = $geo_city;
			$reporting->hours = $current_hours;
			$reporting->geo_region = $geo_region;
			$reporting->device_name = $device_name;
			$reporting->type = 1;
			$reporting->date = $current_date;
			$reporting->day = $current_day;
			$reporting->month = $current_month;
			$reporting->year = $current_year;
			$reporting->publication_id = $publication_id;
			$reporting->zone_id = $zone_id;
			$reporting->campaign_id = $campaign_id;
			$reporting->creative_id = $creative_id;
			$reporting->total_requests = 0;
			$reporting->total_requests_sec = 0;
			$reporting->total_impressions = $add_impression;
			$reporting->total_clicks = 0;
			$reporting->report_hash = md5(serialize($reporting));
			$result = $reporting->create();
			if ($result == false) {
				foreach ($reporting->getMessages() as $message) {
					$this->getDi()->get('logger')->error($message->getMessage());
				}
			}
		}
	}

} 