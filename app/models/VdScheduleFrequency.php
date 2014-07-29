<?php
/**
 * Created by PhpStorm.
 * User: yangliu
 * Date: 14-6-25
 * Time: 下午4:26
 */

class VdScheduleFrequency extends BaseModel
{

    public $id;
    public $time_start;
    public $time_end;
    public $frequency;
    public $campaign_id;
    public $type;


    public function getSource()
    {
        return "vd_schedule_frequency";
    }

    public function initialize() {
        $this->setReadConnectionService('dbSlave');
        $this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
    }
}