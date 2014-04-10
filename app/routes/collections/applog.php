<?php

return call_user_func(function($config){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('MDApplogController')->setLazy(true);

    $collection->setPrefix('/'.$config->application->mdapplog);
    
    $collection->get('/', 'get');

	return $collection;
}, $config);