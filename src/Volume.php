<?php

namespace Biigle\Modules\Laserpoints;

use DB;
use Exception;
use Biigle\Shape;
use Biigle\Volume as BaseVolume;

/**
 * Extends the base Biigle volume.
 */
class Volume extends BaseVolume
{
    /**
     * Minimum number of manually annotated images required for Delphi laser point
     * detection.
     *
     * @var int
     */
    const MIN_DELPHI_IMAGES = 4;

    /**
     * Converts a regular Biigle volume to a Laserpoints volume.
     *
     * @param BaseVolume $volume Regular Biigle volume instance
     *
     * @return Volume
     */
    public static function convert(BaseVolume $volume)
    {
        $instance = new static;
        $instance->setRawAttributes($volume->attributes);
        $instance->exists = $volume->exists;

        return $instance->setRelations($volume->relations);
    }

    /**
     * Determines if the images of this volume can be processed with Delphi.
     *
     * @throws Exception If the images of this volume can't be processed with Delphi
     */
    public function readyForDelphiDetection()
    {
        $labelId = config('laserpoints.label_id');
        $points = DB::table('annotations')
            ->join('annotation_labels', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->join('images', 'annotations.image_id', '=', 'images.id')
            ->where('images.volume_id', $this->id)
            ->where('annotation_labels.label_id', $labelId)
            ->where('annotations.shape_id', Shape::pointId())
            ->select(DB::raw('count(annotation_labels.id) as count'))
            ->groupBy('images.id')
            ->pluck('count');

        if ($points->count() < self::MIN_DELPHI_IMAGES) {
            throw new Exception('Only '.$points->count().' images have manually annotated laser points. At least '.self::MIN_DELPHI_IMAGES.' are required.');
        }

        $reference = $points[0];
        if ($reference < Image::MIN_MANUAL_POINTS) {
            throw new Exception('There must be at least '.Image::MIN_MANUAL_POINTS.' manually annotated laser points per image ('.$reference.' found).');
        }

        if ($reference > Image::MAX_MANUAL_POINTS) {
            throw new Exception('There can\'t be more than '.Image::MAX_MANUAL_POINTS.' manually annotated laser points per image ('.$reference.' found).');
        }

        foreach ($points as $count) {
            if ($reference !== $count) {
                throw new Exception('Images must have equal count of manually annotated laser points.');
            }
        }
    }

    /**
     * Determines whether there are images with automatically detected laser points in
     * this volume.
     *
     * @return bool
     */
    public function hasDetectedLaserpoints()
    {
        return $this->images()
            ->where('attrs->laserpoints->error', 'false')
            ->where('attrs->laserpoints->method', '!=', 'manual')
            ->exists();
    }
}
