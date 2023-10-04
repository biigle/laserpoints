<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Image;
use Biigle\MediaType;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageDelphiJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeDelphiJob;
use Biigle\Shape;
use Biigle\Tests\ImageAnnotationLabelTest;
use Biigle\Tests\ImageAnnotationTest;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Queue;

class LaserpointsControllerTest extends ApiTestCase
{
    public function testComputeImage()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->doTestApiRoute('POST', "/api/v1/images/{$image->id}/laserpoints/area");

        $this->beGuest();
        $this->post("/api/v1/images/{$image->id}/laserpoints/area")
            ->assertStatus(403);

        $this->beEditor();

        // Not enough manually annotated images for Delphi.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        $this->makeManualAnnotations($label, 3);

        $this->postJson("/api/v1/images/{$image->id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);

        Queue::assertPushed(ProcessImageDelphiJob::class);

        // Distance is required.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/area", [
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        // Label is required.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/area", [
                'distance' => 50,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 1, 1);
        $image = Image::first();

        // Not enough manual annotations on this image.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 5, 1);
        $image = Image::first();

        // Too many manual annotations on this image.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 2, 1);
        $image = Image::first();

        $this->post("/api/v1/images/{$image->id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);

        Queue::assertPushed(ProcessImageManualJob::class);
    }

    public function testComputeImageRemote()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $this->volume()->url = 'http://localhost';
        $this->volume()->save();
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->makeManualAnnotations($label, 3);

        $this->beEditor();
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);
        Queue::assertPushed(ProcessImageDelphiJob::class);
    }

    public function testComputeImageTiled()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $this->volume()->id]);
        $this->makeManualAnnotations($label, 3);

        $this->beEditor();
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);
        Queue::assertNotPushed(ProcessImageDelphiJob::class);
    }

    public function testComputeVolume()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume()->id;
        $this->doTestApiRoute('POST', "/api/v1/volumes/{$id}/laserpoints/area");

        $this->beGuest();
        $this->post("/api/v1/volumes/{$id}/laserpoints/area")->assertStatus(403);

        $this->beEditor();
        $this->makeManualAnnotations($label, 3);
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);
        Queue::assertPushed(ProcessVolumeDelphiJob::class);
    }

    public function testComputeVolumeValidation()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume()->id;
        $this->beEditor();

        // Missing distance
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/area", [
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        // Missing label
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/area", [
                'distance' => 50,
            ])
            ->assertStatus(422);

        // Not enough manually annotated images for Delphi
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        $this->makeManualAnnotations($label, 1);

        // Images must have at least 2 laserpoint annotations
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 5);
        // Images cant have more than 4 laserpoint annotations
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 2, 1);
        // Images don't have equal count of LP annotations
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);
    }

    public function testComputeVolumeRemote()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $this->volume()->url = 'http://localhost';
        $this->volume()->save();
        $id = $this->volume()->id;
        $this->makeManualAnnotations($label, 3);

        $this->beEditor();
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);
        Queue::assertPushed(ProcessVolumeDelphiJob::class);
    }

    public function testComputeVolumeTiled()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume()->id;
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $id]);
        $this->makeManualAnnotations($label, 3);

        $this->beEditor();
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);
        Queue::assertNotPushed(ProcessVolumeDelphiJob::class);
    }

    public function testComputeVideoVolume()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume(['media_type_id' => MediaType::videoId()])->id;
        $this->beEditor();
        $this->makeManualAnnotations($label, 3);
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/area", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);
    }

    protected function makeManualAnnotations($label, $annotations, $images = 4)
    {
        $annotations = $annotations ?: rand(1, 10);
        for ($i = 0; $i < $images; $i++) {
            $image = ImageTest::create([
                'volume_id' => $this->volume()->id,
                'filename' => uniqid(),
            ]);

            for ($j = 0; $j < $annotations; $j++) {
                $annotation = ImageAnnotationTest::create([
                    'image_id' => $image->id,
                    'shape_id' => Shape::pointId(),
                ]);
                ImageAnnotationLabelTest::create([
                    'annotation_id' => $annotation->id,
                    'label_id' => $label->id,
                ]);
            }
        }
    }
}
