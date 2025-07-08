<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use App;
use Biigle\Image;
use Biigle\ImageAnnotation;
use Biigle\Jobs\Job as BaseJob;
use Biigle\Modules\Laserpoints\Traits\FiltersInvalidLaserPoints;
use Biigle\Shape;
use DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

abstract class Job extends BaseJob implements ShouldQueue
{
    use InteractsWithQueue, FiltersInvalidLaserPoints;

    /**
     * Distance between laser points im cm to use for computation.
     *
     * @var float
     */
    protected $distance;

    /**
     * ID of the laser point label.
     *
     * @var int
     */
    protected $labelId;

    /**
     * Create a new job instance.
     *
     * @param float $distance
     * @param int $labelId
     *
     * @return void
     */
    public function __construct($distance, $labelId)
    {
        $this->distance = $distance;
        $this->labelId = $labelId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    abstract public function handle();

    /**
     * Collects all images of a volume that contain laser point annotations.
     *
     * @param int $id Volume ID
     *
     * @return Collection Laser point coordinates indexed by image ID
     */
    protected function getLaserpointsForVolume($id)
    {
        return ImageAnnotation::join('image_annotation_labels', 'image_annotation_labels.annotation_id', '=', 'image_annotations.id')
            ->join('images', 'image_annotations.image_id', '=', 'images.id')
            ->where('images.volume_id', $id)
            ->where('image_annotation_labels.label_id', $this->labelId)
            ->where('image_annotations.shape_id', Shape::pointId())
            ->select('image_annotations.points', 'image_annotations.image_id')
            ->get()
            ->groupBy('image_id')
            ->pipe([$this, 'filterInvalidLaserPoints'])
            ->map(function ($i) {
                return $i->pluck('points')->toJson();
            });
    }
}
