<?php
use Core\Api as Api;

class Handler extends Api {};

Handler::route('/get/{id}', function($request, $id) {
    $response = array(
        'id'=> $id,
        'request'=> $request
    );
    Handler::set('response', $response);
    Handler::send();
}, Handler::METHOD_GET);

Handler::route('/get/{id}/{group}', function($request, $id, $group) {
    $response = array(
        'id'=> $id,
        'group'=> $group,
        'request'=> $request
    );
    Handler::set('response', $response);
    Handler::send();
}, [Handler::METHOD_GET, Handler::METHOD_POST]);

?>