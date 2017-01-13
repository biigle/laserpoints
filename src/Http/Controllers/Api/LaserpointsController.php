<?php

namespace Biigle\Modules\Laserpoints\Http\Controllers\Api;

use Biigle\Volume;
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
     * @apiDescription This feature is not available for images of remote volumes.
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
        $image = Image::with('volume')->findOrFail($id);
        $this->authorize('edit-in', $image->volume);

        if ($image->volume->isRemote()) {
            return $this->buildFailedValidationResponse($request, [
                'id' => 'Laserpoint detection is not available for images of remote volumes.',
            ]);
        }

        $this->validate($request, Image::$laserpointsRules);
        $distance = $request->input('distance');

        $this->dispatch(new LaserpointDetection($image->volume, $distance, [$image->id]));
    }

     /**
     * Compute distance between laserpoints for a volume
     *
     * @api {post} volumes/:id/laserpoints/area Compute image footprint for all images
     * @apiGroup Volumes
     * @apiName VolumesComputeImageArea
     * @apiPermission projectEditor
     * @apiDescription This feature is not available for remote volumes.
     *
     * @apiParam {Number} id The volume ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laserpoints in cm.
     *
     * @param Request $request
     * @param int $id volume id
     * @return \Illuminate\Http\Response
     */
    public function computeVolume(Request $request, $id)
    {
        $volume = Volume::findOrFail($id);
        $this->authorize('edit-in', $volume);

        if ($volume->isRemote()) {
            return $this->buildFailedValidationResponse($request, [
                'id' => 'Laserpoint detection is not available for remote volumes.',
            ]);
        }

        $this->validate($request, Image::$laserpointsRules);
        $distance = $request->input('distance');

        $this->dispatch(new LaserpointDetection($volume, $distance));
    }
}
