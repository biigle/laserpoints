<?php

$router->group([
    'namespace' => 'Api',
    'prefix' => 'api/v1',
    'middleware' => 'auth.api',
], function ($router) {
    $router->post('images/{id}/laserpoints/area', [
        'uses' => 'LaserpointsController@computeImage',
    ]);

    $router->post('volumes/{id}/laserpoints/area', [
        'uses' => 'LaserpointsController@computeVolume',
    ]);

    $router->get('images/{id}/laserpoints', [
        'uses' => 'ImagesController@showLaserpoints',
    ]);
});
