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
    
}