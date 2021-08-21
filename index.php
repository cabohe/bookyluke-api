<?php

require __DIR__ . "/inc/bootstrap.php";
require(PROJECT_ROOT_PATH.'/vendor/autoload.php');

// Firebase ADMIN SDK
use Kreait\Firebase\Factory;

$firebase_functions_endpoint = 'http://localhost:5000/bookylucke/us-central1/app';


function kick_out(){
    header("HTTP/1.1 404 Not Found");
    exit();
}

// Parse and manage ENDPOINT
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );

// Si no llega endpoint, sacamos.
if (!isset($uri[1]) || !isset($uri[2])) {
    kick_out();
}

$endpoint = $uri[1].'/'.$uri[2];
$endpoints = ['clip/scrap_and_save','clip/download_epub','clip/test'];
$open_endpoints = ['clip/test'];
$user = [];
// Si no existe el endpoint, sacamos.
if( ! in_array( $endpoint , $endpoints ) ){
    kick_out();
}

// Si es un endpoint privado, necesitamos validacion
if( ! in_array( $endpoint , $open_endpoints ) ){
    // Need validation
    if( $_GET["token"] && !$_GET["token"]==""){
        $user['token'] = $_GET["token"];
        $factory = (new Factory)->withServiceAccount(PROJECT_ROOT_PATH.'/env/bookylucke-firebase-adminsdk-1g76v-671cb9e5f4.json');
        $auth = $factory->createAuth();
        // TEST Acceso PRO a bnnnmrtnz@gmail.com
        // $auth->setCustomUserClaims('ySHRQmu95pOi0raoXlvN7TzSyOL2', ['role' => 'PRO']);
        try {
            $verifiedIdToken = $auth->verifyIdToken($_GET["token"]);
            $uid = $verifiedIdToken->claims()->get('sub');
            $user['id'] = $uid;
            $claims = $auth->getUser($uid)->customClaims;
            if( isset($claims["role"]) && $claims["role"]=='PRO'){
                
                $user['role'] = 'PRO';
            }
        } catch (InvalidToken $e) {
            $error =[];
            $error["msg"] = 'The token is invalid';
            $error["error"] = $e->getMessage();
            echo json_encode($error);
            die();
        } catch (\InvalidArgumentException $e) {
            $error =[];
            $error["msg"] = 'The token could not be parsed';
            $error["error"] = $e->getMessage();
            echo json_encode($error);
            die();
        }
    }else{
        kick_out();
    }    
}


require PROJECT_ROOT_PATH . "/controller/Clip.php";

switch ($uri[1]) {
    case 'clip':
        $objFeedController = new ClipController($firebase_functions_endpoint,$user);
        break;
    
    default:
        kick_out();
        break;
}
$strMethodName = $uri[2] . 'Action';
$objFeedController->{$strMethodName}();
?>