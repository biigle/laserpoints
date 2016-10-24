<?php

namespace Dias\Modules\Laserpoints\Jobs;

use DB;
use Exception;
use Dias\Shape;
use Dias\Jobs\Job;
use Dias\Modules\Laserpoints\Image;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dias\Modules\Laserpoints\Support\Detect;

class ProcessChunk extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * URL of the transect the images belong to.
     *
     * @var string
     */
    protected $transectUrl;

    /**
     * IDs of all images that should be processed in this chunk.
     *
     * @var array
     */
    protected $ids;

    /**
     * Distance between laserpoints im cm to use for computation
     *
     * @var float
     */
    private $distance;

    /**
     * Create a new job instance.
     *
     * @param string $transectUrl
     * @param array $ids
     * @param float $distance
     *
     * @return void
     */
    public function __construct($transectUrl, $ids, $distance)
    {
        $this->transectUrl = $transectUrl;
        $this->ids = $ids;
        $this->distance = $distance;
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

        // get all laserpoint annotations of this chunk
        $points = DB::table('annotations')
            ->join('annotation_labels', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->whereIn('annotations.image_id', $this->ids)
            ->where('annotation_labels.label_id', $labelId)
            ->where('annotations.shape_id', Shape::$pointId)
            ->select('annotations.points', 'annotations.image_id')
            ->get();

        // map of image IDs to all laserpoint coordinates on the image
        $points = collect($points)->groupBy('image_id');

        $images = Image::whereIn('id', $this->ids)->select('id', 'filename')->get();

        foreach ($images as $image) {

            $imagePoints = '[';
            if ($points->has($image->id)) {
                $imagePoints .= $points[$image->id]->pluck('points')->implode(',');
            }
            $imagePoints .= ']';

            $output = [];

            try {
                $output = $detect->execute(
                    "{$this->transectUrl}/{$image->filename}",
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
