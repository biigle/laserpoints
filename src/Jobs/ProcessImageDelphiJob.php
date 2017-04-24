<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Queue;
use Biigle\Modules\Laserpoints\Support\DelphiGather;
use Biigle\Modules\Laserpoints\Jobs\CollectsLaserpointAnnotations;

class ProcessImageDelphiJob extends ProcessImageJob
{
    use CollectsLaserpointAnnotations;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->image) {
            // The image was deleted in the meantime.
            return;
        }

        $url = $this->image->volume->url;
        $points = $this->getLaserpointsForVolume($this->image->volume_id)
            ->map(function ($pts) {
                return json_decode('['.$pts->implode(',').']');
            });
        $images = $this->image->volume->images()
            ->whereIn('id', $points->keys())
            ->pluck('filename', 'id')
            ->map(function ($i, $id) use ($points) {
                return ['filename' => $i, 'points' => $points->get($id)];
            });

        $gather = App::make(DelphiGather::class);
        $gatherFile = $gather->execute(
            $this->image->volume->url,
            $images->pluck('filename')->all(),
            $images->pluck('points')->all()
        );

        // TODO: Figure out when to delete the ouput file. Maybe create another file that
        // keeps track of all the jobs that are still running? If the count becomes 0,
        // the last job deletes both files.

        Queue::push(new ProcessDelphiChunkJob($url, collect($this->image->id), $this->distance, $gatherFile));
    }
}
