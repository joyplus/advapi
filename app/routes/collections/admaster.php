<?php

return call_user_func(function($config){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('MDAdmasterController')->setLazy(true);

    $collection->setPrefix('/'.$config->application->admaster);
    
    $collection->get('/', 'get');

	return $collection;
}, $config);