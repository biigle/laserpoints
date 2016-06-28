<?php

namespace Dias\Modules\Laserpoints\Jobs;

use Dias\Jobs\Job;
use DB;
use Dias\Modules\Laserpoints\Image;
use Dias\Annotation;
use Dias\Transect;
use Dias\Shape;
use Dias\Modules\Laserpoints\Support\Detect;
use Exception;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ComputeAreaForImages extends Job implements ShouldQueue
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
     * IDs of images to restrict this job to
     *
     * @var array
     */
    private $only;

    /**
     * Create a new job instance.
     *
     * @param Transect $transect
     * @param float $distance
     * @param array $only IDs of the images to restrict this job to
     *
     * @return void
     */
    public function __construct(Transect $transect, $distance, array $only = [])
    {
        $this->transect = $transect;
        $this->distance = $distance;
        $this->only = $only;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $labelId = config('laserpoints.label_id');
        $detect = app()->make(Detect::class);

        if (empty($this->only)) {
            $query = Image::where('transect_id', $this->transect->id);
        } else {
            $query = Image::where('transect_id', $this->transect->id)
                ->whereIn('id', $this->only);
        }

        $query->chunkById(500, function ($images) use ($detect, $labelId) {
            $this->handleBatch($images, $detect, $labelId);
        });
    }

    private function handleBatch($images, $detect, $labelId)
    {
        // get all laserpoint annotations of this batch
        $points = DB::table('annotations')
            ->join('annotation_labels', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->whereIn('annotations.image_id', $images->pluck('id')->toArray())
            ->where('annotation_labels.label_id', $labelId)
            ->where('annotations.shape_id', Shape::$pointId)
            ->select('annotations.points', 'annotations.image_id')
            ->get();

        // map of image IDs to all laserpoint coordinates on the image
        $points = collect($points)->groupBy('image_id');

        foreach ($images as $image) {

            if ($points->has($image->id)) {
                $imagePoints = '['.$points[$image->id]->pluck('points')->implode(',').']';
            } else {
                $imagePoints = '[]';
            }

            $output = [];

            try {
                $output = $detect->execute(
                    "{$this->transect->url}/{$image->filename}",
                    $this->distance,
                    $imagePoints
                );

                $output['error'] = false;
            } catch (Exception $e) {
                $output['error'] = true;
            }

            $output['distance'] = $this->distance;

            $image->laserpoints = $output;
            $image->save();
        }

    }
}
