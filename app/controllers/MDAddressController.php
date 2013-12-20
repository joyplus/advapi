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

class MDAddressController extends RESTController{

    public function get(){
      $result = $this->handleAdRequest();
      return $this->respond($result);
    }

    private function handleAdRequest(){
        $request_settings = array();
        $request_data = array();
        $display_ad = array();
        $request_settings['ip_origin']='fetch';
        $this->prepare_ip($request_settings);
        $address = $this->getAddressFromIp($request_settings['ip_address']);
        $display_ad['code'] = $request_settings['ip_address']." ".$address;
        return $display_ad;
    }
}