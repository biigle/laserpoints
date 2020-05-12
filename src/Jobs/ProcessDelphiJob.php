<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use File;
use Cache;
use Storage;
use Exception;
use FileCache;
use Biigle\Jobs\Job as BaseJob;
use Biigle\Modules\Laserpoints\Image;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Biigle\Modules\Laserpoints\Support\DelphiApply;

class ProcessDelphiJob extends BaseJob implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * The image to process.
     *
     * @var Image
     */
    protected $image;

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
     * @param string $gatherFile
     * @param string $cacheKey
     *
     * @return void
     */
    public function __construct($image, $distance, $gatherFile, $cacheKey = null)
    {
        $this->image = Image::convert($image);
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
        $tmpDir = config('laserpoints.tmp_dir');
        $localGatherPath = "{$tmpDir}/{$this->gatherFile}";
        $stream = Storage::disk(config('laserpoints.disk'))
            ->readStream($this->gatherFile);
        File::put($localGatherPath, $stream);

        $callback = function ($image, $path) use ($localGatherPath) {
            $delphi = App::make(DelphiApply::class);

            return $delphi->execute($localGatherPath, $path, $this->distance);
        };

        try {
            $output = FileCache::getOnce($this->image, $callback);
        } catch (Exception $e) {
            $output = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        } finally {
            File::delete($localGatherPath);
        }

        $output['distance'] = $this->distance;

        $this->image->laserpoints = $output;
        $this->image->save();

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
     * If more than one image is processed during a Delphi LP detection, the jobs use the
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

        Storage::disk(config('laserpoints.disk'))->delete($this->gatherFile);
    }
}
