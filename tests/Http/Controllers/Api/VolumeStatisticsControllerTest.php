<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Modules\Laserpoints\Image;
use Biigle\Modules\Laserpoints\Support\DetectionLock;
use Biigle\Tests\ImageTest;

class VolumeStatisticsControllerTest extends ApiTestCase
{
    public function testShow()
    {
        $id = $this->volume()->id;

        $this->doTestApiRoute('GET', "/api/v1/volumes/{$id}/laserpoints/statistics");

        $this->beUser();
        $this->getJson("/api/v1/volumes/{$id}/laserpoints/statistics")
            ->assertStatus(403);

        $this->beGuest();
        $this->getJson("/api/v1/volumes/{$id}/laserpoints/statistics")
            ->assertStatus(200)
            ->assertExactJson([
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'none' => 0,
                'running' => false,
            ]);

        // No detection.
        ImageTest::create(['volume_id' => $id, 'filename' => 'a']);
        // Other attributes but no detection.
        ImageTest::create(['volume_id' => $id, 'filename' => 'b', 'attrs' => ['size' => 1]]);

        $image = Image::convert(ImageTest::create(['volume_id' => $id, 'filename' => 'c']));
        $image->laserpoints = ['error' => false, 'method' => 'automatic', 'area' => 1];
        $image->save();

        $image = Image::convert(ImageTest::create(['volume_id' => $id, 'filename' => 'd']));
        $image->laserpoints = ['error' => false, 'method' => 'manual', 'area' => 1];
        $image->save();

        $image = Image::convert(ImageTest::create(['volume_id' => $id, 'filename' => 'e']));
        $image->laserpoints = ['error' => true, 'message' => 'abc'];
        $image->save();

        // Image of another volume.
        $image = Image::convert(ImageTest::create());
        $image->laserpoints = ['error' => false, 'method' => 'automatic', 'area' => 1];
        $image->save();

        $this->getJson("/api/v1/volumes/{$id}/laserpoints/statistics")
            ->assertStatus(200)
            ->assertExactJson([
                'total' => 5,
                'successful' => 2,
                'failed' => 1,
                'none' => 2,
                'running' => false,
            ]);
    }

    public function testShowRunning()
    {
        $id = $this->volume()->id;
        $this->beGuest();

        DetectionLock::acquireVolume($id);
        $this->getJson("/api/v1/volumes/{$id}/laserpoints/statistics")
            ->assertStatus(200)
            ->assertJsonPath('running', true);

        DetectionLock::releaseVolume($id);
        $this->getJson("/api/v1/volumes/{$id}/laserpoints/statistics")
            ->assertStatus(200)
            ->assertJsonPath('running', false);

        DetectionLock::acquireImage($id, 1);
        $this->getJson("/api/v1/volumes/{$id}/laserpoints/statistics")
            ->assertStatus(200)
            ->assertJsonPath('running', true);
    }
}
