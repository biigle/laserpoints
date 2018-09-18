<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Cache;
use Biigle\Volume;
use Illuminate\Foundation\Bus\DispatchesJobs;

class ProcessVolumeDelphiJob extends Job
{
    use DispatchesJobs;

    /**
     * Number of images to process in one chunk.
     *
     * @var int
     */
    const CHUNK_SIZE = 10;

    /**
     * Number of images to process in one chunk.
     *
     * Duplicated as member variable so it can be changed during testing.
     *
     * @var int
     */
    public $chunkSize;

    /**
     * The volume to process the images of.
     *
     * @var Volume
     */
    protected $volume;

    /**
     * Create a new job instance.
     *
     * @param Volume $volume
     * @param float $distance
     *
     * @return void
     */
    public function __construct(Volume $volume, $distance)
    {
        parent::__construct($distance);
        $this->volume = $volume;
        $this->chunkSize = self::CHUNK_SIZE;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->volume) {
            // The volume was deleted in the meantime.
            return;
        }

        $points = $this->getLaserpointsForVolume($this->volume->id);

        // We chunk this job into multiple smaller jobs because volumes can become
        // really large. Multiple smaller jobs can be better parallelized with multiple
        // queue workers and each one does not run very long (in case there is a hard
        // limit on the runtime of a job).
        foreach ($points->chunk($this->chunkSize) as $chunk) {
            $this->dispatch(new ProcessManualChunkJob($chunk, $this->distance));
        }

        $gatherFile = $this->gather($points);

        $imageChunksToProcess = $this->volume->images()
            ->whereNotIn('id', $points->keys())
            ->pluck('id')
            ->chunk($this->chunkSize);

        $cacheKey = uniqid('delphi_job_count_');
        Cache::forever($cacheKey, $imageChunksToProcess->count());

        foreach ($imageChunksToProcess as $chunk) {
            $this->dispatch(new ProcessDelphiChunkJob($chunk, $this->distance, $gatherFile, $cacheKey));
        }
    }
}
