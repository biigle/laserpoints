<?php

use Dias\Modules\Laserpoints\Jobs\ComputeAreaForImages;

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

        $this->expectsJobs(ComputeAreaForImages::class);
        $this->post("/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseOk();
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

        $this->expectsJobs(ComputeAreaForImages::class);
        $this->post("/api/v1/transects/{$id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseOk();
    }
}
