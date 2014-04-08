<?php

class BlockIp extends BaseModel {
    public $id;
    public $ip_start;
    public $ip_end;
    public $create_time;

    public function getSource() {
        return "md_block_ip";
    }
    
    public function initialize() {
    	$this->setReadConnectionService('dbSlave');
    	$this->setWriteConnectionService('dbMaster');
    	$this->useDynamicUpdate(true);
    }
    
    
    public function getIpBlockList() {
    	$list = BlockIp::find(array(
    			"columns"=>"ip_start, ip_end",
    			"cache"=>array(
    					"key"=>CACHE_PREFIX."_IP_BLOCK_LIST",
    					"life_time"=>MD_CACHE_TIME
    			)
    	));
    	foreach ($list as $row) {
    		$rows[$row->ip_start] = array($row->ip_start, $row->ip_end);
    	}
    	asort($rows);
    	return count($rows)>0?$rows:false;
    }
    
    public  function exist($ip, $row) {
    	return $ip>=$row[0] && $ip<=$row[1];
    }
    
    public function getTwoBlocksKey($ip, $array) {
    	$count = count($array);
    	$min = 0;
    	$max = $count-1;
    	$active = intval($count / 2);
    	while($max-$min>1) {
    		if($ip==$array[$active])
    			return array($array[$active], $array[$active]);
    		if($ip < $array[$active]) {
    			$max = $active;
    		}else{
    			$min = $active;
    		}
    		$active = intval(($min+$max)/2);
    	}
    	return array($array[$min], $array[$max]);
    }

}

