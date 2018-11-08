<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use DB;
use Biigle\Shape;
use Biigle\Image;

class ProcessImageManualJob extends Job
{
    /**
     * The image to compute the area for.
     *
     * @var Image
     */
    protected $image;

    /**
     * Create a new job instance.
     *
     * @param Image $image
     * @param float $distance
     *
     * @return void
     */
    public function __construct(Image $image, $distance)
    {
        parent::__construct($distance);
        $this->image = $image;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // The image may be deleted in the meantime.
        if (!$this->image) {
            return;
        }

        $points = $this->getLaserpointsForImage($this->image->id);
        $points = collect([$this->image->id => $points]);
        ProcessManualChunkJob::dispatch($points, $this->distance);
    }

    /**
     * Collects the laser point annotations of the given image.
     *
     * @param int $id Image ID
     *
     * @return string JSON encoded array of annotation coordinates
     */
    protected function getLaserpointsForImage($id)
    {
        $labelId = config('laserpoints.label_id');

        $points = DB::table('annotations')
            ->join('annotation_labels', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->where('annotations.image_id', $id)
            ->where('annotation_labels.label_id', $labelId)
            ->where('annotations.shape_id', Shape::pointId())
            ->select('annotations.points', 'annotations.image_id')
            ->pluck('annotations.points');

        return '['.$points->implode(',').']';
    }
}
