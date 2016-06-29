<?php

$router->group([
    'namespace' => 'Api',
    'prefix' => 'api/v1',
    'middleware' => 'auth.api',
], function ($router) {
    $router->post('images/{id}/laserpoints/area', [
        'uses' => 'LaserpointsController@computeImage',
    ]);

    $router->post('transects/{id}/laserpoints/area', [
        'uses' => 'LaserpointsController@computeTransect',
    ]);

    $router->get('images/{id}/laserpoints', [
        'uses' => 'ImagesController@showLaserpoints',
    ]);
});
