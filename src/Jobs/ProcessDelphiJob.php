<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Biigle\Jobs\Job as BaseJob;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DelphiApply;
use Exception;
use FileCache;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProcessDelphiJob extends BaseJob implements ShouldQueue
{
    use Batchable, InteractsWithQueue, SerializesModels;

    /**
     * The image to process.
     *
     * @var Image
     */
    protected $image;

    /**
     * Path to the output of the Delphi gather script or lines file.
     *
     * @var string|null
     */
    protected $gatherFile;

    /**
     * Volume ID for accessing cached lines file.
     *
     * @var int|null
     */
    protected $volumeId;

    /**
     * Distance between laser points im cm to use for computation.
     *
     * @var float
     */
    protected $distance;

    /**
     * Ignore this job if the image does not exist any more.
     *
     * @var bool
     */
    protected $deleteWhenMissingModels = true;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param Image $image
     * @param float $distance
     * @param string|null $gatherFile
     * @param int|null $volumeId
     *
     * @return void
     */
    public function __construct($image, $distance, $gatherFile = null, $volumeId = null)
    {
        $this->image = Image::convert($image);
        $this->gatherFile = $gatherFile;
        $this->distance = $distance;
        $this->volumeId = $volumeId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Determine the lines file path - either from parameter or cache
        $linesFilePath = $this->gatherFile;
        
        if (!$linesFilePath && $this->volumeId) {
            // Try to get lines file from cache if volume ID is provided
            $cacheKey = "laserpoint_lines_volume_{$this->volumeId}";
            $linesFilePath = Cache::get($cacheKey);
        }

        $callback = function ($image, $path) use ($linesFilePath) {
            $delphi = App::make(DelphiApply::class);
            return $delphi->execute($linesFilePath, $path, $this->distance);
        };

        try {
            $output = FileCache::getOnce($this->image, $callback);
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
