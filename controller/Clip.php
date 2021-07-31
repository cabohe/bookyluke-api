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
     */
    public function scrapAction()
    {
        $readability = new Readability(new Configuration());
        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        if (strtoupper($requestMethod) == 'GET') {
            try {
                
                if (isset($arrQueryStringParams['url']) && $arrQueryStringParams['url']) {
                    $clip = [];
                    $url = $arrQueryStringParams['url'];
                    $raw_article_html = file_get_contents($url);
                    try {
                        $readability->parse($raw_article_html);

                        $clip["url"] = $arrQueryStringParams['url'];
                        $clip["title"] = $readability->getTitle();
                        $clip["content"] = $readability->getContent();
                        $clip["image"] = $readability->getImage();
                        $clip["text_dir"] = $readability->getDirection();
                        $clip["path_info"] = $readability->getPathInfo($url);
                        $clip["sitename"] = $readability->getSiteName();

                    } catch (ParseException $e) {
                        echo sprintf('Error processing text: %s', $e->getMessage());
                    }
                }
 
                $responseData = json_encode($clip);
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