<?php

return call_user_func(function($config){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('YZRequestController')->setLazy(true);

    $collection->setPrefix('/'.$config->application->yzrequest);
    
    //$collection->get('/', 'get');
    $collection->post('/', 'post');
	return $collection;
}, $config);