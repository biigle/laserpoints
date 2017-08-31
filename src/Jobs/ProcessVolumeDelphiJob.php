<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use File;
use Queue;
use Biigle\Volume;
use Biigle\Modules\Laserpoints\Support\DelphiGather;

class ProcessVolumeDelphiJob extends Job
{
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

        $url = $this->volume->url;
        $points = $this->getLaserpointsForVolume($this->volume->id);

        // We chunk this job into multiple smaller jobs because volumes can become
        // really large. Multiple smaller jobs can be better parallelized with multiple
        // queue workers and each one does not run very long (in case there is a hard
        // limit on the runtime of a job).
        foreach ($points->chunk($this->chunkSize) as $chunk) {
            Queue::push(new ProcessManualChunkJob($url, $chunk, $this->distance));
        }

        // After submitting the images for manual detection, prepare the jobs for Delphi
        // detection.

        $points = $points->map(function ($pts) {
            return json_decode('['.$pts->implode(',').']');
        });

        $images = $this->volume->images()
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

        $imagesToProcess = $this->volume->images()
            ->whereNotIn('id', $points->keys())
            ->pluck('id')
            ->chunk($this->chunkSize);

        $tmpDir = config('laserpoints.tmp_dir');
        if (!File::isDirectory($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }
        $indexFile = tempnam($tmpDir, 'biigle_delphi_job_indices');
        File::put($indexFile, json_encode($imagesToProcess->keys()));

        foreach ($imagesToProcess as $index => $chunk) {
            Queue::push(new ProcessDelphiChunkJob($url, $chunk, $this->distance, $gatherFile, $indexFile, $index));
        }
    }
}
