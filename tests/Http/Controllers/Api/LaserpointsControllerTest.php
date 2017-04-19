<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Label;
use Biigle\Shape;
use Biigle\Tests\LabelTest;
use Biigle\Tests\ImageTest;
use Biigle\Tests\AnnotationTest;
use Biigle\Tests\AnnotationLabelTest;
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
        $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area");
        // Missing distance
        $this->assertResponseStatus(422);

        $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        // Not enough manual annotations for Delphi
        $this->assertResponseStatus(422);

        $this->makeManualAnnotations();

        $this->expectsJobs(LaserpointDetection::class);
        $this->post("/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        $this->assertResponseOk();

        $image = ImageTest::create([
            'volume_id' => $this->volume()->id,
            'filename' => "9.jpg",
        ]);
        $annotation = AnnotationTest::create([
            'image_id' => $image->id,
            'shape_id' => Shape::$pointId,
        ]);
        AnnotationLabelTest::create([
            'annotation_id' => $annotation->id,
            'label_id' => config('laserpoints.label_id'),
        ]);

        $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        // Images don't have equal count of LP annotations
        $this->assertResponseStatus(422);
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

    protected function makeManualAnnotations()
    {
        $labelId = config('laserpoints.label_id');
        if (!Label::where('id', $labelId)->exists()) {
            LabelTest::create(['id' => $labelId, 'name' => 'Laserpoint']);
        }

        for ($i = 0; $i < 4; $i++) {
            $image = ImageTest::create([
                'volume_id' => $this->volume()->id,
                'filename' => "{$i}.jpg",
            ]);

            for ($j = 0; $j < 3; $j++) {
                $annotation = AnnotationTest::create([
                    'image_id' => $image->id,
                    'shape_id' => Shape::$pointId,
                ]);
                AnnotationLabelTest::create([
                    'annotation_id' => $annotation->id,
                    'label_id' => $labelId,
                ]);
            }
        }
    }
}
