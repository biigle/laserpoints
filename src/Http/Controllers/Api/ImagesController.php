<?php

namespace Dias\Modules\Laserpoints\Http\Controllers\Api;

use Dias\Image;
use Dias\Modules\Laserpoints\Image as LaserImage;
use Dias\Http\Controllers\Api\Controller;

class ImagesController extends Controller
{
    /**
     * Show laserpoint data for this image
     *
     * @api {get} images/:id/laserpoints Show laserpoints data
     * @apiGroup Images
     * @apiName ImagesShowLaserpoints
     * @apiPermission projectMember
     *
     * @apiParam {Number} id The image ID
     *
     * @param int $id image id
     * @return \Illuminate\Http\Response
     */
    public function showLaserpoints($id)
    {
        $image = Image::findOrFail($id);
        $this->authorize('access', $image);

        return LaserImage::convert($image)->laserpoints;
    }
}