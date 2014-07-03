<?php
/**
 * Created by PhpStorm.
 * User: yangliu
 * Date: 14-6-25
 * Time: 下午4:26
 */

class VdScheduleImpression extends BaseModel
{

    public $id;
    public $time;
    public $campaign_id;
    public $schedule_impression;
    public $actual_impression;


    public function getSource()
    {
        return "vd_schedule_impression";
    }

    public function initialize() {
        $this->setReadConnectionService('dbSlave');
        $this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
    }
}