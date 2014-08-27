<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-12
 * Time: 下午1:13
 */

class YZXMLResponse extends Response{

    protected $snake = true;
    protected $envelope = true;

    public function __construct(){
        parent::__construct();
    }

    public function send($records, $error=false){

        // Error's come from HTTPException.  This helps set the proper envelope data
        $response = $this->di->get('response');
        $success = ($error) ? 'ERROR' : 'SUCCESS';

        // If the query string 'envelope' is set to false, do not use the envelope.
        // Instead, return headers.
        $request = $this->di->get('request');
        //$etag = md5(serialize($records));
        $response->setContentType('text/xml;charset=UTF-8');
        //$response->setHeader('E-Tag', $etag);

        $response->setContent($this->print_ad($records));
        $response->send();

        return $this;
    }

    private function print_ad($display_ad){
        $response="<?xml version=\"1.0\" encoding=\"UTF-8\" ?>";
        $response.="<response method=\"syncad\"><attributes>";
        $response.="<interval_min>".TIME_YANGZHI_REQUEST."</interval_min>";
        $response.="<ads>";
        if(isset($display_ad['available']) && $display_ad['available']==1){
            $response.="<ad>";
            $response.="<adtype>".ZONE_YANGZHI_VIDEO_1280x720."</adtype>";
            $response.="<adcategory>".($display_ad['adv_type']+1)."</adcategory>";
            //$response.="<adid>".$display_ad['ad_hash']."</adid>";
            $response.="<adid>".$display_ad['ad_id']."</adid>";
            $response.="<adtext>".$display_ad['creative-url_3']."</adtext>";
            $response.="</ad>";
        }
        $response.="</ads>";
        $response.="</attributes></response>";

        return $response;
    }
}

