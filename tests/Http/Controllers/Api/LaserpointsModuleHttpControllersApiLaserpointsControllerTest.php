<?php

use Dias\Modules\Laserpoints\Jobs\LaserpointDetection;

class LaserpointsModuleHttpControllersApiLaserpointsControllerTest extends ApiTestCase {

    public function testComputeImage()
    {

        $image = ImageTest::create(['transect_id' => $this->transect()->id]);
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
        $this->transect()->url = 'http://localhost';
        $this->transect()->save();
        $image = ImageTest::create(['transect_id' => $this->transect()->id]);

        $this->beEditor();
        $this->doesntExpectJobs(LaserpointDetection::class);
        $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseStatus(422);
    }

    public function testComputeTransect()
    {

        $id = $this->transect()->id;
        $this->doTestApiRoute('POST', "/api/v1/transects/{$id}/laserpoints/area");

        $this->beGuest();
        $this->post("/api/v1/transects/{$id}/laserpoints/area");
        $this->assertResponseStatus(403);

        $this->beEditor();
        $this->post("/api/v1/transects/{$id}/laserpoints/area");
        $this->assertResponseStatus(302);

        $this->expectsJobs(LaserpointDetection::class);
        $this->post("/api/v1/transects/{$id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseOk();
    }

    public function testComputeTransectRemote()
    {
        $this->transect()->url = 'http://localhost';
        $this->transect()->save();
        $id = $this->transect()->id;

        $this->beEditor();
        $this->doesntExpectJobs(LaserpointDetection::class);
        $this->json('POST', "/api/v1/transects/{$id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseStatus(422);
    }
}
