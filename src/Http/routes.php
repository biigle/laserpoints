<?php

$router->group([
    'namespace' => 'Api',
    'prefix' => 'api/v1',
    'middleware' => ['api', 'auth:web,api'],
], function ($router) {
    $router->post('images/{id}/laserpoints/manual', [
        'uses' => 'LaserpointsController@imageManual',
    ]);

    $router->post('images/{id}/laserpoints/automatic', [
        'uses' => 'LaserpointsController@imageAutomatic',
    ]);

    $router->post('volumes/{id}/laserpoints/manual', [
        'uses' => 'LaserpointsController@volumeManual',
    ]);

    $router->post('volumes/{id}/laserpoints/automatic', [
        'uses' => 'LaserpointsController@volumeAutomatic',
    ]);

    $router->get('images/{id}/laserpoints', [
        'uses' => 'ImagesController@showLaserpoints',
    ]);

    $router->delete('images/{id}/laserpoints', [
        'uses' => 'LaserpointsController@destroyImage',
    ]);

    $router->delete('volumes/{id}/laserpoints', [
        'uses' => 'LaserpointsController@destroyVolume',
    ]);

    $router->get('volumes/{id}/images/filter/laserpoints', [
        'uses' => 'VolumeImageController@index',
    ]);
});
