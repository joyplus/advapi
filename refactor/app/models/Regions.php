<?php
class Regions extends BaseModel {
	public $entry_id;
	public $targeting_code;
	public $targeting_type;
	public $region_code;
	public $region_name;
	public $head_country;
	public $head_region;
	public $head_city;
	public $entry_status;
	public $region_name_zh;
	public function getSource() {
		return "md_regional_targeting";
	}
	public function initialize() {
		$this->setReadConnectionService ( 'dbSlave' );
		$this->setWriteConnectionService ( 'dbMaster' );
		$this->useDynamicUpdate ( true );
	}
}