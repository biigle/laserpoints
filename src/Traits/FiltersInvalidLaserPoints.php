<?php

namespace Biigle\Modules\Laserpoints\Traits;

use Biigle\Image;

trait FiltersInvalidLaserPoints
{
    /**
     * Removes items from the annotations array if the laser point annotations are invalid.
     *
     * @param Collection $annotations Annotations grouped by image
     *
     * @return Filtered points array
     */
    public function filterInvalidLaserPoints($annotations)
    {
        $images = Image::whereIn('id', $annotations->keys())
            ->whereNotNull('attrs->width')
            ->whereNotNull('attrs->height')
            ->select('id', 'attrs')
            ->get()
            ->keyBy('id');

        return $annotations->filter(function ($as, $imageId) use ($images) {
            $image = $images->get($imageId);

            // Ignore annotations of images that have no width/height attributes
            if (is_null($image)) {
                return true;
            }

            return $as->reduce(function ($c, $a) use ($image) {
                return $c && $a->points[0] >= 0 && $a->points[0] <= $image->width && $a->points[1] >= 0 && $a->points[1] <= $image->height;
            }, true);
        });
    }
}
