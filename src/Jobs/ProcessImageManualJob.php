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
        // Use an exists constraint instead of a join because the same label can be
        // attached to the same annotation by multiple users. A join would return the
        // points of these annotations more than once, which would distort the computed
        // image area.
        return $this->image->annotations()
            ->where('shape_id', Shape::pointId())
            ->whereHas('labels', fn ($query) => $query->where('label_id', $this->label->id))
            ->orderBy('id')
            ->pluck('points')
            ->toArray();
    }
}
