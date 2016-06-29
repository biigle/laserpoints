<?php

namespace Dias\Modules\Laserpoints\Http\Controllers\Api;

use Dias\Modules\Laserpoints\Image;
use Dias\Transect;
use Dias\Http\Controllers\Api\Controller;
use Dias\Modules\Laserpoints\Jobs\ComputeAreaForImages;

class LaserpointsController extends Controller
{
    /**
     * Compute distance between laserpoints for an image
     *
     * @api {post} images/:id/laserpoints/area Compute image footprint
     * @apiGroup Images
     * @apiName ImagesComputeArea
     * @apiPermission projectEditor
     *
     * @apiParam {Number} id The image ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laserpoints in cm.
     *
     * @param int $id image id
     * @return \Illuminate\Http\Response
     */
    public function computeImage($id)
    {
        $image = Image::with('transect')->findOrFail($id);
        $this->authorize('edit-in', $image->transect);

        $this->validate($this->request, Image::$laserpointsRules);
        $distance = $this->request->input('distance');

        $this->dispatch(new ComputeAreaForImages($image->transect, $distance, [$image->id]));
    }

     /**
     * Compute distance between laserpoints for a transect
     *
     * @api {post} transects/:id/laserpoints/area Compute image footprint for all images
     * @apiGroup Transects
     * @apiName TransectsComputeImageArea
     * @apiPermission projectEditor
     *
     * @apiParam {Number} id The transect ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laserpoints in cm.
     *
     * @param int $id transect id
     * @return \Illuminate\Http\Response
     */
    public function computeTransect($id)
    {
        $transect = Transect::findOrFail($id);
        $this->authorize('edit-in', $transect);

        $this->validate($this->request, Image::$laserpointsRules);
        $distance = $this->request->input('distance');

        $this->dispatch(new ComputeAreaForImages($transect, $distance));
    }


}
