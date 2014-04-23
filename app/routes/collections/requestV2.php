<?php

return call_user_func(function($config){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('MDRequestV2Controller')->setLazy(true);

    $collection->setPrefix('/'.$config->application->mdrequestv2);
    
    $collection->get('/', 'get');

	return $collection;
}, $config);