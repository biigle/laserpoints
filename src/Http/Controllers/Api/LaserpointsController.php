<?php

namespace Biigle\Modules\Laserpoints\Http\Controllers\Api;

use Biigle\Http\Controllers\Api\Controller;
use Biigle\Modules\Laserpoints\Http\Requests\ComputeImage;
use Biigle\Modules\Laserpoints\Http\Requests\ComputeVolume;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Jobs\ProcessDelphiJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
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
     * @apiParam (Optional arguments) {Number} label_id The ID of the label used for laserpoint annotations (required only if manual annotations exist).
     * @apiParam (Optional arguments) {Boolean} use_line_detection Whether to use line detection mode for improved accuracy.
     *
     * @param ComputeImage $request
     *
     * @return \Illuminate\Http\Response
     */
    public function computeImage(ComputeImage $request)
    {
        $image = Image::convert($request->image);
        $labelId = $request->input('label_id');
        
        // Check if the image has manual annotations
        if ($labelId) {
            // If a label is provided, check for manual annotations with that specific label
            $manualPoints = $image->annotations()
                ->where('shape_id', \Biigle\Shape::pointId())
                ->whereHas('labels', function ($query) use ($labelId) {
                    $query->where('label_id', $labelId);
                })
                ->count();
                
            if ($manualPoints >= 2) {
                // Image has manual annotations - use manual processing
                ProcessImageManualJob::dispatch($image, $request->input('distance'), $labelId)
                    ->onQueue(config('laserpoints.process_manual_queue'));
            } else {
                // Label provided but no matching manual annotations - use automatic detection
                ProcessDelphiJob::dispatch($image, $request->input('distance'))
                    ->onQueue(config('laserpoints.process_delphi_queue'));
            }
        } else {
            // No label provided - use automatic detection
            ProcessDelphiJob::dispatch($image, $request->input('distance'))
                ->onQueue(config('laserpoints.process_delphi_queue'));
        }
        
        return response()->json(['message' => 'Laser point detection job dispatched successfully.']);
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
     * @apiParam (Optional arguments) {Number} label_id The ID of the label used for laserpoint annotations (required only if manual annotations exist).
     * @apiParam (Optional arguments) {Boolean} use_line_detection Whether to use line detection mode for improved accuracy.
     *
     * @param ComputeVolume $request
     * @return \Illuminate\Http\Response
     */
    public function computeVolume(ComputeVolume $request)
    {
        $volume = Volume::convert($request->volume);
        $labelId = $request->input('label_id');
        $useLineDetection = $request->input('use_line_detection', true);
        
        ProcessVolumeDelphiJob::dispatch($volume, $request->input('distance'), $labelId, $useLineDetection)
            ->onQueue(config('laserpoints.process_delphi_queue'));
            
        return response()->json(['message' => 'Laser point detection job dispatched successfully.']);
    }
}
