<?php

return call_user_func(function($config){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('MDRequestV3Controller')->setLazy(true);

    $collection->setPrefix('/'.$config->application->mdrequestv3);
    
    $collection->get('/', 'get');

	return $collection;
}, $config);