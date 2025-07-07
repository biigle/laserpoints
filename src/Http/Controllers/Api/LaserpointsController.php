<?php

namespace Biigle\Modules\Laserpoints\Http\Controllers\Api;

use Biigle\Http\Controllers\Api\Controller;
use Biigle\Modules\Laserpoints\Http\Requests\ComputeImage;
use Biigle\Modules\Laserpoints\Http\Requests\ComputeVolume;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageDelphiJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeDelphiJob;
use Biigle\Modules\Laserpoints\Volume;

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
     * @apiParam (Required arguments) {Number} distance The distance between two laser points in cm.
     * @apiParam (Optional arguments) {Boolean} use_line_detection Whether to use line detection mode for improved accuracy.
     *
     * @param ComputeImage $request
     *
     * @return \Illuminate\Http\Response
     */
    public function computeImage(ComputeImage $request)
    {
        $image = Image::convert($request->image);
        $useLineDetection = $request->input('use_line_detection', true);
        
        // For individual images, we can't use line detection mode since it requires
        // processing multiple images. Fall back to regular detection.
        if ($useLineDetection) {
            // Note: Individual image processing always uses regular detection
            // Line detection requires processing the whole volume first
        }
        
        ProcessImageDelphiJob::dispatch($image, $request->input('distance'), null)
            ->onQueue(config('laserpoints.process_delphi_queue'));
    }

    /**
     * Compute distance between laser points for a volume.
     *
     * @api {post} volumes/:id/laserpoints/area Compute image footprint for all images
     * @apiGroup Volumes
     * @apiName VolumesComputeImageArea
     * @apiPermission projectEditor
     * @apiDescription This feature is not available for video volumes and volumes with very large images.
     *
     * @apiParam {Number} id The volume ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laser points in cm.
     * @apiParam (Optional arguments) {Boolean} use_line_detection Whether to use line detection mode for improved accuracy.
     *
     * @param ComputeVolume $request
     * @return \Illuminate\Http\Response
     */
    public function computeVolume(ComputeVolume $request)
    {
        $volume = Volume::convert($request->volume);
        $useLineDetection = $request->input('use_line_detection', true);
        
        ProcessVolumeDelphiJob::dispatch($volume, $request->input('distance'), null, $useLineDetection)
            ->onQueue(config('laserpoints.process_delphi_queue'));
    }
}
