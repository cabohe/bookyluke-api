<?php
require __DIR__ . "/inc/bootstrap.php";
 
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );

echo "sadf";
die();

if ((isset($uri[2]) && $uri[2] != 'clip') || !isset($uri[3])) {
    header("HTTP/1.1 404 Not Found");
    exit();
}
 
require PROJECT_ROOT_PATH . "/controller/Clip.php";
 
$objFeedController = new UserController();
$strMethodName = $uri[3] . 'Action';
$objFeedController->{$strMethodName}();
?>