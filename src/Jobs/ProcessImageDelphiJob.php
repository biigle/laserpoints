<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Biigle\Image;
use Illuminate\Foundation\Bus\DispatchesJobs;

class ProcessImageDelphiJob extends Job
{
    use DispatchesJobs;

    /**
     * The image to compute the area for.
     *
     * @var Image
     */
    protected $image;

    /**
     * Create a new job instance.
     *
     * @param Image $image
     * @param float $distance
     *
     * @return void
     */
    public function __construct(Image $image, $distance)
    {
        parent::__construct($distance);
        $this->image = $image;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // The image may be deleted in the meantime.
        if (!$this->image) {
            return;
        }

        $points = $this->getLaserpointsForVolume($this->image->volume_id);
        $gatherFile = $this->gather($points);
        $this->dispatch(new ProcessDelphiChunkJob([$this->image->id], $this->distance, $gatherFile));
    }
}
