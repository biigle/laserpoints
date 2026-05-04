<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Biigle\Jobs\Job;
use Biigle\Shape;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DetectAutomatic;
use Exception;
use FileCache;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImageAutomaticJob extends Job implements ShouldQueue
{
    use Batchable, InteractsWithQueue, SerializesModels;

    public $tries = 1;

    /**
     * Ignore this job if the image does not exist any more.
     *
     * @var bool
     */
    protected $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param Image $image The image to process.
     * @param float $distance Distance between laser points im cm to use for computation.
     * @param ?string $channelMode Channel mode override (red/green/blue/gray)
     * @param int $numLaserpoints Number of laser points to search for.
     *
     * @return void
     */
    public function __construct(
        public Image $image,
        public float $distance,
        public ?string $channelMode = null,
        public int $numLaserpoints = 2,
    )
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $output = FileCache::get($this->image, function ($image, $path) {
                $detect = App::make(DetectAutomatic::class);

                return $detect->execute($path, $this->distance, $this->channelMode, $this->numLaserpoints);
            });
        } catch (Exception $e) {
            $output = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        $output['distance'] = $this->distance;

        $this->image->laserpoints = $output;
        $this->image->save();
    }
}
