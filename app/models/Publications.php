<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-5
 * Time: 下午1:58
 */

class Publications extends \Phalcon\Mvc\Model
{
	public $inv_id;
	public $creator_id;
	public $inv_status;
	public $inv_type;
	public $inv_name;
	public $inv_description;
	public $inv_address;
	public $inv_defaultchannel;
	public $md_lastrequest;

    public function initialize() {
        $this->useDynamicUpdate(true);
    }

    public function getSource() {
        return "md_publications";
    }

}