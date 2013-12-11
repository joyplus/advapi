<?php
class Base extends \Phalcon\Mvc\Model {
	
	public function selectReadConnection($intermediate, $bindParams, $bindTypes) {
    	if(MD_SLAVE_NUM == 0)
    		return $this->getDi()->get('dbSlave');
    	
    	$time = time();
    	for($i=1; $i<=MD_SLAVE_NUM; $i++) {
    		$slaves[] = "dbSlave$i";
    	}
    	
    	if(($time & 1) && MD_SLAVE_NUM>1) {
    		return $this->getDi()->get($slaves[1]);
    	}else {
    		return $this->getDi()->get($slaves[0]);
    	}
    }
}