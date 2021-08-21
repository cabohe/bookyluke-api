<?php
class BaseController
{
    function __construct($firebase_functions_endpoint,$user) {
        $this->firebase_functions_endpoint = $firebase_functions_endpoint;
        if( isset($user['id']) ){
            $this->user_id = $user['id'];
        }else{
            $this->user_id = null;
        }
        if( isset($user['role']) ){
            $this->user_role = $user['role'];
        }else{
            $this->user_role = null;
        }
        if( isset($user['token']) ){
            $this->user_token = $user['token'];
        }else{
            $this->user_token = null;
        }
        
    }
    /**
     * __call magic method.
     */
    public function __call($name, $arguments)
    {
        $this->sendOutput('', array('HTTP/1.1 404 Not Found'));
    }
 
    /**
     * Get URI elements.
     * 
     * @return array
     */
    protected function getUriSegments()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = explode( '/', $uri );
 
        return $uri;
    }
 
    /**
     * Get querystring params.
     * 
     * @return array
     */
    protected function getQueryStringParams()
    {
        parse_str($_SERVER['QUERY_STRING'], $query);
        return $query;
    }
 
    /**
     * Send API output.
     *
     * @param mixed  $data
     * @param string $httpHeader
     */
    protected function sendOutput($data, $httpHeaders=array())
    {
        header("Access-Control-Allow-Origin: *");
        header_remove('Set-Cookie');
        
        if (is_array($httpHeaders) && count($httpHeaders)) {
            foreach ($httpHeaders as $httpHeader) {
                header($httpHeader);
            }
        }
 
        echo $data;
        exit;
    }


    protected function queryFirestorePostFunctions($endpoint_url,$body){
         //open connection
        $ch = curl_init($endpoint_url);

        // Add auth
        $authorization = "Authorization: Bearer " . $this->user_token;

        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', $authorization ));

        //So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 

        $result = curl_exec($ch);
        if($result === false){
            $result = curl_error($ch);
        }

        curl_close($ch);

        return $result;
    }

    protected function queryFirestoreGetFunctions($endpoint_url){
         //open connection
        $ch = curl_init($endpoint_url);

        // Add auth
        $authorization = "Authorization: Bearer " . $this->user_token;

        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', $authorization ));

        //So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 

        $result = curl_exec($ch);
        if($result === false){
            $result = curl_error($ch);
        }

        curl_close($ch);

        return $result;
    }
       
}