<?php
/**
 * Created by PhpStorm.
 * User: yangliu
 * Date: 14-6-25
 * Time: 下午4:26
 */

class VdUnit extends BaseModel
{

    public $id;
    public $creative_name;
    public $creative_type;
    public $creative_width;
    public $creative_height;
    public $creative_owner;
    public $refresh_time;
    public $status;
    public $last_modify_time;
    public $last_modify_user;
    public $campaign_id;
    public $hash;
    public $resource_url;

    public function getSource()
    {
        return "vd_unit";
    }

    public function initialize() {
        $this->setReadConnectionService('dbSlave');
        $this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
    }
}