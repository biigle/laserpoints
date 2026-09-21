<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Biigle\Jobs\Job;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DetectColor;
use Biigle\Volume;
use Exception;
use FileCache;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\SerializesModels;
use Log;

#[DeleteWhenMissingModels]
class ProcessVolumeAutomaticJob extends Job
{
    use Batchable, SerializesModels;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param Volume $volume The volume to process the images of.
     * @param float $distance Distance between laser points im cm to use for computation.
     * @param int $numLaserpoints Number of laser points to search for.
     *
     * @return void
     */
    public function __construct(
        protected Volume $volume,
        protected float $distance,
        protected int $numLaserpoints = 2,
    )
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $colorSampleImages = $this->volume->images()
            ->inRandomOrder()
            ->take(100)
            ->get()
            ->all();

        try {
            $channelMode = FileCache::batch($colorSampleImages, function ($images, $paths) {
                return $this->performColorDetection($images, $paths);
            });
        } catch (Exception $e) {
            // Fall back to the automatic channel detection of each individual image
            // instead of failing the detection for the whole volume.
            Log::warning('Laser point color detection failed for volume '.$this->volume->id.'. Falling back to automatic detection per image.', [
                'exception' => $e->getMessage(),
            ]);
            $channelMode = null;
        }

        // The volume lock is released by the batch that this job is part of.
        $this->volume->images()
            ->select('id', 'volume_id')
            ->chunkById(1000, function ($images) use ($channelMode) {
                $jobs = $images->map(fn ($image) => new ProcessImageAutomaticJob(
                    Image::convert($image),
                    $this->distance,
                    $channelMode,
                    $this->numLaserpoints,
                    batch: true
                ));

                $this->batch()->add($jobs->all());
            });
    }

    /**
     * Execute the color detection.
     *
     * @param array $images Cached image models
     * @param array $paths Cached image file paths
     *
     * @return ?string
     */
    protected function performColorDetection(array $images, array $paths)
    {
        $input = array_combine(array_map(fn ($image) => $image->id, $images), $paths);
        $detect = App::make(DetectColor::class);

        return $detect->execute($input, $this->numLaserpoints);
    }
}
