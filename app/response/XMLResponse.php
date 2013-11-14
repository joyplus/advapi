<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-12
 * Time: 下午1:13
 */

class XMLResponse extends Response{

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
        $etag = md5(serialize($records));
        $response->setContentType('text/xml');
        $response->setHeader('E-Tag', $etag);

        $response->setContent($this->print_ad($records));
        $response->send();

        return $this;
    }

    private function print_ad($display_ad){

        $response="<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        if ($display_ad['main_type']=='display'){

            switch ($display_ad['response_type']){

                case 'xml':
                    if ($display_ad['type']!='mraid-markup'){
                        $response.="<request type=\"textAd\">";
                    } else {
                        $response.= "<request type=\"mraidAd\">";
                    }
                    $response.= "<htmlString skipoverlaybutton=\"".$display_ad['skipoverlay']."\"><![CDATA[";
                    $response.= $display_ad['final_markup'];
                    $response.= "]]></htmlString>";
                    $response.= "<clicktype>";
                    $response.= "".$display_ad['clicktype']."";
                    $response.= "</clicktype>";
                    $response.= "<clickurl><![CDATA[";
                    $response.= "".$display_ad['final_click_url']."";
                    $response.= "]]></clickurl>";
                    $response.= "<impressionurl><![CDATA[";
                    $response.= "".$display_ad['final_impression_url']."";
                    $response.= "]]></impressionurl>";
                    if(!is_null($display_ad['interstitial-creative_res_url']) && $display_ad['interstitial-creative_res_url']!=''){
                        $response.= '<creative_res_url src="'.$display_ad['interstitial-creative_res_url'].'"></creative_res_url>';
                    }
                    $response.= "<trackingurl><![CDATA[";
                    $response.= "".$display_ad['trackingpixel']."";
                    $response.= "]]></trackingurl>";
                    $response.= "<urltype>";
                    $response.= "link";
                    $response.= "</urltype>";
                    $response.= "<refresh>";
                    $response.= "".$display_ad['refresh']."";
                    $response.= "</refresh>";
                    $response.= "<scale>";
                    $response.= "no";
                    $response.= "</scale>";
                    $response.= "<skippreflight>";
                    $response.= "".$display_ad['skippreflight']."";
                    $response.= "</skippreflight>";
                    $response.= "</request>";
                    break;

                case 'html':
                    $response.= $display_ad['final_markup'];
                    break;
            }

        }
        else if ($display_ad['main_type']=='interstitial'){
            $response.= '<ad type="'.$this->convert_interstitial_name($display_ad['type']).'" animation="'.$display_ad['animation'].'">';

            if ($display_ad['type']=='interstitial' or $display_ad['type']=='video-interstitial' or $display_ad['type']=='interstitial-video'){
                if ($display_ad['interstitial-type']=='markup'){$interstitial_urlcontent=''; } else {$interstitial_urlcontent='url="'.htmlspecialchars($display_ad['interstitial-content']).'"';}

                $response.= '<interstitial preload="'.$display_ad['interstitial-preload'].'" autoclose="'.$display_ad['interstitial-autoclose'].'" type="'.$display_ad['interstitial-type'].'" '.$interstitial_urlcontent.' orientation="'.$display_ad['interstitial-orientation'].'">';
                if ($display_ad['interstitial-type']=='markup'){
                    $response.= '<markup><![CDATA['.$display_ad['interstitial-content'].']]></markup>';
                }
                if(!is_null($display_ad['interstitial-creative_res_url']) && $display_ad['interstitial-creative_res_url']!=''){
                    $response.= '<creative_res_url src="'.$display_ad['interstitial-creative_res_url'].'"></creative_res_url>';
                }
                $response.= "<impressionurl><![CDATA[";
                $response.= "".$display_ad['final_impression_url']."";
                $response.= "]]></impressionurl>";
                $response.= "<trackingurl><![CDATA[";
                $response.= "".$display_ad['trackingpixel']."";
                $response.= "]]></trackingurl>";
                $response.= '<skipbutton show="'.$display_ad['interstitial-skipbutton-show'].'" showafter="'.$display_ad['interstitial-skipbutton-showafter'].'"></skipbutton>';
                $response.= '<navigation show="'.$display_ad['interstitial-navigation-show'].'">';
                $response.= '<topbar custombackgroundurl="'.$display_ad['interstitial-navigation-topbar-custombg'].'" show="'.$display_ad['interstitial-navigation-topbar-show'].'" title="'.$display_ad['interstitial-navigation-topbar-titletype'].'" titlecontent="'.$display_ad['interstitial-navigation-topbar-titlecontent'].'"></topbar>';
                $response.= '<bottombar custombackgroundurl="'.$display_ad['interstitial-navigation-bottombar-custombg'].'" show="'.$display_ad['interstitial-navigation-bottombar-show'].'" backbutton="'.$display_ad['interstitial-navigation-bottombar-backbutton'].'" forwardbutton="'.$display_ad['interstitial-navigation-bottombar-forwardbutton'].'" reloadbutton="'.$display_ad['interstitial-navigation-bottombar-reloadbutton'].'" externalbutton="'.$display_ad['interstitial-navigation-bottombar-externalbutton'].'" timer="'.$display_ad['interstitial-navigation-bottombar-timer'].'">';
                $response.= '</bottombar>';
                $response.= '</navigation>';
                $response.= '</interstitial>';
            }

            if ($display_ad['type']=='video' or $display_ad['type']=='video-interstitial' or $display_ad['type']=='interstitial-video'){

                $response.= '<video orientation="'.$display_ad['video-orientation'].'" expiration="'.$display_ad['video-expiration'].'">';
                $response.= '<creative display="'.$display_ad['video-creative-display'].'" delivery="'.$display_ad['video-creative-delivery'].'" type="'.$display_ad['video-creative-type'].'" bitrate='.$display_ad['video-creative-bitrate'].'"" width="'.$display_ad['video-creative-width'].'" height="'.$display_ad['video-creative-height'].'"><![CDATA['.$display_ad['video-creative-url'].']]></creative>';
                $response.= "<impressionurl><![CDATA[";
                $response.= "".$display_ad['final_impression_url']."";
                $response.= "]]></impressionurl>";
                if(!is_null($display_ad['interstitial-creative_res_url']) && $display_ad['interstitial-creative_res_url']!=''){
                    $response.= '<creative_res_url src="'.$display_ad['interstitial-creative_res_url'].'"></creative_res_url>';
                }
                $response.= "<trackingurl><![CDATA[";
                $response.= "".$display_ad['trackingpixel']."";
                $response.= "]]></trackingurl>";
                $response.= '<duration>'.$display_ad['video-duration'].'</duration>';
                $response.= '<skipbutton show="'.$display_ad['video-skipbutton-show'].'" showafter="'.$display_ad['video-skipbutton-showafter'].'"></skipbutton>';
                $response.= '<navigation show="'.$display_ad['video-navigation-show'].'" allowtap="'.$display_ad['video-navigation-allowtap'].'">';
                $response.= '<topbar custombackgroundurl="'.$display_ad['video-navigation-topbar-custombg'].'" show="'.$display_ad['video-navigation-topbar-show'].'"></topbar>';
                $response.= '<bottombar custombackgroundurl="'.$display_ad['video-navigation-bottombar-custombg'].'" show="'.$display_ad['video-navigation-bottombar-show'].'" pausebutton="'.$display_ad['video-navigation-bottombar-pausebutton'].'" replaybutton="'.$display_ad['video-navigation-bottombar-replaybutton'].'" timer="'.$display_ad['video-navigation-bottombar-timer'].'">';
                $response.= '</bottombar>';
                $response.= '</navigation>';
                $response.= '<trackingevents>';
                foreach ($display_ad['video-trackers'] as $tracker){
                    $response.= '<tracker type="'.$tracker[0].'"><![CDATA['.$tracker[1].']]></tracker>';
                }

                $response.= '</trackingevents>';
                if ($display_ad['video-htmloverlay-show']==1){
                    if ($display_ad['video-htmloverlay-type']=='markup'){$htmloverlay_urlcontent=''; } else {$htmloverlay_urlcontent='url="'.htmlspecialchars($display_ad['video-htmloverlay-content']).'"';}
                    $response.= '<htmloverlay show="'.$display_ad['video-htmloverlay-show'].'" showafter="'.$display_ad['video-htmloverlay-showafter'].'" type="'.$display_ad['video-htmloverlay-type'].'" '.$htmloverlay_urlcontent.'>';
                    if ($display_ad['video-htmloverlay-type']=='markup'){
                        $response.= '<![CDATA['.$display_ad['video-htmloverlay-content'].']]>';
                    }

                    $response.= '</htmloverlay>';
                }
                $response.= '</video>';


            }

            $response.= "</ad>";

        }

        return $response;
    }

    private function convert_interstitial_name($input){
        switch ($input){

            case 'interstitial':
                return 'interstitial';
                break;

            case 'video':
                return 'video';
                break;

            case 'interstitial-video':
                return 'interstitial-to-video';
                break;

            case 'video-interstitial':
                return 'video-to-interstitial';
                break;

        }
    }
}

