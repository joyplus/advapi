<?php

class Devices extends BaseModel
{
	public $device_id;
	public $device_type;
	public $device_name;
	public $device_movement;
	public $device_brands;
	public $device_quality;
	
    public function getSource()
    {
        return "md_devices";
    }

    public function initialize() {
        $this->setReadConnectionService('dbSlave');
        $this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
    }
    public static function findByName($name) {
    	if(!isset($name) || empty($name))
    		return false;
    	$d = Devices::findFirst(array(
    			"device_movement = '".$name."' OR device_name='".$name."'",
    			"cache"=>array("key"=>CACHE_PREFIX."_DEVICE_NAME_".$name)
    	));
    	if($d)
    		return $d;
    	return false;
    }
}