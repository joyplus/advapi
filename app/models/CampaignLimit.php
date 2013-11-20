<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-15
 * Time: 下午5:56
 */

class CampaignLimit extends \Phalcon\Mvc\Model
{
    public function getSource()
    {
        return "md_campaign_limit";
    }

    public function initialize() {
        $this->useDynamicUpdate(true);
        $this->skipAttributes(array('campaign_id', 'cap_type', 'total_amount', 'last_refresh', 'date', 'hours'));
    }
}