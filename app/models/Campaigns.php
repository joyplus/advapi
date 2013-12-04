<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-4
 * Time: 下午1:05
 */

class Campaigns extends \Phalcon\Mvc\Model
{
	public $campaign_id;
	public $campaign_owner;
	public $campaign_status;
	public $campaign_type;
	public $campaign_name;
	public $campaign_desc;
	public $campaign_start;
	public $campaign_end;
	public $campaign_creationdate;
	public $campaign_networkid;
	public $campaign_priority;
	public $campaign_rate_type;
	public $campaign_rate;
	public $target_iphone;
	public $target_ipod;
	public $target_ipad;
	public $target_android;
	public $target_other;
	public $ios_version_min;
	public $ios_version_max;
	public $android_version_min;
	public $android_version_max;
	public $country_target;
	public $publication_target;
	public $channel_target;
	public $device_target;
	public $device_type_target;
	public $video_target;
	public $pattern_target;
	public $quality_target;
	public $brand_target;
	public $target_devices_desc;
	public $target_android_phone;
	public $creative_show_rule;
	public $belong_to_advertiser;
	public $campaign_display_way;
	public $total_amount;
	public $campaign_class;
	
    public function getSource()
    {
        return "md_campaigns";
    }

    public function initialize() {
        $this->useDynamicUpdate(true);
    }
}