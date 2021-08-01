<?php

use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;
use andreskrey\Readability\ParseException;
// Firebase ADMIN SDK
use Kreait\Firebase\Factory;

class ClipController extends BaseController
{
    
    /**
     * "/clip/scrap" Endpoint - Scrap clip
     * TODO : Pass Bearer to FIREBASE
     */
    private function firestore_save($clip_object)
    {

        //The url you wish to send the POST request to
        $url = $this->firebase_functions_endpoint . '/clips';
        
        //url-ify the data for the POST
        $body = json_encode( $clip_object );

        $result = $this->queryFirestoreFunctions($url,$body);
        
        if ($result) {
            $responseData = $result;
        }else{
            $strErrorDesc = 'Error sending FIREBASE query - ' . curl_error($ch);
            $strErrorHeader = 'HTTP/1.1 200 OK'; 
        }
 
        // send output
        if (! isset( $strErrorDesc ) ) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(json_encode(array('error' => $strErrorDesc)), 
                array('Content-Type: application/json', $strErrorHeader)
            );
        }
    }

     /**
     * "/clip/scrap" Endpoint - Scrap clip
     */
    private function scrap($url)
    {
        $readability = new Readability(new Configuration());
        $clip = [];
        $raw_article_html = file_get_contents($url);
        if(!isset($url)){
            $resp["error"] = "url not set";
            return $resp;
        }
        try {
            $readability->parse($raw_article_html);

            $clip["url"] = $url;
            $clip["title"] = $readability->getTitle();
            $clip["content"] = $readability->getContent();
            $clip["image"] = $readability->getImage();
            $clip["text_dir"] = $readability->getDirection();
            $clip["path_info"] = $readability->getPathInfo($url);
            $clip["sitename"] = $readability->getSiteName();
            if( $this->user_id ){
                $clip["user_id"] = $this->user_id;
            }
            return $clip;
        } catch (ParseException $e) {
            $resp["error"] = "unable to scrap";
            return $resp;
        }
    }

    /**
     * "/clip/scrap-add" Endpoint - Scrap clip
     */
    public function scrap_and_saveAction()
    {

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        if (strtoupper($requestMethod) == 'GET') {
            try {
                
                if (isset($arrQueryStringParams['url']) && $arrQueryStringParams['url']) {
                    $scrap_result = $this -> scrap($arrQueryStringParams['url']);
                    if(!isset($scrap_result["error"])){
                        $responseData = json_encode($this -> firestore_save($scrap_result));
                    }else{
                        $responseData = json_encode('Error:' .$scrap_result["error"]);
                    }
                }
 
                
            } catch (Error $e) {
                $strErrorDesc = $e->getMessage().'Something went wrong! Please contact support.';
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method not supported';
            $strErrorHeader = 'HTTP/1.1 422 Unprocessable Entity';
        }
 
        // send output
        if (!$strErrorDesc) {
            $this->sendOutput(
                $responseData,
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(json_encode(array('error' => $strErrorDesc)), 
                array('Content-Type: application/json', $strErrorHeader)
            );
        }
    }


   
}