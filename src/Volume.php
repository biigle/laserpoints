<?php

namespace Biigle\Modules\Laserpoints;

use Biigle\ImageAnnotation;
use Biigle\Label;
use Biigle\Modules\Laserpoints\Traits\FiltersInvalidLaserPoints;
use Biigle\Shape;
use Biigle\Volume as BaseVolume;
use Exception;

/**
 * Extends the base Biigle volume.
 */
class Volume extends BaseVolume
{
    use FiltersInvalidLaserPoints;

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
     * Determines if the images of this volume have a valid number of manually annotated laser points.
     *
     * @param Label $label The laser point label.
     *
     * @throws Exception If the images of this volume have an invalid count of manually annotated laser points
     */
    public function readyForManualDetection(Label $label)
    {
        $points = ImageAnnotation::join('image_annotation_labels', 'image_annotation_labels.annotation_id', '=', 'image_annotations.id')
            ->join('images', 'image_annotations.image_id', '=', 'images.id')
            ->where('images.volume_id', $this->id)
            ->where('image_annotation_labels.label_id', $label->id)
            ->where('image_annotations.shape_id', Shape::pointId())
            ->select('image_annotations.points', 'image_annotations.image_id')
            ->get()
            ->groupBy('image_id')
            ->pipe([$this, 'filterInvalidLaserPoints'])
            ->map(function ($annotations) {
                return $annotations->count();
            });

        $reference = $points->first();
        if ($reference < Image::MIN_MANUAL_POINTS) {
            throw new Exception('There must be at least '.Image::MIN_MANUAL_POINTS.' manually annotated laser points per image ('.$reference.' found).');
        }

        if ($reference > Image::MAX_MANUAL_POINTS) {
            throw new Exception('There can\'t be more than '.Image::MAX_MANUAL_POINTS.' manually annotated laser points per image ('.$reference.' found).');
        }

        $points->each(function ($count) use ($reference) {
            if ($reference !== $count) {
                throw new Exception('Images must have equal count of manually annotated laser points.');
            }
        });
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
