<?php
class CampaignTargeting extends BaseModel {
	public $entry_id;
	public $campaign_id;
	public $targeting_type;
	public $targeting_code;
	public function getSource() {
		return "md_campaign_targeting";
	}
	public function initialize() {
		$this->setReadConnectionService ( 'dbSlave' );
		$this->setWriteConnectionService ( 'dbMaster' );
		$this->useDynamicUpdate ( true );
	}
}