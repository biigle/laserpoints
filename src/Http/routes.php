<?php

$router->group([
    'namespace' => 'Api',
    'prefix' => 'api/v1',
    'middleware' => 'auth.api',
], function ($router) {
    $router->post('images/{id}/laserpoints/area/{laserdist}', [
        'uses' => 'LaserpointsController@area',
    ]);
    $router->post('transects/{id}/laserpoints/area/{laserdist}', [
        'uses' => 'LaserpointsController@TransectArea',
    ]);
});
