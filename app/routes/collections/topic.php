<?php

return call_user_func(function(){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('MDTopicController')->setLazy(true);
    
    $collection->get('/'.MAD_TOPIC_LIST_HANDLER, 'listTopic');
    
    $collection->get('/'.MAD_TOPIC_GET_HANDLER, 'get');

	return $collection;
});