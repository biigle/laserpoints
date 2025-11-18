<?php

namespace Biigle\Modules\Laserpoints\Http\Controllers\Api;

use Biigle\Http\Controllers\Api\Controller;
use Biigle\Label;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageAutomaticJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeAutomaticJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeManualJob;
use Biigle\Modules\Laserpoints\Volume;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LaserpointsController extends Controller
{
    /**
     * Compute distance between laser points for an image with manual annotations.
     *
     * @api {post} images/:id/laserpoints/manual Compute image area with manual annotations
     * @apiGroup Images
     * @apiName ImagesComputeAreaManual
     * @apiPermission projectEditor
     *
     * @apiParam {Number} id The image ID.
     * @apiParam (Required arguments) {Number} label_id ID of the laser point label that was used.
     * @apiParam (Required arguments) {Number} distance The distance between two laser points in cm.
     *
     * @param Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function imageManual(Request $request, $id)
    {
        $image = Image::with('volume')->findOrFail($id);
        $this->authorize('edit-in', $image->volume);
        // TODO manual is possible for tiled images? how does the script get the dimensions? do we need a python script for this at all?
        if ($image->tiled) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection is not available for very large images.',
            ]);
        }
        $request->validate([
            'distance' => 'required|numeric|min:1',
            'label_id' => 'required|integer|exists:labels,id',
        ]);

        $label = Label::find($request->input('label_id'));

        try {
            $image->readyForManualDetection($label);
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection can\'t be performed. '.$e->getMessage(),
            ]);
        }

        ProcessImageManualJob::dispatch($image, $label, $request->input('distance'))
            ->onQueue(config('laserpoints.process_manual_queue'));
    }

    /**
     * Compute distance between laser points for an image with automatic detection.
     *
     * @api {post} images/:id/laserpoints/automatic Compute image area with automatic detection
     * @apiGroup Images
     * @apiName ImagesComputeAreaAutomatic
     * @apiPermission projectEditor
     * @apiDescription This feature is not available for very large images.
     *
     * @apiParam {Number} id The image ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laser points in cm.
     *
     * @param Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function imageAutomatic(Request $request, $id)
    {
        $image = Image::with('volume')->findOrFail($id);
        $this->authorize('edit-in', $image->volume);
        if ($image->tiled) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection is not available for very large images.',
            ]);
        }
        $request->validate([
            'distance' => 'required|numeric|min:1',
        ]);

        ProcessImageAutomaticJob::dispatch($image, $request->input('distance'))
            ->onQueue(config('laserpoints.process_automatic_queue'));
    }

    /**
     * Compute distance between laser points for a volume with manual annotations.
     *
     * @api {post} volumes/:id/laserpoints/manual Compute image area with manual annotations
     * @apiGroup Volumes
     * @apiName VolumesComputeAreaManual
     * @apiPermission projectEditor
     *
     * @apiParam {Number} id The volume ID.
     * @apiParam (Required arguments) {Number} label_id ID of the laser point label that was used.
     * @apiParam (Required arguments) {Number} distance The distance between two laser points in cm.
     *
     * @param Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function volumeManual(Request $request, $id)
    {
        // TODO use cache key to prevent users from submitting multiple jobs at the same
        // time
        $volume = Volume::findOrFail($id);
        $this->authorize('edit-in', $volume);
        if (!$volume->isImageVolume()) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection is only available for image volumes.',
            ]);
        }
        // TODO manual is possible for tiled images? how does the script get the dimensions? do we need a python script for this at all?
        if ($volume->hasTiledImages()) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection is not available for volumes with very large images.',
            ]);
        }
        $request->validate([
            'distance' => 'required|numeric|min:1',
            'label_id' => 'required|integer|exists:labels,id',
        ]);

        $label = Label::find($request->input('label_id'));

        try {
            $volume->readyForManualDetection($label);
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection can\'t be performed. '.$e->getMessage(),
            ]);
        }

        ProcessVolumeManualJob::dispatch($volume, $label, $request->input('distance'))
            ->onQueue(config('laserpoints.process_manual_queue'));
    }

    /**
     * Compute distance between laser points for a volume with automatic detection.
     *
     * @api {post} volumes/:id/laserpoints/automatic Compute image area with automatic detection
     * @apiGroup Volumes
     * @apiName VolumesComputeAreaAutomatic
     * @apiPermission projectEditor
     * @apiDescription This feature is not available for video volumes and volumes with very large images.
     *
     * @apiParam {Number} id The image ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laser points in cm.
     * @apiParam (Optional arguments) {boolean} disable_line_detection Set to true if the laser pointers can move relative to the camera (e.g. laser points could move even if the vehicle does not move).
     *
     * @param Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function volumeAutomatic(Request $request, $id)
    {
        // TODO use cache key to track which image job should delete cache data
        // and to prevent users from submitting multiple jobs at the same time
        $volume = Volume::findOrFail($id);
        $this->authorize('edit-in', $volume);

        if (!$volume->isImageVolume()) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection is only available for image volumes.',
            ]);
        }

        if ($volume->hasTiledImages()) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection is not available for volumes with very large images.',
            ]);
        }

        $request->validate([
            'distance' => 'required|numeric|min:1',
            'disable_line_detection' => 'boolean',
        ]);

        ProcessVolumeAutomaticJob::dispatch($volume, $request->input('distance'), $request->input('disable_line_detection', false))
            ->onQueue(config('laserpoints.process_automatic_queue'));
    }
}
