<?php

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDApplogController extends RESTController{

    public function get() {
    	$params['an'] = $this->request->get("an", null, '');
    	$params['apn'] = $this->request->get("apn", null, '');
    	$params['asti'] = $this->request->get("asti", null, '');
    	$params['acti'] = $this->request->get("acti", null, '');
    	$params['ds'] = $this->request->get("ds", null, '');
    	$params['sn'] = $this->request->get("sn", null, '');
    	$params['dt'] = $this->request->get("dt", null, '');
    	$params['up'] = $this->request->get("up", null, '');
    	$params['lp'] = $this->request->get("lp", null, '');
    	$params['dm'] = $this->request->get("dm", null, '');
    	$params['b'] = $this->request->get("b", null, '');
    	$params['ot'] = $this->request->get("ot", null, '');
    	$params['screen'] = $this->request->get("screen", null, '');
    	$params['mt'] = $this->request->get("mt", null, '');
    	$params['os'] = $this->request->get("os", null, '');
    	$params['osv'] = $this->request->get("osv", null, '');
    	$params['dss'] = $this->request->get("dss", null, '');
    	$params['dsr'] = $this->request->get("dsr", null, '');
    	$params['i'] = $this->request->get("i", null, '');
    	$params['timestamp'] = time();
    	foreach ($params as $key=>$value) {
    		$log.=$key."->".$value."\n";
    	}
    	$this->debugLog("[MDApplogController]".$log);
    	
    	$queue = $this->getDi()->get('beanstalk');
    	$queue->choose(TUBE_APPLOG);
    	$queue->put($params);
		return $result;
    }
}