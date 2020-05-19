<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Exception;
use FileCache;
use Biigle\Jobs\Job as BaseJob;
use Biigle\Modules\Laserpoints\Image;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Biigle\Modules\Laserpoints\Support\Detect;

class ProcessManualJob extends BaseJob implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * The image to process.
     *
     * @var Image
     */
    protected $image;

    /**
     * Laser point coordinates for the image.
     *
     * @var string
     */
    protected $points;

    /**
     * Distance between laser points im cm to use for computation.
     *
     * @var float
     */
    protected $distance;

    /**
     * Ignore this job if the image does not exist any more.
     *
     * @var bool
     */
    protected $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param Image $image
     * @param string $points
     * @param float $distance
     *
     * @return void
     */
    public function __construct($image, $points, $distance)
    {
        $this->image = $image;
        $this->points = $points;
        $this->distance = $distance;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $output = FileCache::getOnce($this->image, function ($image, $path) {
                $detect = App::make(Detect::class);

                return $detect->execute($path, $this->distance, $this->points);
            });
        } catch (Exception $e) {
            $output = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        $output['distance'] = $this->distance;

        $this->image->laserpoints = $output;
        $this->image->save();
    }
}
