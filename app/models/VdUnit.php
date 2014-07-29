<?php
/**
 * Created by PhpStorm.
 * User: yangliu
 * Date: 14-6-25
 * Time: 下午4:26
 */

class VdUnit extends BaseModel
{

    public $adv_id;
    public $adv_name;
    public $adv_type;
    public $adv_width;
    public $adv_height;
    public $adv_owner;
    public $refresh_time;
    public $adv_status;
    public $last_modify_time;
    public $last_modify_user;
    public $campaign_id;
    public $unit_hash;
    public $adv_click_url;
    public $adv_impression_tracking_url;
    public $adv_impression_tracking_url_admaster;
    public $adv_impression_tracking_url_iresearch;
    public $adv_chtml;
    public $creative_time;
    public $adv_creative_url;
    public $adv_creative_url_2;
    public $adv_creative_url_3;
    public $creative_unit_type;
    public $creative_weight;
    public $adv_end;
    public $adv_start;
    public $custom_file_name;
    public $custom_file_name_2;
    public $custom_file_name_3;
    public $file_hash_1;
    public $file_hash_2;
    public $del_flg;

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