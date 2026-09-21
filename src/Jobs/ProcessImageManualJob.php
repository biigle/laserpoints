<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Biigle\Jobs\Job;
use Biigle\Label;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DetectManual;
use Biigle\Modules\Laserpoints\Support\DetectionLock;
use Biigle\Shape;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessImageManualJob extends Job implements ShouldQueue
{
    use Batchable, InteractsWithQueue, SerializesModels;

    public $tries = 1;

    /**
     * ID of the image to process.
     *
     * The image model is not serialized with DeleteWhenMissingModels because a job
     * that is deleted this way neither releases the image lock nor updates its batch
     * (which would never finish and keep the volume lock).
     *
     * @var int
     */
    public int $imageId;

    /**
     * ID of the volume of the image. Required to release the lock if the image was
     * deleted.
     *
     * @var int
     */
    public int $volumeId;

    /**
     * Create a new job instance.
     *
     * @param Image $image The image to process. Must have the id and volume_id attributes.
     * @param Label $label The laser point label.
     * @param float $distance Distance between laser points im cm to use for computation.
     * @param bool $batch Whether the job is part of a volume detection. Otherwise it
     * releases the image lock when it's finished.
     *
     * @return void
     */
    public function __construct(
        Image $image,
        public Label $label,
        public float $distance,
        public bool $batch = false,
    )
    {
        $this->imageId = $image->id;
        $this->volumeId = $image->volume_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $image = Image::find($this->imageId);
            if ($image) {
                $this->detect($image);
            }
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Handle a job failure.
     *
     * @param Throwable $exception
     *
     * @return void
     */
    public function failed(?Throwable $exception)
    {
        $this->releaseLock();
    }

    /**
     * Perform the laser point detection.
     *
     * @param Image $image
     *
     * @return void
     */
    protected function detect(Image $image)
    {
        try {
            $detect = App::make(DetectManual::class);
            $output = $detect->execute(
                $image->width,
                $image->height,
                $this->distance,
                $this->getLaserpoints($image)
            );
        } catch (Exception $e) {
            $output = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        $output['distance'] = $this->distance;

        $image->laserpoints = $output;
        $image->save();
    }

    /**
     * Collects the laser point annotations of the given image.
     *
     * @param Image $image
     *
     * @return array Array of annotation coordinates as `[x, y]` pairs
     */
    protected function getLaserpoints(Image $image)
    {
        // Use an exists constraint instead of a join because the same label can be
        // attached to the same annotation by multiple users. A join would return the
        // points of these annotations more than once, which would distort the computed
        // image area.
        return $image->annotations()
            ->where('shape_id', Shape::pointId())
            ->whereHas('labels', fn ($query) => $query->where('label_id', $this->label->id))
            ->orderBy('id')
            ->pluck('points')
            ->toArray();
    }

    /**
     * Release the lock of a single image detection. The lock of a volume detection is
     * released by the batch.
     *
     * @return void
     */
    protected function releaseLock()
    {
        if (!$this->batch) {
            DetectionLock::releaseImage($this->volumeId, $this->imageId);
        }
    }
}
