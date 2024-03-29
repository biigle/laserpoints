<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Biigle\Image;
use Illuminate\Support\Facades\Bus;
use Storage;

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
     * @param int $labelId
     *
     * @return void
     */
    public function __construct(Image $image, $distance, $labelId)
    {
        parent::__construct($distance, $labelId);
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
        $job = new ProcessDelphiJob($this->image, $this->distance, $gatherFile);

        Bus::batch([$job])
            ->onQueue(config('laserpoints.process_delphi_queue'))
            ->finally(function () use ($gatherFile) {
                Storage::disk(config('laserpoints.disk'))->delete($gatherFile);
            })
            ->dispatch();
    }
}
