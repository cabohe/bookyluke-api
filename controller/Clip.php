<?php
include 'Ebook.php';

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
    private function firestore_save($clip_object){

        //The url you wish to send the POST request to
        $url = $this->firebase_functions_endpoint . '/clips/add';
        
        //url-ify the data for the POST
        $body = json_encode( $clip_object );

        $result = $this->queryFirestorePostFunctions($url,$body);

        if ($result) {
            $responseData = $result;
        }else{
            $strErrorDesc = 'Error sending FIREBASE query';
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

    private function load_clip($clip_id){

        //The url you wish to send the POST request to
        $url = $this->firebase_functions_endpoint . '/clips/get/'.$clip_id;
        
        $result = $this->queryFirestoreGetFunctions($url);

        if ($result) {
            return $result;
        }else{
            return false;
        }
 
    }

    private function img_rel_to_abs($img_url,$html_url){
        if( strpos( $img_url , 'http' ) === 0 || strpos( $img_url , 'data' ) === 0){
            return $img_url;
        }else{
            $down_levels = substr_count($img_url, '../');
            $new_img_path = str_replace("../", "", $img_url);

            $urlinfo = parse_url($html_url);
            $path=dirname($urlinfo["path"]);
            if( $down_levels == 0 ){
                $rel_path = dirname($path);
            }else{
                $rel_path = dirname($path,$down_levels);
            };
            $final_path = $urlinfo["scheme"].'://'.$urlinfo["host"].$rel_path;        

            $new_src= $final_path.$new_img_path;
            return $new_src;
        }
    }

    private function clean_html($content, $content_path=""){

        
        // Add alt to images
        $content = preg_replace('/(<img(?!.*?alt=([\'"]).*?\2)[^>]*?)(\/?>)/', '$1 alt="image"$3', $content );
        // Replace LAZY LOAD SRCs to SRC
        $content = preg_replace('/data-sf-src="([^\'"]*)"/', 'src="$1"', $content);
        $content = preg_replace('/data-lazy-src="([^\'"]*)"/', 'src="$1"', $content);

        // Removes SIZES ATTR
        $content = preg_replace('/data-lazy-sizes="([^\"])*"/', '', $content);
        $content = preg_replace('/sizes="([^\"])*"/', '', $content);
        // Removes SRCSET ATTR
        $content = preg_replace('/data-lazy-srcset="([^\"])*"/', '', $content);
        $content = preg_replace('/srcset="([^\"])*"/', '', $content);
        // Removes HEIGHT ATTR
        $content = preg_replace('/height="([^\"])*"/', '', $content);
        // Removes WIDTH ATTR
        $content = preg_replace('/width="([^\"])*"/', '', $content);

        // Si SRC empieza con DATA y existe data-lazy-src, los remplazamos.
        // TODOS LOS LAZY_LOAD SRC: preg_match_all('/data-lazy-src="([^\"]*)"/', $input_lines, $output_array);
        //  TODAS LOS TAG DE IMG: $img_tags = []; preg_match_all('/<img ([^\<])*/', $content, $img_tags);

        // Remove standard lazy load
        $content = preg_replace('/data-lazy-src="([^\"])*"/', '', $content);
        $content = preg_replace('/loading="lazy" /', '', $content);

        // Replace AMP images to images
        $content = preg_replace('/<amp-img/', '<img', $content);
        $content = preg_replace('/<\/amp-img>/', '</img>', $content); 
        
        // Check absolute images
        $images_src = [];
        preg_match_all('/src\s*=\s*"(.+?)"/', $content, $images_src);

        if(count($images_src) > 0){
            foreach($images_src[1] as $src){
                // If src is not HTTP, try to set absolute path
                $new_src = $this->img_rel_to_abs( $src ,$content_path[0] );
                $content = str_replace($src, $new_src, $content);
            }
        }
        // Remove unaccepted attrs
        $content = preg_replace('/rel="([^\'"]*)"/', '', $content);
        $content = preg_replace('/readabilityDataTable="([^\'"]*)"/', '', $content);
        // Remove data atributes
        $content = preg_replace('/data-[a-z0-9_-]*="[^\'"]*"/', '', $content);
        // Remove multiple spaces
        $content = preg_replace('/\s+/', ' ', $content);
        return $content;
    }
     /**
     * "/clip/scrap" Endpoint - Scrap clip
     */
    private function scrap($url){
        $readabilityOptions = [
            'FixRelativeURLs' => true,
            'CleanConditionally' => false

        ];
        $readability = new Readability(new Configuration($readabilityOptions));
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
            $clip["clean_content"] = $this->clean_html($readability->getContent() , $readability->getPathInfo($url));
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
     * "/clip/scrap_and_save" Endpoint - Scrap clip
     */
    public function scrap_and_saveAction(){
       
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

    /**
     * "/clip/download_epub" Endpoint - Download saved clip
     */
    public function download_epubAction(){

        $strErrorDesc = '';
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        if (strtoupper($requestMethod) == 'GET') {
            try {
                
                if (isset($arrQueryStringParams['clip_id']) && $arrQueryStringParams['clip_id']) {
                    $clip_result = $this->load_clip($arrQueryStringParams['clip_id']);
                    $responseData = $clip_result;
                    
                    if( $clip_result){
                        $myBook = new Ebook();
                        $clip = json_decode($clip_result);
                        $myBook->setTitle( addslashes( $clip->title ) );
                        $chapter1 = ['title' => addslashes( $clip->title ),'content' => $clip->clean_content ];
                        $myBook->addChapter($chapter1);
                        $myBook->download_file();
                        $responseData = true;
                    }else{
                        return false;
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

    public function testAction(){
        $strErrorDesc = '';
		$requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        if (strtoupper($requestMethod) == 'GET') {
            try {
                 
                if (isset($arrQueryStringParams['test']) && $arrQueryStringParams['test']) {
                    $test_val = $arrQueryStringParams['test'];
                }
 
                
            } catch (Error $e) {
                $strErrorDesc = $e->getMessage().'Something went wrong! Please contact support.';
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method not supported';
            $strErrorHeader = 'HTTP/1.1 422 Unprocessable Entity';
        }

		if (!$strErrorDesc) {
            $this->sendOutput(
                json_encode('This is a test: '.$test_val),
                array('Content-Type: application/json', 'HTTP/1.1 200 OK')
            );
        } else {
            $this->sendOutput(json_encode(array('error' => $strErrorDesc)), 
                array('Content-Type: application/json', $strErrorHeader)
            );
        }

    }

   
}