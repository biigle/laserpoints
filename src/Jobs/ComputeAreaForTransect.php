<?php

namespace Dias\Modules\Laserpoints\Jobs;

use Dias\Jobs\Job;
use Dias\Modules\Laserpoints\Image;
use Dias\Annotation;
use Dias\Modules\Laserpoints\Support\Detect;
use Exception;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ComputeAreaForTransect extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * The transect to compute the area for
     *
     * @var Transect
     */
    private $transect;

    /**
     * Distance between laserpoints im cm to use for computation
     *
     * @var float
     */
    private $distance;

    /**
     * Create a new job instance.
     *
     * @param Transect $transect
     * @param float $distance
     *
     * @return void
     */
    public function __construct(Transect $transect, $distance)
    {
        $this->transect = $transect;
        $this->distance = $distance;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $transect = $this->image->transect;
        $laserpointId = config('laserpoints.label_id');

        $images = $this->transect->images()
            ->pluck('filename', 'id');

        dd($images);

        foreach ($variable as $key => $value) {
            # code...
        }

        // all laserpoint coordinates of the image
        $annotationPoints = Annotation::join('annotation_labels', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->where('annotations.image_id', $this->image->id)
            ->where('annotation_labels.label_id', $laserpointId)
            ->pluck('annotations.points');

        $annotationPoints = '['.$annotationPoints->implode(',').']';

        $detect = app()->make(Detect::class);

        $output = [];

        try {
            $output = $detect->execute(
                "{$transect->url}/{$this->image->filename}",
                $this->distance,
                $annotationPoints
            );

            $output['error'] = false;
        } catch (Exception $e) {
            $output['error'] = true;
        }

        $output['distance'] = $this->distance;

        $this->image->laserpoints = $output;
        $this->image->save();
    }
}
