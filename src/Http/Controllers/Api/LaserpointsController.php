<?php

namespace Biigle\Modules\Laserpoints\Http\Controllers\Api;

use Biigle\Transect;
use Illuminate\Http\Request;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Http\Controllers\Api\Controller;
use Biigle\Modules\Laserpoints\Jobs\LaserpointDetection;

class LaserpointsController extends Controller
{
    /**
     * Compute distance between laserpoints for an image
     *
     * @api {post} images/:id/laserpoints/area Compute image footprint
     * @apiGroup Images
     * @apiName ImagesComputeArea
     * @apiPermission projectEditor
     * @apiDescription This feature is not available for images of remote transects.
     *
     * @apiParam {Number} id The image ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laserpoints in cm.
     *
     * @param Request $request
     * @param int $id image id
     * @return \Illuminate\Http\Response
     */
    public function computeImage(Request $request, $id)
    {
        $image = Image::with('transect')->findOrFail($id);
        $this->authorize('edit-in', $image->transect);

        if ($image->transect->isRemote()) {
            return $this->buildFailedValidationResponse($request, [
                'id' => 'Laserpoint detection is not available for images of remote transects.',
            ]);
        }

        $this->validate($request, Image::$laserpointsRules);
        $distance = $request->input('distance');

        $this->dispatch(new LaserpointDetection($image->transect, $distance, [$image->id]));
    }

     /**
     * Compute distance between laserpoints for a transect
     *
     * @api {post} transects/:id/laserpoints/area Compute image footprint for all images
     * @apiGroup Transects
     * @apiName TransectsComputeImageArea
     * @apiPermission projectEditor
     * @apiDescription This feature is not available for remote transects.
     *
     * @apiParam {Number} id The transect ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laserpoints in cm.
     *
     * @param Request $request
     * @param int $id transect id
     * @return \Illuminate\Http\Response
     */
    public function computeTransect(Request $request, $id)
    {
        $transect = Transect::findOrFail($id);
        $this->authorize('edit-in', $transect);

        if ($transect->isRemote()) {
            return $this->buildFailedValidationResponse($request, [
                'id' => 'Laserpoint detection is not available for remote transects.',
            ]);
        }

        $this->validate($request, Image::$laserpointsRules);
        $distance = $request->input('distance');

        $this->dispatch(new LaserpointDetection($transect, $distance));
    }
}
