<?php

use Phalcon\Mvc\Model\Resultset\Simple as Resultset,
    Phalcon\DI,
    Phalcon\Cache\Backend\Memcache;

class MDAdmasterController extends RESTController{

    public function get(){
    	$this->executeXml("config/admaster", null);
    }
}