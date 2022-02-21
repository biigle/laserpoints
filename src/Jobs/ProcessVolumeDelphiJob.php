<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Biigle\Modules\Laserpoints\Image;
use Biigle\Volume;
use Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Storage;

class ProcessVolumeDelphiJob extends Job
{
    use SerializesModels;

    /**
     * The volume to process the images of.
     *
     * @var Volume
     */
    protected $volume;

    /**
     * Ignore this job if the image does not exist any more.
     *
     * @var bool
     */
    protected $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param Volume $volume
     * @param float $distance
     * @param int $labelId
     *
     * @return void
     */
    public function __construct(Volume $volume, $distance, $labelId)
    {
        parent::__construct($distance, $labelId);
        $this->volume = $volume;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $points = $this->getLaserpointsForVolume($this->volume->id);
        $images = Image::whereIn('id', $points->keys())->get();

        $jobs = $images->map(function ($image) use ($points) {
            return new ProcessManualJob($image, $points->get($image->id), $this->distance);
        });

        Bus::batch($jobs)
            ->onQueue(config('laserpoints.process_manual_queue'))
            ->dispatch();

        $query = $this->volume->images()->whereNotIn('id', $points->keys());

        if ($query->exists()) {
            $images = $query->get();
            $gatherFile = $this->gather($points);

            $jobs = $images->map(function ($image) use ($gatherFile) {
                return new ProcessDelphiJob($image, $this->distance, $gatherFile);
            });

            Bus::batch($jobs)
                ->onQueue(config('laserpoints.process_delphi_queue'))
                ->finally(function () use ($gatherFile) {
                    Storage::disk(config('laserpoints.disk'))->delete($gatherFile);
                })
                ->dispatch();
        }
    }
}
