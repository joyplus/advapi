<?php
/**
 * Created by PhpStorm.
 * User: yangliu
 * Date: 14-6-25
 * Time: 下午4:26
 */

class VdZone extends BaseModel
{

    public $entry_id;
    public $tunnel_id;
    public $zone_name;
    public $zone_description;
    public $zone_hash;
    public $zone_width;
    public $zone_height;
    public $zone_type;
    public $count;
    public $publication_id;
    public $del_flg;

    public function getSource()
    {
        return "vd_zone";
    }

    public function initialize() {
        $this->setReadConnectionService('dbSlave');
        $this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
    }
}