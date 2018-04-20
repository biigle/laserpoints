<?php

namespace Biigle\Modules\Laserpoints\Http\Controllers\Api;

use Exception;
use Illuminate\Http\Request;
use Biigle\Volume as BaseVolume;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Volume;
use Biigle\Http\Controllers\Api\Controller;
use Illuminate\Validation\ValidationException;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageDelphiJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeDelphiJob;

class LaserpointsController extends Controller
{
    /**
     * Compute distance between laser points for an image.
     *
     * @api {post} images/:id/laserpoints/area Compute image footprint
     * @apiGroup Images
     * @apiName ImagesComputeArea
     * @apiPermission projectEditor
     * @apiDescription This feature is not available for very large images.
     *
     * @apiParam {Number} id The image ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laser points in cm.
     *
     * @param Request $request
     * @param int $id image id
     * @return \Illuminate\Http\Response
     */
    public function computeImage(Request $request, $id)
    {
        $image = Image::with('volume')->findOrFail($id);
        $this->authorize('edit-in', $image->volume);

        if ($image->tiled === true) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection is not available for very large images.',
            ]);
        }

        try {
            $manual = $image->readyForManualDetection();
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection can\'t be performed. '.$e->getMessage(),
            ]);
        }

        if (!$manual) {
            try {
                Volume::convert($image->volume)->readyForDelphiDetection();
            } catch (Exception $e) {
                throw ValidationException::withMessages([
                    'id' => 'Delphi laser point detection can\'t be performed. '.$e->getMessage(),
                ]);
            }
        }

        $this->validate($request, Image::$laserpointsRules);
        $distance = $request->input('distance');

        if ($manual) {
            $this->dispatch(new ProcessImageManualJob($image, $distance));
        } else {
            $this->dispatch(new ProcessImageDelphiJob($image, $distance));
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
     * @apiParam (Required arguments) {Number} distance The distance between two laser points in cm.
     *
     * @param Request $request
     * @param int $id volume id
     * @return \Illuminate\Http\Response
     */
    public function computeVolume(Request $request, $id)
    {
        $volume = BaseVolume::findOrFail($id);
        $this->authorize('edit-in', $volume);

        if ($volume->hasTiledImages()) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection is not available for volumes with very large images.',
            ]);
        }

        try {
            Volume::convert($volume)->readyForDelphiDetection();
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'id' => 'Delphi laser point detection can\'t be performed. '.$e->getMessage(),
            ]);
        }

        $this->validate($request, Image::$laserpointsRules);
        $distance = $request->input('distance');

        $this->dispatch(new ProcessVolumeDelphiJob($volume, $distance));
    }
}
