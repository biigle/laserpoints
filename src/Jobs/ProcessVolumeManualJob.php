<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use Biigle\Jobs\Job;
use Biigle\Label;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Shape;
use Biigle\Volume;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\SerializesModels;

#[DeleteWhenMissingModels]
class ProcessVolumeManualJob extends Job
{
    use Batchable, SerializesModels;

    public $tries = 1;

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
        // The volume lock is released by the batch that this job is part of.
        Image::where('volume_id', $this->volume->id)
            ->join('image_annotations', 'images.id', '=', 'image_annotations.image_id')
            ->join('image_annotation_labels', 'image_annotation_labels.annotation_id', '=', 'image_annotations.id')
            ->where('image_annotation_labels.label_id', $this->label->id)
            ->where('image_annotations.shape_id', Shape::pointId())
            ->select('images.id as images_id', 'images.volume_id')
            ->distinct()
            ->chunkById(1000, function ($images) {
                $jobs = $images->map(function ($image) {
                    // Reassign the ID because the ambiguous column had to use an alias.
                    $image->id = $image->images_id;

                    return new ProcessImageManualJob($image, $this->label, $this->distance, batch: true);
                });

                $this->batch()->add($jobs->all());
            }, 'images.id', 'images_id');
    }
}
