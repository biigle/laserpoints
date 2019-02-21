<?php

namespace Biigle\Modules\Laserpoints\Http\Controllers\Api;

use Exception;
use Biigle\Label;
use Biigle\Volume as BaseVolume;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Volume;
use Biigle\Http\Controllers\Api\Controller;
use Illuminate\Validation\ValidationException;
use Biigle\Modules\Laserpoints\Http\Requests\ComputeImage;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageDelphiJob;
use Biigle\Modules\Laserpoints\Http\Requests\ComputeVolume;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeDelphiJob;

class LaserpointsController extends Controller
{
    /**
     * Compute distance between laser points for an image.
     *
     * @api {post} images/:id/laserpoints/area Compute image area
     * @apiGroup Images
     * @apiName ImagesComputeArea
     * @apiPermission projectEditor
     * @apiDescription This feature is not available for very large images.
     *
     * @apiParam {Number} id The image ID.
     * @apiParam (Required arguments) {Number} label_id ID of the laser point label that was used.
     * @apiParam (Required arguments) {Number} distance The distance between two laser points in cm.
     *
     * @param ComputeImage $request
     * @param int $id image id
     * @return \Illuminate\Http\Response
     */
    public function computeImage(ComputeImage $request)
    {
        $image = Image::convert($request->image);
        $label = Label::find($request->input('label_id'));

        try {
            $manual = $image->readyForManualDetection($label);
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection can\'t be performed. '.$e->getMessage(),
            ]);
        }

        if ($manual) {
            ProcessImageManualJob::dispatch($image, $request->input('distance'), $label->id);
        } else {
            try {
                Volume::convert($image->volume)->readyForDelphiDetection($label);
            } catch (Exception $e) {
                throw ValidationException::withMessages([
                    'id' => 'Delphi laser point detection can\'t be performed. '.$e->getMessage(),
                ]);
            }

            ProcessImageDelphiJob::dispatch($image, $request->input('distance'), $label->id);
        }
    }

    /**
     * Compute distance between laser points for a volume.
     *
     * @api {post} volumes/:id/laserpoints/area Compute image footprint for all images
     * @apiGroup Volumes
     * @apiName VolumesComputeImageArea
     * @apiPermission projectEditor
     * @apiDescription This feature is not available for very large images.
     *
     * @apiParam {Number} id The volume ID.
     * @apiParam (Required arguments) {Number} label_id ID of the laser point label that was used.
     * @apiParam (Required arguments) {Number} distance The distance between two laser points in cm.
     *
     * @param ComputeVolume $request
     * @return \Illuminate\Http\Response
     */
    public function computeVolume(ComputeVolume $request)
    {
        $volume = Volume::convert($request->volume);
        $label = Label::find($request->input('label_id'));

        try {
            $volume->readyForDelphiDetection($label);
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'id' => 'Delphi laser point detection can\'t be performed. '.$e->getMessage(),
            ]);
        }

        ProcessVolumeDelphiJob::dispatch($volume, $request->input('distance'), $label->id);
    }
}
