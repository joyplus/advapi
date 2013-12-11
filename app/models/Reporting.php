<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-10
 * Time: 下午5:32
 */

class Reporting extends Base
{
	public $entry_id;
	public $type;
	public $time_stamp;
	public $date;
	public $day;
	public $month;
	public $year;
	public $publication_id;
	public $zone_id;
	public $campaign_id;
	public $creative_id;
	public $network_id;
	public $total_requests;
	public $total_requests_sec;
	public $totle_impressions;
	public $total_clicks;
	public $total_cost;
	public $device_name;
	public $hours;
	public $geo_region;
	public $geo_city;
	public $province_code;
	public $city_code;
	public $report_hash;
	
    public function initialize() {
    	$this->setReadConnectionService('dbSlave');
    	$this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(array('time_stamp', 'network_id', 'total_cost', 'geo_region', 'geo_city'));

    }

    public function getSource() {
        return "md_reporting";
    }
}