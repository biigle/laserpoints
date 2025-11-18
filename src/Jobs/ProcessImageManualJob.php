<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Biigle\Jobs\Job;
use Biigle\Label;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DetectManual;
use Biigle\Shape;
use Exception;
use FileCache;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImageManualJob extends Job implements ShouldQueue
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
     * @param Label $label The laser point label.
     * @param float $distance Distance between laser points im cm to use for computation.
     *
     * @return void
     */
    public function __construct(
        public Image $image,
        public Label $label,
        public float $distance,
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
            // TODO implement this without loading the image. dimensions are available in the image model. this can be enabled for tiled images too then.
            $output = FileCache::get($this->image, function ($image, $path) {
                $detect = App::make(DetectManual::class);
                $points = $this->getLaserpoints();

                return $detect->execute($path, $this->distance, $points);
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

    /**
     * Collects the laser point annotations of the given image.
     *
     * @return string JSON encoded array of annotation coordinates
     */
    protected function getLaserpoints()
    {
        return $this->image->annotations()
            ->join('image_annotation_labels', 'image_annotation_labels.annotation_id', '=', 'image_annotations.id')
            ->where('image_annotation_labels.label_id', $this->label->id)
            ->where('image_annotations.shape_id', Shape::pointId())
            ->pluck('image_annotations.points')
            ->toJson();
    }
}
