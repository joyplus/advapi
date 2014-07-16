<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-15
 * Time: 下午5:56
 */
class CampaignLimit extends BaseModel {
	public $entry_id;
	public $campaign_id;
	public $cap_type;
	public $total_amount;
	public $total_amount_left;
	public $last_refresh;
	public function getSource() {
		return "md_campaign_limit";
	}
	public function initialize() {
		$this->setReadConnectionService ( 'dbSlave' );
		$this->setWriteConnectionService ( 'dbMaster' );
		$this->useDynamicUpdate ( true );
		$this->skipAttributes ( array (
				'campaign_id',
				'cap_type',
				'total_amount',
				'last_refresh',
				'date',
				'hours' 
		) );
	}
	public static function findByCampaignId($id) {
		$limit = CampaignLimit::findFirst ( array (
				"conditions" => "campaign_id='$id'",
				"cache" => array (
						"key" => CACHE_PREFIX . "_CAMPAIGNLIMIT_CAMPAIGNID_" . $id 
				) 
		) );
		return $limit ? $limit : false;
	}
}