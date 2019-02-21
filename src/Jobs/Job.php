<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use DB;
use App;
use FileCache;
use Biigle\Image;
use Biigle\Shape;
use Biigle\Jobs\Job as BaseJob;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Biigle\Modules\Laserpoints\Support\DelphiGather;

abstract class Job extends BaseJob implements ShouldQueue
{
    use InteractsWithQueue;

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
        return DB::table('annotations')
            ->join('annotation_labels', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->join('images', 'annotations.image_id', '=', 'images.id')
            ->where('images.volume_id', $id)
            ->where('annotation_labels.label_id', $this->labelId)
            ->where('annotations.shape_id', Shape::pointId())
            ->select('annotations.points', 'annotations.image_id')
            ->get()
            ->groupBy('image_id')
            ->map(function ($i) {
                return '['.$i->pluck('points')->implode(',').']';
            });
    }

    /**
     * Perform the gather step.
     *
     * @param Collection $points Points Collection returned from getLaserpointsForVolume.
     *
     * @return string Path to the gather file.
     */
    protected function gather($points)
    {
        $images = Image::whereIn('id', $points->keys())
            ->with('volume')
            ->select('filename', 'id', 'volume_id')
            // Take only a maximum of 100 images for delphi_gather.
            ->inRandomOrder()
            ->limit(100)
            ->get();

        $gather = App::make(DelphiGather::class);
        $callback = function ($image, $path) use ($gather, $points) {
            return $gather->execute($path, $points->get($image->id));
        };

        foreach ($images as $image) {
            FileCache::getOnce($image, $callback);
        }

        $gather->finish();

        return $gather->getOutputPath();
    }
}
