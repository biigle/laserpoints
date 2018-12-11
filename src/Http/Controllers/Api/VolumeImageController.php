<?php

namespace Biigle\Modules\Laserpoints\Http\Controllers\Api;

use Biigle\Volume;
use Biigle\Http\Controllers\Api\Controller;

class VolumeImageController extends Controller
{
    /**
     * List the IDs of images having automatically detected laser points.
     *
     * @api {get} volumes/:id/images/filter/laserpoints Get images with detected laser points
     * @apiGroup Volumes
     * @apiName VolumeImagesHasDetectedLaserpoint
     * @apiPermission projectMember
     *
     * @apiParam {Number} id The volume ID
     *
     * @apiSuccessExample {json} Success response:
     * [1, 5, 6]
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        $volume = Volume::findOrFail($id);
        $this->authorize('access', $volume);

        if (\DB::connection() instanceof \Illuminate\Database\PostgresConnection) {
            return $volume->images()
                ->where('attrs->laserpoints->error', 'false')
                ->where('attrs->laserpoints->method', '!=', 'manual')
                ->pluck('id');
        }

        // Fallback for DBs that don't support JSON queries.
        return $volume->images()
            ->whereNotNull('attrs')
            ->pluck('attrs', 'id')
            ->filter(function ($value) {
                return array_key_exists('laserpoints', $value) &&
                    array_key_exists('error', $value['laserpoints']) &&
                    array_key_exists('method', $value['laserpoints']) &&
                    $value['laserpoints']['error'] === false &&
                    $value['laserpoints']['method'] !== 'manual';
            })
            ->keys();
    }
}
