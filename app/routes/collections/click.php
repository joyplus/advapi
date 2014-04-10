<?php

return call_user_func(function($config){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('MDClickController')->setLazy(true);

    $collection->setPrefix('/'.$config->application->mdclick);
    
    $collection->get('/', 'get');

	return $collection;
}, $config);