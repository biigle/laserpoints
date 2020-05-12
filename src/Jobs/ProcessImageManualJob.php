<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use DB;
use Biigle\Shape;
use Biigle\Image;
use Illuminate\Queue\SerializesModels;

class ProcessImageManualJob extends Job
{
    use SerializesModels;

    /**
     * The image to compute the area for.
     *
     * @var Image
     */
    protected $image;

    /**
     * Ignore this job if the image does not exist any more.
     *
     * @var bool
     */
    protected $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param Image $image
     * @param float $distance
     * @param int $labelId
     *
     * @return void
     */
    public function __construct(Image $image, $distance, $labelId)
    {
        parent::__construct($distance, $labelId);
        $this->image = $image;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $points = $this->getLaserpointsForImage($this->image->id);
        ProcessManualJob::dispatch($this->image, $points, $this->distance)
            ->onQueue(config('laserpoints.process_manual_queue'));
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
        $points = DB::table('annotations')
            ->join('annotation_labels', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->where('annotations.image_id', $id)
            ->where('annotation_labels.label_id', $this->labelId)
            ->where('annotations.shape_id', Shape::pointId())
            ->select('annotations.points', 'annotations.image_id')
            ->pluck('annotations.points');

        return '['.$points->implode(',').']';
    }
}
