<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Queue;
use Biigle\Image;
use Biigle\Modules\Laserpoints\Support\DelphiGather;

class ProcessImageDelphiJob extends Job
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
            $url,
            $images->pluck('filename')->all(),
            $images->pluck('points')->all()
        );

        Queue::push(new ProcessDelphiChunkJob($url, collect($this->image->id), $this->distance, $gatherFile));
    }
}
