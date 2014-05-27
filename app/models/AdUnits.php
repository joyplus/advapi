<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-11
 * Time: 上午11:20
 */

class AdUnits extends BaseModel
{
	public $adv_id;
	public $campaign_id;
	public $unit_hash;
	public $adv_type;
	public $adv_status;
	public $adv_click_url;
	public $adv_click_opentype;
	public $adv_chtml;
	public $adv_mraid;
	public $adv_bannerurl;
	public $adv_impression_tracking_url;
	public $adv_name;
	public $adv_clickthrough_type;
	public $adv_creative_extension;
	public $adv_creative_extension_2;
	public $adv_creative_extension_3;
	public $adv_height;
	public $adv_width;
	public $creativeserver_id;
	public $creative_unit_type;
	public $creative_weight;
	public $adv_start;
	public $adv_end;
	public $filehash_1;
	public $filehash_2;
	
    public function getSource()
    {
        return "md_ad_units";
    }

    public function initialize() {
    	$this->setReadConnectionService('dbSlave');
    	$this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
    }
    
}