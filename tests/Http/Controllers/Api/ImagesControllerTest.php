<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Tests\ImageTest;

class ImagesControllerTest extends ApiTestCase
{
    public function testShowLaserpoints()
    {
        $image = ImageTest::create([
            'attrs' => [
                'laserpoints' => [
                    'points' => [[10, 10], [20, 20], [30, 30]],
                ],
            ],
            'volume_id' => $this->volume()->id,
        ]);

        $this->doTestApiRoute('GET', "/api/v1/images/{$image->id}/laserpoints");

        $this->beUser();
        $response = $this->get("/api/v1/images/{$image->id}/laserpoints");
        $response->assertStatus(403);

        $this->beGuest();
        $response = $this->get("/api/v1/images/{$image->id}/laserpoints");
        $response->assertStatus(200);
        $response->assertExactJson([
            'points' => [[10, 10], [20, 20], [30, 30]],
        ]);
    }
}
