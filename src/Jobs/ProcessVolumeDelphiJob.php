<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeLinesJob;
use Biigle\Volume;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

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
     * Whether to use line detection mode.
     *
     * @var bool
     */
    protected $useLineDetection;

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
     * @param int|null $labelId
     * @param bool $useLineDetection
     *
     * @return void
     */
    public function __construct(Volume $volume, $distance, $labelId = null, $useLineDetection = true)
    {
        parent::__construct($distance, $labelId);
        $this->volume = $volume;
        $this->useLineDetection = $useLineDetection;
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

        // Capture distance for use in closures (avoid serialization issues)
        $distance = $this->distance;

        // Process manually annotated images first
        $jobs = $images->map(function ($image) use ($points, $distance) {
            return new ProcessManualJob($image, $points->get($image->id), $distance);
        });

        Bus::batch($jobs)
            ->onQueue(config('laserpoints.process_manual_queue'))
            ->dispatch();

        $query = $this->volume->images()->whereNotIn('id', $points->keys());

        if ($query->exists()) {
            $remainingImages = $query->get();
            
            if ($this->useLineDetection) {
                // Line detection mode: fit lines first, then detect using those lines
                $lineFittingJob = new ProcessVolumeLinesJob($this->volume, $distance, $this->labelId);
                $lineFittingJob->handle(); // Run synchronously to ensure lines are created before detection
                
                // Capture values for use in closures (avoid serialization issues)
                $volumeId = $this->volume->id;
                
                // Now create jobs for detecting laser points using the fitted lines
                $detectionJobs = $remainingImages->map(function ($image) use ($distance, $volumeId) {
                    return new ProcessDelphiJob($image, $distance, null, $volumeId);
                });
                
                // Run detection jobs in batch
                Bus::batch($detectionJobs)
                    ->onQueue(config('laserpoints.process_delphi_queue'))
                    ->finally(function () use ($volumeId) {
                        // Clean up the cached lines file after all detection jobs are done
                        $cacheKey = "laserpoint_lines_volume_{$volumeId}";
                        $linesFile = Cache::get($cacheKey);
                        if ($linesFile) {
                            Storage::disk(config('laserpoints.disk'))->delete($linesFile);
                            Cache::forget($cacheKey);
                        }
                    })
                    ->dispatch();
            } else {
                // Regular detection mode: detect without line constraints
                $detectionJobs = $remainingImages->map(function ($image) use ($distance) {
                    return new ProcessDelphiJob($image, $distance, null, null);
                });
                
                // Run detection jobs in batch
                Bus::batch($detectionJobs)
                    ->onQueue(config('laserpoints.process_delphi_queue'))
                    ->dispatch();
            }
        }
    }
}
