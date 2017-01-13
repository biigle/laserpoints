<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Queue;
use Biigle\Volume;
use Biigle\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class LaserpointDetection extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * Number of images to process in one chunk.
     *
     * @var int
     */
    const CHUNK_SIZE = 10;

    /**
     * The volume to compute the area for
     *
     * @var Volume
     */
    private $volume;

    /**
     * Distance between laserpoints im cm to use for computation
     *
     * @var float
     */
    private $distance;

    /**
     * IDs of images to restrict this job to
     *
     * @var array
     */
    private $only;

    /**
     * Create a new job instance.
     *
     * @param Volume $volume
     * @param float $distance
     * @param array $only IDs of the images to restrict this job to
     *
     * @return void
     */
    public function __construct(Volume $volume, $distance, array $only = [])
    {
        $this->volume = $volume;
        $this->distance = $distance;
        $this->only = $only;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->volume) {
            return;
        }

        $query = $this->volume->images();

        if (!empty($this->only)) {
            $query = $query->whereIn('id', $this->only);
        }

        $ids = $query->pluck('id');

        // We chunk this job into multiple smaller jobs because volumes can become
        // really large. Multiple smaller jobs can be better parallelized with multiple
        // queue workers and each one does not run very long (in case there is a hard
        // limit on the runtime of a job).
        foreach ($ids->chunk(self::CHUNK_SIZE) as $chunk) {
            Queue::push(new ProcessChunk($this->volume->url, $chunk, $this->distance));
        }
    }
}
