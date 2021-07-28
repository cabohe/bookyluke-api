<?php
// TEMPORAL DEVELOPMENT CROSS ORIGIN PATCH
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With, Set-Cookie, Cookie, Bearer');	


require __DIR__ . "/inc/bootstrap.php";

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );

function kick_out(){
    header("HTTP/1.1 404 Not Found");
    exit();
}

// Si no llega endpoint, sacamos.
if (!isset($uri[1]) || !isset($uri[2])) {
    kick_out();
}

$endpoint = $uri[1].'/'.$uri[2];
$endpoints = ['clip/scrap','clip/scrap2'];
$open_endpoints = ['clip/scrap2'];

// Si no existe el endpoint, sacamos.
if( ! in_array( $endpoint , $endpoints ) ){
    kick_out();
}

// Check AUTH for endpoint
require PROJECT_ROOT_PATH . "/inc/Auth.php";

if( ! in_array( $endpoint , $open_endpoints ) ){
    // Need validation
    kick_out();
}


require PROJECT_ROOT_PATH . "/controller/Clip.php";
 
$objFeedController = new ClipController();
$strMethodName = $uri[2] . 'Action';
$objFeedController->{$strMethodName}();
?>