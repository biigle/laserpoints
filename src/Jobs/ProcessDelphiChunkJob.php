<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use File;
use Cache;
use Exception;
use FileCache;
use Biigle\Jobs\Job as BaseJob;
use Biigle\Modules\Laserpoints\Image;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Biigle\Modules\Laserpoints\Support\DelphiApply;

class ProcessDelphiChunkJob extends BaseJob implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * IDs of the images to process.
     *
     * @var array
     */
    protected $images;

    /**
     * Path to the output of the Delphi gather script.
     *
     * @var string
     */
    protected $gatherFile;

    /**
     * Distance between laser points im cm to use for computation.
     *
     * @var float
     */
    protected $distance;

    /**
     * Key of the cached count of other jobs that run on the same volume than this one.
     * The last job should delete the gatherFile.
     *
     * @var string
     */
    protected $cacheKey;

    /**
     * Create a new job instance.
     *
     * @param string $volumeUrl
     * @param array $images
     * @param float $distance
     * @param string $gatherFile
     * @param string $cacheKey
     *
     * @return void
     */
    public function __construct($images, $distance, $gatherFile, $cacheKey = null)
    {
        $this->images = $images;
        $this->gatherFile = $gatherFile;
        $this->distance = $distance;
        // If null, this job assumes it is the only one accessing the gatherFile.
        $this->cacheKey = $cacheKey;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $delphi = App::make(DelphiApply::class);
        $images = Image::whereIn('id', $this->images)
            ->with('volume')
            ->select('id', 'filename', 'volume_id')
            ->get();

        $callback = function ($image, $path) use ($delphi) {
            return $delphi->execute($this->gatherFile, $path, $this->distance);
        };

        foreach ($images as $image) {
            try {
                $output = FileCache::getOnce($image, $callback);
            } catch (Exception $e) {
                $output = [
                    'error' => true,
                    'message' => $e->getMessage(),
                ];
            }

            $output['distance'] = $this->distance;

            $image->laserpoints = $output;
            $image->save();
        }

        $this->maybeDeleteGatherFile();
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed()
    {
        $this->maybeDeleteGatherFile();
    }

    /**
     * Handles the deletion of the gatherFile once all "sibling" jobs finished.
     *
     * If more than one chunk is processed during a Delphi LP detection, the jobs use the
     * cache to track how many of them are still running. They need to track this to
     * determine when the gatherFile can be deleted. This function updates the count
     * when a job was finished and deletes the gather file if this is the last job to
     * finish.
     */
    protected function maybeDeleteGatherFile()
    {
        if ($this->cacheKey) {
            // This requires an atomic operation to work correctly. BIIGLE uses the
            // Redis cache which provides these atomic operations.
            if (Cache::decrement($this->cacheKey) > 0) {
                return;
            }
            Cache::forget($this->cacheKey);
        }

        File::delete($this->gatherFile);
    }
}
