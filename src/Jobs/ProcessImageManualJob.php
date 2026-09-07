<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Biigle\Jobs\Job;
use Biigle\Label;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DetectManual;
use Biigle\Shape;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[DeleteWhenMissingModels]
class ProcessImageManualJob extends Job implements ShouldQueue
{
    use Batchable, InteractsWithQueue, SerializesModels;

    public $tries = 1;

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
            $detect = App::make(DetectManual::class);
            $output = $detect->execute(
                $this->image->width,
                $this->image->height,
                $this->distance,
                $this->getLaserpoints()
            );
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
     * @return array Array of annotation coordinates as `[x, y]` pairs
     */
    protected function getLaserpoints()
    {
        return $this->image->annotations()
            ->join('image_annotation_labels', 'image_annotation_labels.annotation_id', '=', 'image_annotations.id')
            ->where('image_annotation_labels.label_id', $this->label->id)
            ->where('image_annotations.shape_id', Shape::pointId())
            ->pluck('image_annotations.points')
            ->toArray();
    }
}
