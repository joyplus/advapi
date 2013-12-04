<?php

class Devices extends \Phalcon\Mvc\Model
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
        $this->useDynamicUpdate(true);
    }
}