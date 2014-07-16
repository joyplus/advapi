<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-5
 * Time: 上午11:45
 */
class Zones extends BaseModel {
	public $entry_id;
	public $publication_id;
	public $zone_hash;
	public $zone_name;
	public $zone_type;
	public $zone_width;
	public $zone_height;
	public $zone_refresh;
	public $zone_channel;
	public $zone_lastrequest;
	public $zone_description;
	public $mobfox_backfill_active;
	public $mobfox_min_cpc_active;
	public $min_cpc;
	public $min_cpm;
	public $backfill_alt_1;
	public $backfill_alt_2;
	public $backfill_alt_3;
	public function initialize() {
		$this->setReadConnectionService ( 'dbSlave' );
		$this->setWriteConnectionService ( 'dbMaster' );
		$this->useDynamicUpdate ( true );
	}
	public function getSource() {
		return "md_zones";
	}
	public static function findByHash($hash){
		$zone = Zones::findFirst(array(
				"zone_hash = :hash:",
				"bind" => array("hash"=>$hash),
				"cache" => array("key"=>CACHE_PREFIX."_ZONES_".$hash, "lifetime"=>CACHE_TIME)
		));
		return $zone;
	}
}