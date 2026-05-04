<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Biigle\Jobs\Job;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DetectColor;
use Biigle\Volume;
use FileCache;
use Illuminate\Queue\SerializesModels;

class ProcessVolumeAutomaticJob extends Job
{
    use SerializesModels;

    public $tries = 1;

    /**
     * Ignore this job if the image does not exist any more.
     *
     * @var bool
     */
    protected $deleteWhenMissingModels = true;

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
        $channelMode = FileCache::batch($colorSampleImages, function ($images, $paths) {
            return $this->performColorDetection($images, $paths);
        });

        $this->volume->images()
            ->eachById(function ($image) use ($channelMode) {
                $image = Image::convert($image);
                ProcessImageAutomaticJob::dispatch($image, $this->distance, $channelMode, $this->numLaserpoints)
                    ->onQueue(config('laserpoints.process_automatic_queue'));
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
