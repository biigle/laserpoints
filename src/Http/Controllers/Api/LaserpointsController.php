<?php

namespace Biigle\Modules\Laserpoints\Http\Controllers\Api;

use DB;
use Exception;
use Biigle\Shape;
use Biigle\Volume;
use Illuminate\Http\Request;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Http\Controllers\Api\Controller;
use Biigle\Modules\Laserpoints\Jobs\LaserpointDetection;

class LaserpointsController extends Controller
{
    /**
     * Minimum number of manually annotated images required for Delphi laserpoint
     * detection.
     *
     * @var int
     */
    const MIN_DELPHI_IMAGES = 4;

    /**
     * Compute distance between laserpoints for an image.
     *
     * @api {post} images/:id/laserpoints/area Compute image footprint
     * @apiGroup Images
     * @apiName ImagesComputeArea
     * @apiPermission projectEditor
     * @apiDescription This feature is not available for images of remote volumes.
     *
     * @apiParam {Number} id The image ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laserpoints in cm.
     *
     * @param Request $request
     * @param int $id image id
     * @return \Illuminate\Http\Response
     */
    public function computeImage(Request $request, $id)
    {
        $image = Image::with('volume')->findOrFail($id);
        $this->authorize('edit-in', $image->volume);

        if ($image->volume->isRemote()) {
            return $this->buildFailedValidationResponse($request, [
                'id' => 'Laserpoint detection is not available for images of remote volumes.',
            ]);
        }

        $this->validate($request, Image::$laserpointsRules);
        $distance = $request->input('distance');

        $this->dispatch(new LaserpointDetection($image->volume, $distance, [$image->id]));
    }

    /**
     * Compute distance between laserpoints for a volume.
     *
     * @api {post} volumes/:id/laserpoints/area Compute image footprint for all images
     * @apiGroup Volumes
     * @apiName VolumesComputeImageArea
     * @apiPermission projectEditor
     * @apiDescription This feature is not available for remote volumes.
     *
     * @apiParam {Number} id The volume ID.
     * @apiParam (Required arguments) {Number} distance The distance between two laserpoints in cm.
     *
     * @param Request $request
     * @param int $id volume id
     * @return \Illuminate\Http\Response
     */
    public function computeVolume(Request $request, $id)
    {
        $volume = Volume::findOrFail($id);
        $this->authorize('edit-in', $volume);

        if ($volume->isRemote()) {
            return $this->buildFailedValidationResponse($request, [
                'id' => 'Laserpoint detection is not available for remote volumes.',
            ]);
        }

        try {
            $this->checkReadyForDelphi($volume);
        } catch (Exception $e) {
            return $this->buildFailedValidationResponse($request, [
                'id' => 'Delphi laserpoint detection can\'t be performed. '.$e->getMessage(),
            ]);
        }

        $this->validate($request, Image::$laserpointsRules);
        $distance = $request->input('distance');

        $this->dispatch(new LaserpointDetection($volume, $distance));
    }

    /**
     * Determines if the image of a volume can be processed with Delphi
     *
     * @param Volume $volume
     * @throws Exception If the volume can't be processed with Delphi
     */
    protected function checkReadyForDelphi(Volume $volume)
    {
        $labelId = config('laserpoints.label_id');
        $points = DB::table('annotations')
            ->join('annotation_labels', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->join('images', 'annotations.image_id', '=', 'images.id')
            ->where('images.volume_id', $volume->id)
            ->where('annotation_labels.label_id', $labelId)
            ->where('annotations.shape_id', Shape::$pointId)
            ->select(DB::raw('count(annotation_labels.id) as count'))
            ->groupBy('images.id')
            ->pluck('count');

        if ($points->count() < self::MIN_DELPHI_IMAGES) {
            throw new Exception('Only '.$points->count().' images have manually annotated laserpoints. At least '.self::MIN_DELPHI_IMAGES.' are required.');
        }

        $reference = $points[0];
        foreach ($points as $count) {
            if ($reference !== $count) {
                throw new Exception('Images must have equal count of manual laserpoint annotations.');
            }
        }
    }
}
