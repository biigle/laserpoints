<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Exception;
use FileCache;
use Biigle\Jobs\Job as BaseJob;
use Biigle\Modules\Laserpoints\Image;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Biigle\Modules\Laserpoints\Support\Detect;

class ProcessManualChunkJob extends BaseJob implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Array mapping image IDs to laser point coordinates.
     *
     * @var Collection
     */
    protected $points;

    /**
     * Distance between laser points im cm to use for computation.
     *
     * @var float
     */
    protected $distance;

    /**
     * Create a new job instance.
     *
     * @param Collection $points
     * @param float $distance
     *
     * @return void
     */
    public function __construct($points, $distance)
    {
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
        $detect = App::make(Detect::class);

        $images = Image::whereIn('id', $this->points->keys())
            ->with('volume')
            ->select('id', 'filename', 'volume_id')
            ->get();

        $callback = function ($image, $path) use ($detect) {
            return $detect->execute($path, $this->distance, $this->points->get($image->id));
        };

        foreach ($images as $image) {
            try {
                $output = FileCache::getOnce($image, $callback);
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
    }
}
