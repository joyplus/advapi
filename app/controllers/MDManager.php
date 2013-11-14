<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-10
 * Time: 下午5:11
 */

class MDManager {

    public function reporting_db_update(&$display_ad, &$request_settings, $publication_id,
                                        $zone_id, $campaign_id, $creative_id, $network_id,
                                        $add_request, $add_request_sec, $add_impression, $add_click){
        if (!is_numeric($publication_id)){$publication_id='';}
        if (!is_numeric($zone_id)){$zone_id='';}
        if (!is_numeric($campaign_id)){$campaign_id='';}
        if (!is_numeric($creative_id)){$creative_id='';}
        if (!is_numeric($network_id)){$network_id='';}

        if(is_null($request_settings['device_name']) || $request_settings['device_name'] ==''){
            $device_name='';
        }else {
            $device_name=$request_settings['device_name'];
        }

        if(is_null($request_settings['geo_region']) || $request_settings['geo_region'] ==''){
            $geo_region='';
        }else {
            $geo_region=$request_settings['geo_region'];
        }

        if(is_null($request_settings['geo_city']) || $request_settings['geo_city'] ==''){
            $geo_city='';
        }else {
            $geo_city=$request_settings['geo_city'];
        }

        $current_date=date("Y-m-d");
        $current_day=date("d");
        $current_month=date("m");
        $current_hours=date('H');
        $current_year=date("Y");
        $current_timestamp=time();

        //$select_query="select entry_id from md_reporting where hours='".$current_hours."' AND geo_city='".$geo_city."' AND  publication_id='".$publication_id."' AND zone_id='".$zone_id."' AND campaign_id='".$campaign_id."' AND creative_id='".$creative_id."' AND network_id='".$network_id."' AND date='".$current_date."' AND device_name='".$device_name."' LIMIT 1";

        //global $repdb_connected,$display_ad;
//        $reporting = Reporting::findFirst(array(
//            "hours = '".$current_hours."'",
//            "geo_city = '".$geo_city."'",
//            "publication_id = '".$publication_id."'",
//            "zone_id = '".$zone_id."'",
//            "campaign_id = '".$campaign_id."'",
//            "creative_id = '".$creative_id."'",
//            "network_id = '".$network_id."'",
//            "date = '".$current_date."'",
//            "device_name = '".device_name."'"
//        ));

        $reporting = Reporting::findFirst(array(
            "conditions" => "hours = ?1 and publication_id = ?2 and zone_id = ?3 and campaign_id=?4 and creative_id=?5 and network_id=?6 and date=?7 and device_name=?8",
            "bind"       => array(1 =>$current_hours,
                                  2 =>$publication_id,
                                  //3 =>$geo_city,
                                 3 =>$zone_id,
                                4 =>$campaign_id,
                                5 =>$creative_id,
                                6 =>$network_id,
                                7 =>$current_date,
                                8 =>device_name

            )
        ));

        $add_impression=0;

        //TODO Moved to handler class
//        $base_ctr="".MAD_ADSERVING_PROTOCOL . MAD_SERVER_HOST . rtrim(dirname($_SERVER['PHP_SELF']), '/')."/".MAD_TRACK_HANDLER."?publication_id=".$publication_id."&zone_id=".$zone_id."&network_id=".$network_id."&campaign_id=".$campaign_id."&ad_id=".$creative_id."&h=".$request_settings['request_hash']."";
//        $display_ad['final_impression_url']=$base_ctr;


        if ($reporting){
            $reporting->total_requests = $reporting->total_requests + $add_request;
            $reporting->total_requests_sec = $reporting->total_requests_sec + $add_request_sec;
            $reporting->total_impressions = $reporting->total_impressions + $add_impression;
            $reporting->total_clicks = $reporting->total_clicks + $add_click;
            $reporting->update();
        }
        else {
            $reporting = new Reporting();
            $reporting->geo_city = $geo_city;
            $reporting->hours = $current_hours;
            $reporting->geo_region = $geo_region;
            $reporting->device_name = $device_name;
            $reporting->type = '1';
            $reporting->date = $current_date;
            $reporting->month = $current_date;
            $reporting->day = $current_day;
            $reporting->month = $current_month;
            $reporting->year = $current_year;
            $reporting->publication_id = $publication_id;
            $reporting->zone_id = $zone_id;
            $reporting->campaign_id = $campaign_id;

            $reporting->creative_id = $creative_id;
            $reporting->network_id = $network_id;
            $reporting->total_requests = $add_request;
            $reporting->total_requests_sec = $add_request_sec;
            $reporting->total_impressions = $add_impression;
            $reporting->total_clicks = $add_click;
            //$reporting->entry_id=1100023;


            $reporting->create();



        }
    }

    function track_request(&$request_settings, $zone_detail, &$display_ad, $impression){

        if (!isset($request_settings['active_campaign_type'])){$request_settings['active_campaign_type']='';}

        switch ($request_settings['active_campaign_type']){
            case 'normal':
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail->entry_id, $display_ad['campaign_id'], $display_ad['ad_id'], '', 1, 0, $impression, 0);
                break;

            case 'network':
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail['entry_id'], $request_settings['active_campaign'], '', $request_settings['network_id'], 1, 0, $impression, 0);
                break;

            case 'backfill':
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail['entry_id'], '', '', $request_settings['network_id'], 1, 0, $impression, 0);
                break;

            default:
                $this->reporting_db_update($display_ad, $request_settings,$zone_detail->publication_id, $zone_detail['entry_id'], '', '', '', 1, 0, $impression, 0);
                break;
        }

        if ($impression==1){
            /*Deduct Impression from Limit Card*/
            switch ($request_settings['active_campaign_type']){

                case 'normal':
                    $this->deduct_impression($display_ad['campaign_id']);
                    break;

                case 'network':
                    $this->deduct_impression($request_settings['active_campaign']);
                    break;

            }

        }

    }

    //TODO why it's commented in adv-server code.
    function deduct_impression($campaign_id){
        //global $maindb;
        //mysql_query("UPDATE md_campaign_limit set total_amount_left=total_amount_left-1 WHERE campaign_id='".$campaign_id."' AND total_amount>0", $maindb);
    }
} 