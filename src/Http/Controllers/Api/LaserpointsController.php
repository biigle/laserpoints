<?php

namespace Biigle\Modules\Laserpoints\Http\Controllers\Api;

use Biigle\Http\Controllers\Api\Controller;
use Biigle\Label;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageAutomaticJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeAutomaticJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeManualJob;
use Biigle\Modules\Laserpoints\Support\DetectionLock;
use Biigle\Modules\Laserpoints\Volume;
use Bus;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        // The manual detection itself works for tiled images, too, as it only needs
        // the image dimensions. This restriction can be lifted once the UI offers the
        // manual detection for volumes with very large images.
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

        $this->acquireImageLock($image);

        try {
            ProcessImageManualJob::dispatch($image, $label, $request->input('distance'))
                ->onQueue(config('laserpoints.process_manual_queue'));
        } catch (Throwable $e) {
            DetectionLock::releaseImage($image->volume_id, $image->id);
            throw $e;
        }
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
     * @apiParam (Required arguments) {Number} num_laserpoints Number of laser points to search for (2-4).
     * @apiParam (Required arguments) {String} channel_mode Color channel to use (red/green/blue/gray).
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
            'num_laserpoints' => 'required|integer|min:'.Image::MIN_POINTS.'|max:'.Image::MAX_POINTS,
            'channel_mode' => 'required|in:red,green,blue,gray',
        ]);

        $this->acquireImageLock($image);

        try {
            ProcessImageAutomaticJob::dispatch($image, $request->input('distance'), $request->input('channel_mode'), $request->input('num_laserpoints'))
                ->onQueue(config('laserpoints.process_automatic_queue'));
        } catch (Throwable $e) {
            DetectionLock::releaseImage($image->volume_id, $image->id);
            throw $e;
        }
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
        $volume = Volume::findOrFail($id);
        $this->authorize('edit-in', $volume);
        if (!$volume->isImageVolume()) {
            throw ValidationException::withMessages([
                'id' => 'Laser point detection is only available for image volumes.',
            ]);
        }
        // The manual detection itself works for tiled images, too, as it only needs
        // the image dimensions. This restriction can be lifted once the UI offers the
        // manual detection for volumes with very large images.
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

        $this->acquireVolumeLock($volume);

        $this->dispatchVolumeBatch(
            $volume,
            new ProcessVolumeManualJob($volume, $label, $request->input('distance')),
            config('laserpoints.process_manual_queue')
        );
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
     * @apiParam {Number} id The volume ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laser points in cm.
     * @apiParam (Required arguments) {Number} num_laserpoints Number of laser points to search for (2-4).
     *
     * @param Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function volumeAutomatic(Request $request, $id)
    {
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
            'num_laserpoints' => 'required|integer|min:'.Image::MIN_POINTS.'|max:'.Image::MAX_POINTS,
        ]);

        $this->acquireVolumeLock($volume);

        $this->dispatchVolumeBatch(
            $volume,
            new ProcessVolumeAutomaticJob($volume, $request->input('distance'), $request->input('num_laserpoints')),
            config('laserpoints.process_automatic_queue')
        );
    }

    /**
     * Dispatch a volume job as a batch that releases the volume lock when it's
     * finished. The volume job adds the image jobs to the same batch in chunks. The
     * batch can't finish before all image jobs were added because the volume job is
     * part of it.
     *
     * @param Volume $volume
     * @param object $job
     * @param string $queue
     */
    protected function dispatchVolumeBatch(Volume $volume, $job, $queue)
    {
        $volumeId = $volume->id;

        try {
            // The closure is static so the controller is not serialized with it.
            Bus::batch([$job])
                ->onQueue($queue)
                ->allowFailures()
                ->finally(static fn () => DetectionLock::releaseVolume($volumeId))
                ->dispatch();
        } catch (Throwable $e) {
            DetectionLock::releaseVolume($volumeId);
            throw $e;
        }
    }

    /**
     * Prevent a new volume detection while another detection runs in the volume.
     *
     * @param Volume $volume
     *
     * @throws ValidationException
     */
    protected function acquireVolumeLock(Volume $volume)
    {
        if (!DetectionLock::acquireVolume($volume->id)) {
            throw ValidationException::withMessages([
                'id' => 'A laser point detection is already running for this volume.',
            ]);
        }
    }

    /**
     * Prevent a new image detection while a volume detection or another detection of
     * the same image runs.
     *
     * @param Image $image
     *
     * @throws ValidationException
     */
    protected function acquireImageLock(Image $image)
    {
        if (!DetectionLock::acquireImage($image->volume_id, $image->id)) {
            throw ValidationException::withMessages([
                'id' => 'A laser point detection is already running for this image or its volume.',
            ]);
        }
    }
}
