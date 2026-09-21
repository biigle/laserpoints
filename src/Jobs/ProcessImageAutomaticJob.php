<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Biigle\Jobs\Job;
use Biigle\Shape;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DetectAutomatic;
use Biigle\Modules\Laserpoints\Support\DetectionLock;
use Exception;
use FileCache;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class ProcessImageAutomaticJob extends Job implements ShouldQueue
{
    use Batchable, InteractsWithQueue;

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
     * @param float $distance Distance between laser points im cm to use for computation.
     * @param ?string $channelMode Channel mode override (red/green/blue/gray)
     * @param int $numLaserpoints Number of laser points to search for.
     * @param bool $batch Whether the job is part of a volume detection. Otherwise it
     * releases the image lock when it's finished.
     *
     * @return void
     */
    public function __construct(
        Image $image,
        public float $distance,
        public ?string $channelMode = null,
        public int $numLaserpoints = 2,
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
            $output = FileCache::get($image, function ($image, $path) {
                $detect = App::make(DetectAutomatic::class);

                return $detect->execute($path, $this->distance, $this->channelMode, $this->numLaserpoints);
            });
        } catch (Exception $e) {
            $output = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        $output['distance'] = $this->distance;
        if ($this->channelMode) {
            $output['channel_mode'] = $this->channelMode;
        }

        $image->laserpoints = $output;
        $image->save();
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
