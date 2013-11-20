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

        $this->reporting_db_update($display_ad, $request_settings, $zone_detail->publication_id, $zone_detail->entry_id, $display_ad['campaign_id'], $display_ad['ad_id'], '', 1, 0, $impression, 0);
//        reporting_db_update_impression($data['publication_id'], $data['zone_id'],
//            $data['campaign_id'], $data['ad_id'], $data['impression'], $data['network_id']);
    }

} 