<?php

$router->group([
    'namespace' => 'Api',
    'prefix' => 'api/v1',
    'middleware' => 'auth.api',
], function ($router) {
    $router->get('images/{id}/laserpoints/area/{laserdist}', [
        'uses' => 'LaserpointsController@area',
    ]);
    $router->get('transects/{id}/laserpoints/area/{laserdist}', [
        'uses' => 'LaserpointsController@TransectArea',
    ]);
});
