<?php
require_once 'sys' . DIRECTORY_SEPARATOR . 'core.php';
use Core\App as App;

$app = new App();
$app->addEndpoint('Handler','endpoints/hello', '/api/v1/hello');

$app->beforeRequest(function($request) {
    print_r($_SERVER);
});

$app->run();

?>