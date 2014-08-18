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


        $response="<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        $response.="<response method=\"syncad\"><attributes>";
        if ($display_ad['main_type']=='interstitial'){
            $response.="<interval_min>1</interval_min>";
            $response.="<ads>";
            $response.="<adtype>".$display_ad['zone_hash']."</adtype>";
            $response.="<adcategory>".$display_ad['adv_type']."</adcategory>";
            $response.="<adid>".$display_ad['ad_hash']."</adid>";
            $response.="<adtext>".$display_ad['creative-url_3']."</adtext>";
            $response.="</ads>";
        }
        $response.="</attributes></response>";

        return $response;
    }
}

