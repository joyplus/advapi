<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-10
 * Time: 下午5:32
 */

class Reporting extends \Phalcon\Mvc\Model
{

    public function initialize() {
        $this->useDynamicUpdate(true);
        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(array('time_stamp', 'network_id', 'total_cost', 'geo_region', 'geo_city','device_name','campaign_id','creative_id','report_hash'));

    }

    public function getSource() {
        return "md_reporting";
    }
}