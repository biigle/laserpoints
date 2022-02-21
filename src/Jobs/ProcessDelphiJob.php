<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Biigle\Jobs\Job as BaseJob;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DelphiApply;
use Cache;
use Exception;
use File;
use FileCache;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Storage;

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
    public function __construct($image, $distance, $gatherFile)
    {
        $this->image = Image::convert($image);
        $this->gatherFile = $gatherFile;
        $this->distance = $distance;
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
    }
}
