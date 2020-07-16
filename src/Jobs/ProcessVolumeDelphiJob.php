<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Biigle\Modules\Laserpoints\Image;
use Biigle\Volume;
use Cache;
use Illuminate\Queue\SerializesModels;

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

        foreach ($images as $image) {
            ProcessManualJob::dispatch($image, $points->get($image->id), $this->distance)
                ->onQueue(config('laserpoints.process_manual_queue'));
        }


        $query = $this->volume->images()->whereNotIn('id', $points->keys());

        if ($query->exists()) {
            $imagesToProcess = $query->get();
            $gatherFile = $this->gather($points);

            $cacheKey = uniqid('delphi_job_count_');
            Cache::forever($cacheKey, $imagesToProcess->count());

            foreach ($imagesToProcess as $image) {
                ProcessDelphiJob::dispatch($image, $this->distance, $gatherFile, $cacheKey)
                    ->onQueue(config('laserpoints.process_delphi_queue'));
            }
        }
    }
}
