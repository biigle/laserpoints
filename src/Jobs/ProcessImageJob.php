<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Queue;
use Biigle\Image;
use Biigle\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

abstract class ProcessImageJob extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * The image to compute the area for.
     *
     * @var Image
     */
    protected $image;

    /**
     * Distance between laserpoints im cm to use for computation.
     *
     * @var float
     */
    protected $distance;

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
        $this->image = $image;
        $this->distance = $distance;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    abstract public function handle();
}
