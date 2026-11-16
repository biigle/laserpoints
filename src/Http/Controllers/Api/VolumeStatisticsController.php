<?php

namespace Biigle\Modules\Laserpoints\Http\Controllers\Api;

use Biigle\Http\Controllers\Api\Controller;
use Biigle\Modules\Laserpoints\Support\DetectionLock;
use Biigle\Volume;

class VolumeStatisticsController extends Controller
{
    /**
     * Get the laser point detection statistics of a volume.
     *
     * @api {get} volumes/:id/laserpoints/statistics Get laser point detection statistics
     * @apiGroup Volumes
     * @apiName VolumesShowLaserpointStatistics
     * @apiPermission projectMember
     * @apiDescription Returns the number of images of the volume with a successful laser point detection, with a failed laser point detection and without a laser point detection. `running` is `true` while a laser point detection is running in the volume.
     *
     * @apiParam {Number} id The volume ID
     *
     * @apiSuccessExample {json} Success response:
     * {
     *     "total": 120,
     *     "successful": 85,
     *     "failed": 10,
     *     "none": 25,
     *     "running": false
     * }
     *
     * @param  int  $id
     * @return array
     */
    public function show($id)
    {
        $volume = Volume::findOrFail($id);
        $this->authorize('access', $volume);

        $counts = $volume->images()
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where attrs->'laserpoints'->>'error' = 'false') as successful")
            ->selectRaw("count(*) filter (where attrs->'laserpoints'->>'error' = 'true') as failed")
            ->first();

        $total = (int) $counts->total;
        $successful = (int) $counts->successful;
        $failed = (int) $counts->failed;

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'none' => $total - $successful - $failed,
            'running' => DetectionLock::isRunning($volume->id),
        ];
    }
}
