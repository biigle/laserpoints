<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Tests\ImageTest;
use Biigle\Modules\Laserpoints\Image;

class ImagesControllerTest extends ApiTestCase
{
    public function testShowLaserpoints()
    {
        $image = ImageTest::create([
            'attrs' => [
                'laserpoints' => [
                    'points' => [[10, 10], [20, 20], [30, 30]],
                ]
            ],
            'volume_id' => $this->volume()->id,
        ]);

        $this->doTestApiRoute('GET', "/api/v1/images/{$image->id}/laserpoints");

        $this->beUser();
        $this->get("/api/v1/images/{$image->id}/laserpoints");
        $this->assertResponseStatus(403);

        $this->beGuest();
        $this->get("/api/v1/images/{$image->id}/laserpoints");
        $this->assertResponseOk();
        $this->seeJsonEquals([
            'points' => [[10, 10], [20, 20], [30, 30]]
        ]);
    }
}
