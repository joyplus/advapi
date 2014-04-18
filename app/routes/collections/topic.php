<?php

return call_user_func(function($config){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('MDTopicController')->setLazy(true);
    
    $collection->get('/'.$config->application->mdtopic, 'listTopic');
    
    $collection->get('/'.$config->application->mdtopicget, 'get');

	return $collection;
}, $config);