<?php
/**
 * Created by PhpStorm.
 * User: li
 * Date: 13-12-24
 * Time: 下午3:19
 */

class DeviceRequestLog extends BaseModel
{
    public $entry_id;
    public $equipment_sn;
    public $equipment_key;
    public $device_id;
    public $device_name;
    public $user_pattern;
    public $date;
    public $operation_type;
    public $operation_extra;
    public $publication_id;
    public $zone_id;
    public $campaign_id;
    public $creative_id;
    public $client_ip;
    public $province_code;
    public $city_code;
    public $business_id;

    public function getSource()
    {
        return "md_device_request_log";
    }

    public function initialize() {
        $this->setReadConnectionService('dbSlave');
        $this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
    }
}
