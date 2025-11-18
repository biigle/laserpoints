<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Biigle\Jobs\Job;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DetectLines;
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
     * @param bool $disableLineDetection Whether to disable the line detection mode.
     *
     * @return void
     */
    public function __construct(
        protected Volume $volume,
        protected float $distance,
        protected bool $disableLineDetection = false,
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
        $lineInfo = null;
        if (!$this->disableLineDetection) {
            $lineSampleImages = $this->volume->images()
                ->inRandomOrder()
                ->take(100)
                ->get()
                ->all();
            $lineInfo = FileCache::batch($lineSampleImages, function ($images, $paths) {
                return $this->performLineDetection($images, $paths);
            });
        }


        $this->volume->images()
            ->eachById(function ($image) use ($lineInfo) {
                $image = Image::convert($image);
                ProcessImageAutomaticJob::dispatch($image, $this->distance, $lineInfo)
                    ->onQueue(config('laserpoints.process_automatic_queue'));
            });
    }

    /**
     * Execute the line detection.
     *
     * @param array $images Cached image models
     * @param array $paths Cached image file paths
     *
     * @return string
     */
    protected function performLineDetection(array $images, array $paths)
    {
        $input = array_combine(array_map(fn ($image) => $image->id, $images), $paths);
        $detect = App::make(DetectLines::class);

        return $detect->execute($input, $this->distance);
    }
}
