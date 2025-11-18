<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Biigle\Jobs\Job;
use Biigle\Label;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Shape;
use Biigle\Volume;
use Illuminate\Queue\SerializesModels;

class ProcessVolumeManualJob extends Job
{
    use SerializesModels;

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
     * @param Volume $volume The volume to process the images of.
     * @param Label $label The laser point label.
     * @param float $distance Distance between laser points im cm to use for computation.
     *
     * @return void
     */
    public function __construct(
        protected Volume $volume,
        protected Label $label,
        protected float $distance,
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
        Image::where('volume_id', $this->volume->id)
            ->join('image_annotations', 'images.id', '=', 'image_annotations.image_id')
            ->join('image_annotation_labels', 'image_annotation_labels.annotation_id', '=', 'image_annotations.id')
            ->where('image_annotation_labels.label_id', $this->label->id)
            ->where('image_annotations.shape_id', Shape::pointId())
            ->select('images.id as images_id')
            ->distinct()
            ->eachById(function ($image) {
                // Reassign the ID because the ambiguous column had to use an alias.
                $image->id = $image->images_id;
                ProcessImageManualJob::dispatch($image, $this->label, $this->distance)
                    ->onQueue(config('laserpoints.process_manual_queue'));
            }, 1000, 'images.id', 'images_id');
    }
}
