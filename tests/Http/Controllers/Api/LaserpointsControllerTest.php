<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Tests\ImageTest;
use Biigle\Modules\Laserpoints\Jobs\LaserpointDetection;

class LaserpointsControllerTest extends ApiTestCase
{
    public function testComputeImage()
    {

        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->doTestApiRoute('POST', "/api/v1/images/{$image->id}/laserpoints/area");

        $this->beGuest();
        $this->post("/api/v1/images/{$image->id}/laserpoints/area");
        $this->assertResponseStatus(403);

        $this->beEditor();
        $this->post("/api/v1/images/{$image->id}/laserpoints/area");
        $this->assertResponseStatus(302);

        $this->expectsJobs(LaserpointDetection::class);
        $this->post("/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseOk();
    }

    public function testComputeImageRemote()
    {
        $this->volume()->url = 'http://localhost';
        $this->volume()->save();
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);

        $this->beEditor();
        $this->doesntExpectJobs(LaserpointDetection::class);
        $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseStatus(422);
    }

    public function testComputeVolume()
    {

        $id = $this->volume()->id;
        $this->doTestApiRoute('POST', "/api/v1/volumes/{$id}/laserpoints/area");

        $this->beGuest();
        $this->post("/api/v1/volumes/{$id}/laserpoints/area");
        $this->assertResponseStatus(403);

        $this->beEditor();
        $this->post("/api/v1/volumes/{$id}/laserpoints/area");
        $this->assertResponseStatus(302);

        $this->expectsJobs(LaserpointDetection::class);
        $this->post("/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseOk();
    }

    public function testComputeVolumeRemote()
    {
        $this->volume()->url = 'http://localhost';
        $this->volume()->save();
        $id = $this->volume()->id;

        $this->beEditor();
        $this->doesntExpectJobs(LaserpointDetection::class);
        $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseStatus(422);
    }
}
