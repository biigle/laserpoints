<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Queue;
use Biigle\Image;

class ProcessImageManualJob extends Job
{
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
        if (!$this->image) return;

        $url = $this->image->volume->url;
        $points = $this->getLaserpointsForImages([$this->image->id]);
        Queue::push(new ProcessManualChunkJob($url, $points, $this->distance));
    }
}
