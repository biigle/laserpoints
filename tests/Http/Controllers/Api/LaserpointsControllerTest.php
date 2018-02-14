<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Label;
use Biigle\Shape;
use Biigle\Image;
use Biigle\Tests\LabelTest;
use Biigle\Tests\ImageTest;
use Biigle\Tests\AnnotationTest;
use Biigle\Tests\AnnotationLabelTest;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageDelphiJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeDelphiJob;

class LaserpointsControllerTest extends ApiTestCase
{
    public function testComputeImage()
    {
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->doTestApiRoute('POST', "/api/v1/images/{$image->id}/laserpoints/area");

        $this->beGuest();
        $response = $this->post("/api/v1/images/{$image->id}/laserpoints/area");
        $response->assertStatus(403);

        $this->beEditor();
        $response = $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area");
        // Distance is required.
        $response->assertStatus(422);

        $response = $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        // Not enough manually annotated images for Delphi.
        $response->assertStatus(422);

        $this->makeManualAnnotations(3);

        $this->expectsJobs(ProcessImageDelphiJob::class);
        $response = $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        $response->assertStatus(200);

        Image::getQuery()->delete();
        $this->makeManualAnnotations(1, 1);
        $image = Image::first();

        $response = $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        // Not enough manual annotations on this image.
        $response->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations(5, 1);
        $image = Image::first();

        $response = $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        // Too many manual annotations on this image.
        $response->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations(2, 1);
        $image = Image::first();

        $this->expectsJobs(ProcessImageManualJob::class);
        $response = $this->post("/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        $response->assertStatus(200);
    }

    public function testComputeImageRemote()
    {
        $this->volume()->url = 'http://localhost';
        $this->volume()->save();
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->makeManualAnnotations(3);

        $this->beEditor();
        $this->doesntExpectJobs(ProcessImageDelphiJob::class);
        $response = $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        $response->assertStatus(422);
    }

    public function testComputeImageTiled()
    {
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $this->volume()->id]);
        $this->makeManualAnnotations(3);

        $this->beEditor();
        $this->doesntExpectJobs(ProcessImageDelphiJob::class);
        $response = $this->json('POST', "/api/v1/images/{$image->id}/laserpoints/area", ['distance' => 50]);
        $response->assertStatus(422);
    }

    public function testComputeVolume()
    {
        $id = $this->volume()->id;
        $this->doTestApiRoute('POST', "/api/v1/volumes/{$id}/laserpoints/area");

        $this->beGuest();
        $response = $this->post("/api/v1/volumes/{$id}/laserpoints/area");
        $response->assertStatus(403);

        $this->beEditor();
        $response = $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area");
        // Missing distance
        $response->assertStatus(422);

        $response = $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        // Not enough manually annotated images for Delphi
        $response->assertStatus(422);

        $this->makeManualAnnotations(1);
        $response = $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        // Images must have at least 2 laserpoint annotations
        $response->assertStatus(422);
        Image::getQuery()->delete();

        $this->makeManualAnnotations(5);
        $response = $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        // Images cant have more than 4 laserpoint annotations
        $response->assertStatus(422);
        Image::getQuery()->delete();

        $this->makeManualAnnotations(3);

        $this->expectsJobs(ProcessVolumeDelphiJob::class);
        $response = $this->post("/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        $response->assertStatus(200);

        $this->makeManualAnnotations(2, 1);

        $response = $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        // Images don't have equal count of LP annotations
        $response->assertStatus(422);
    }

    public function testComputeVolumeRemote()
    {
        $this->volume()->url = 'http://localhost';
        $this->volume()->save();
        $id = $this->volume()->id;
        $this->makeManualAnnotations(3);

        $this->beEditor();
        $this->doesntExpectJobs(ProcessVolumeDelphiJob::class);
        $response = $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        $response->assertStatus(422);
    }

    public function testComputeVolumeTiled()
    {
        $id = $this->volume()->id;
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $id]);
        $this->makeManualAnnotations(3);

        $this->beEditor();
        $this->doesntExpectJobs(ProcessVolumeDelphiJob::class);
        $response = $this->json('POST', "/api/v1/volumes/{$id}/laserpoints/area", ['distance' => 50]);
        $response->assertStatus(422);
    }

    protected function makeManualAnnotations($annotations, $images = 4)
    {
        $annotations = $annotations ?: rand(1, 10);
        $labelId = LabelTest::create(['name' => 'Laserpoint'])->id;
        config(['laserpoints.label_id' => $labelId]);

        for ($i = 0; $i < $images; $i++) {
            $image = ImageTest::create([
                'volume_id' => $this->volume()->id,
                'filename' => uniqid(),
            ]);

            for ($j = 0; $j < $annotations; $j++) {
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
