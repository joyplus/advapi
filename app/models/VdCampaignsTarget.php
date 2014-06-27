<?php
/**
 * Campaigns.php
 * User: monkey
 * Date: 14-1-10
 * Copyright 2014 Joyplus. All rights reserved.
 */

class VdCampaignsTarget extends Phalcon\Mvc\Model
{

    public function getSource()
    {
        return self::getDbTableName();
    }

    public static function getDbTableName()
    {
        return "vd_campaign_target";
    }
}
?>