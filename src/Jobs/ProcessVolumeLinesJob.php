<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\LaserLineFitter;
use Biigle\Volume;
use Exception;
use FileCache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Storage;

class ProcessVolumeLinesJob extends Job
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
        // Get all images from the volume for line fitting
        $allImages = $this->volume->images()->get();
        
        if ($allImages->count() < 100) {
            Log::warning("Volume {$this->volume->id} has fewer than 100 images. Using all images for line fitting.");
        }

        $lineFitter = app(LaserLineFitter::class);
        
        try {
            $linesFile = $lineFitter->execute($allImages, $this->distance);
            
            // Store the lines file path in cache for later use by ProcessDelphiJob
            $cacheKey = "laserpoint_lines_volume_{$this->volume->id}";
            Cache::put($cacheKey, $linesFile, now()->addHours(24)); // Cache for 24 hours
            
            Log::info("Successfully created line fitting file for volume {$this->volume->id}: {$linesFile}");
            
        } catch (Exception $e) {
            Log::error("Failed to create line fitting for volume {$this->volume->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
