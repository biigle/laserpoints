<?php

namespace Biigle\Modules\Laserpoints\Jobs;

use DB;
use Biigle\Shape;

trait CollectsLaserpointAnnotations
{
    /**
     * Collects of the given all images that contain laserpoint annotations
     *
     * @param array $ids Image IDs to restrict the lookup to
     *
     * @return Collection Laserpoint coordinates indexed by image ID
     */
    protected function getLaserpointsForImages($ids)
    {
        $labelId = config('laserpoints.label_id');

        return DB::table('annotations')
            ->join('annotation_labels', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->whereIn('annotations.image_id', $ids)
            ->where('annotation_labels.label_id', $labelId)
            ->where('annotations.shape_id', Shape::$pointId)
            ->select('annotations.points', 'annotations.image_id')
            ->get()
            ->groupBy('image_id')
            ->map(function ($i) {
                return $i->pluck('points');
            });
    }

    /**
     * Collects all images of a volume that contain laserpoint annotations
     *
     * @param int $id Volume ID
     *
     * @return Collection Laserpoint coordinates indexed by image ID
     */
    protected function getLaserpointsForVolume($id)
    {
        $labelId = config('laserpoints.label_id');

        return DB::table('annotations')
            ->join('annotation_labels', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->join('images', 'annotations.image_id', '=', 'images.id')
            ->where('images.volume_id', $id)
            ->where('annotation_labels.label_id', $labelId)
            ->where('annotations.shape_id', Shape::$pointId)
            ->select('annotations.points', 'annotations.image_id')
            ->get()
            ->groupBy('image_id')
            ->map(function ($i) {
                return $i->pluck('points');
            });
    }
}
