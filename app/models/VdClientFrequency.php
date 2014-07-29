<?php
/**
 * Created by PhpStorm.
 * User: yangliu
 * Date: 14-6-25
 * Time: 下午4:26
 */

class VdClientFrequency extends BaseModel
{

    public $id;
    public $campaign_id;
    public $mac;
    public $impression;
    public $vd_date;


    public function getSource()
    {
        return "vd_client_frequency";
    }

    public function initialize() {
        $this->setReadConnectionService('dbSlave');
        $this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
    }
}