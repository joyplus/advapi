<?php
/**
 * Created by PhpStorm.
 * User: yangliu
 * Date: 14-6-25
 * Time: 下午4:26
 */

class VdCampaignTemp extends BaseModel
{

    public $id;
    public $campaign_name;
    public $campaign_ower;
    public $campaign_priority;
    public $campaign_weights;
    public $campaign_start;
    public $campaign_end;
    public $time_create;
    public $total_amount;
    public $campaign_target;

    public function getSource()
    {
        return "vd_campaign_temp";
    }

    public function initialize() {
        $this->setReadConnectionService('dbSlave');
        $this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
    }
}