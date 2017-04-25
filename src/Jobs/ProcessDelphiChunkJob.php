<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use File;
use Exception;
use Biigle\Jobs\Job;
use Biigle\Modules\Laserpoints\Image;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Biigle\Modules\Laserpoints\Support\DelphiApply;

class ProcessDelphiChunkJob extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * URL of the volume the images belong to.
     *
     * @var string
     */
    protected $volumeUrl;

    /**
     * IDs of the images to process.
     *
     * @var Collection
     */
    protected $images;

    /**
     * Path to the output of the Delphi gather script.
     *
     * @var string
     */
    protected $gatherFile;

    /**
     * Distance between laserpoints im cm to use for computation.
     *
     * @var float
     */
    protected $distance;

    /**
     * Path to the file tracking the running "sibling" jobs accessing the same gatherFile.
     *
     * @var string
     */
    protected $indexFile;

    /**
     * Index of this job among the "sibling" jobs.
     *
     * @var int
     */
    protected $index;

    /**
     * Create a new job instance.
     *
     * @param string $volumeUrl
     * @param Collection $images
     * @param float $distance
     * @param string $gatherFile
     * @param string $indexFile
     * @param int $index
     *
     * @return void
     */
    public function __construct($volumeUrl, $images, $distance, $gatherFile, $indexFile = null, $index = null)
    {
        $this->volumeUrl = $volumeUrl;
        $this->images = $images;
        $this->gatherFile = $gatherFile;
        $this->distance = $distance;
        // If null this job assumes it is the only one accessing the gatherFile.
        $this->indexFile = $indexFile;
        $this->index = $index;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $delphi = app()->make(DelphiApply::class);

        $images = Image::whereIn('id', $this->images)
            ->select('id', 'filename')
            ->get();

        foreach ($images as $image) {
            try {
                $output = $delphi->execute(
                    $this->gatherFile,
                    "{$this->volumeUrl}/{$image->filename}",
                    $this->distance
                );
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
     * index file to track how many of them are still running. They need to track this to
     * determine when the gatherFile can be deleted. This function updates the index file
     * when a job was finished and deletes the index and gather files if this is the
     * last job to finish.
     */
    protected function maybeDeleteGatherFile()
    {
        $delete = true;

        if ($this->indexFile) {
            $handle = fopen($this->indexFile, 'r+');
            // We need an exclusive lock for this because the "sibling" jobs may run in
            // parallel.
            if (flock($handle, LOCK_EX)) {
                $running = json_decode(fgets($handle), true);
                // Remove the index of this job.
                $running = array_values(array_filter($running, function ($i) {
                    return $i !== $this->index;
                }));

                if (empty($running)) {
                    File::delete($this->indexFile);
                } else {
                    $delete = false;
                    rewind($handle);
                    ftruncate($handle, 0);
                    fwrite($handle, json_encode($running));
                }
                flock($handle, LOCK_UN);
                fclose($handle);
            } else {
                // Should not happen. Either way we can't do anything meaningful if this
                // happens except not deleting the file so it can't break any other jobs.
                $delete = false;
            }
        }

        if ($delete) {
            File::delete($this->gatherFile);
        }
    }
}
