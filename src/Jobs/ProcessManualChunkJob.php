<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Exception;
use Biigle\Jobs\Job;
use Biigle\Modules\Laserpoints\Image;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Biigle\Modules\Laserpoints\Support\Detect;

class ProcessManualChunkJob extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * URL of the volume the images belong to.
     *
     * @var string
     */
    protected $volumeUrl;

    /**
     * Array mapping image IDs to laserpoint coordinates
     *
     * @var Collection
     */
    protected $points;

    /**
     * Distance between laserpoints im cm to use for computation.
     *
     * @var float
     */
    private $distance;

    /**
     * Create a new job instance.
     *
     * @param string $volumeUrl
     * @param Collection $points
     * @param float $distance
     *
     * @return void
     */
    public function __construct($volumeUrl, $points, $distance)
    {
        $this->volumeUrl = $volumeUrl;
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
        $labelId = config('laserpoints.label_id');
        $detect = app()->make(Detect::class);

        $images = Image::whereIn('id', $this->points->keys())
            ->select('id', 'filename')
            ->get();

        foreach ($images as $image) {
            try {
                $imagePoints = '['.$this->points[$image->id]->implode(',').']';
                $output = $detect->execute(
                    "{$this->volumeUrl}/{$image->filename}",
                    $this->distance,
                    $imagePoints
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
    }
}
