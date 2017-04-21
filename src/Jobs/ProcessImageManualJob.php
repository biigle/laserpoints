<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Queue;
use Biigle\Modules\Laserpoints\Jobs\CollectsLaserpointAnnotations;

class ProcessImageManualJob extends ProcessImageJob
{
    use CollectsLaserpointAnnotations;

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
