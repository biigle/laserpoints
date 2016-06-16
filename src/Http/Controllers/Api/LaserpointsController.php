<?php

namespace Dias\Modules\Laserpoints\Http\Controllers\Api;
use DB;
use Dias\Image;
use Dias\Transect;
use Dias\Http\Controllers\Api\Controller;
use Dias\Modules\Laserpoints\Jobs\ComputeAreaForImage;

class LaserpointsController extends Controller
{
    /**
     * ComputeDistance between laserpoints
     *
     * @api {post} images/:id/laserpoints/area/:laserdist Compute image footprint
     * @apiGroup Images
     * @apiName ComputeArea
     * @apiPermission projectMember
     *
     * @apiParam {Number} id The image ID.
     * @apiParam {Number} laserdist The distance between two laserpoints in cm.
     *
     * @param int $id image id
     * @param int $laserdist The distance between two laserpoints in cm.
     * @return \Illuminate\Http\Response
     */
    public function area($id, $laserdist)
    {
        $image = Image::findOrFail($id);
        $transect = Transect::findOrFail($image->transect_id);
        $this->authorize('access', $image);
        $this->dispatch(new ComputeAreaForImage($image,$transect,$laserdist));
        echo "Job dispatched. Please wait. The results will be available soon.";
    }

     /**
     * ComputeDistance between laserpoints
     *
     * @api {post} transects/:id/laserpoints/area/:laserdist Compute image footprint for all images in a transect
     * @apiGroup Transects
     * @apiName ComputeArea
     * @apiPermission projectMember
     *
     * @apiParam {Number} id The transect ID.
     * @apiParam {Number} laserdist The distance between two laserpoints in cm.
     *
     * @param int $id transect id
     * @param int $laserdist The distance between two laserpoints in cm.
     * @return \Illuminate\Http\Response
     */
    public function TransectArea($id, $laserdist)
    {
        $imageInTransect = DB::select('SELECT images.id FROM images WHERE transect_id =?',[$id]);
        $i=0;
        $transect = Transect::findOrFail($id);
        foreach ($imageInTransect as $imgid) {
            $image = Image::findOrFail($imgid->id);
            $this->authorize('access', $image);
            $this->dispatch(new ComputeAreaForImage($image,$transect,$laserdist));
            $i++;
        }
        echo $i." jobs dispatched. Please wait. The results will be available soon.";
    }


}
