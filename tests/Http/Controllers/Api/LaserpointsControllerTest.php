<?php

namespace Biigle\Tests\Modules\Laserpoints\Http\Controllers\Api;

use ApiTestCase;
use Biigle\Image;
use Biigle\MediaType;
use Biigle\Modules\Laserpoints\Image as LaserpointsImage;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageAutomaticJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessImageManualJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeAutomaticJob;
use Biigle\Modules\Laserpoints\Jobs\ProcessVolumeManualJob;
use Biigle\Modules\Laserpoints\Support\DetectionLock;
use Biigle\Shape;
use Biigle\Tests\ImageAnnotationLabelTest;
use Biigle\Tests\ImageAnnotationTest;
use Biigle\Tests\ImageTest;
use Biigle\Tests\LabelTest;
use Bus;
use Cache;
use Queue;

class LaserpointsControllerTest extends ApiTestCase
{
    public function testImageManual()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->doTestApiRoute('POST', "/api/v1/images/{$image->id}/laserpoints/manual");

        $this->beGuest();
        $this->post("/api/v1/images/{$image->id}/laserpoints/manual")
            ->assertStatus(403);

        $this->beEditor();

        // Distance is required.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/manual", [
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        // Label is required.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/manual", [
                'distance' => 50,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 1, 1);
        $image = Image::first();

        // Not enough manual annotations on this image.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 5, 1);
        $image = Image::first();

        // Too many manual annotations on this image.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 2, 1);
        $image = Image::first();

        $this->post("/api/v1/images/{$image->id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);

        Queue::assertPushed(ProcessImageManualJob::class);
    }

    public function testImageManualTiled()
    {
        $this->markTestIncomplete('todo');
        $label = LabelTest::create(['name' => 'Laser Point']);
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $this->volume()->id]);
        $this->makeManualAnnotations($label, 3);

        $this->beEditor();
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);
        Queue::assertPushed(ProcessImageManualJob::class);
    }

    public function testImageAutomatic()
    {
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->doTestApiRoute('POST', "/api/v1/images/{$image->id}/laserpoints/automatic");

        $this->beGuest();
        $this->post("/api/v1/images/{$image->id}/laserpoints/automatic")
            ->assertStatus(403);

        $this->beEditor();

        // Distance is required.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic")
            ->assertStatus(422);

        // Number of laser points is required.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
            ])
            ->assertStatus(422);

        // Channel mode is required for per-image detection.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
            ])
            ->assertStatus(422);

        $this->post("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
                'channel_mode' => 'red',
            ])
            ->assertStatus(200);

        Queue::assertPushed(ProcessImageAutomaticJob::class);
    }

    public function testImageAutomaticWithChannelMode()
    {
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->beEditor();

        $this->post("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
                'channel_mode' => 'red',
            ])
            ->assertStatus(200);

        Queue::assertPushed(ProcessImageAutomaticJob::class, function ($job) {
            return $job->channelMode === 'red';
        });
    }

    public function testImageAutomaticInvalidChannelMode()
    {
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->beEditor();

        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
                'channel_mode' => 'invalid',
            ])
            ->assertStatus(422);
    }

    public function testImageAutomaticNumLaserpoints()
    {
        $image = ImageTest::create(['volume_id' => $this->volume()->id]);
        $this->beEditor();

        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => LaserpointsImage::MIN_POINTS - 1,
                'channel_mode' => 'red',
            ])
            ->assertStatus(422);

        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => LaserpointsImage::MAX_POINTS + 1,
                'channel_mode' => 'red',
            ])
            ->assertStatus(422);

        Queue::assertNotPushed(ProcessImageAutomaticJob::class);

        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => LaserpointsImage::MAX_POINTS,
                'channel_mode' => 'red',
            ])
            ->assertStatus(200);

        Queue::assertPushed(ProcessImageAutomaticJob::class);
    }

    public function testImageAutomaticTiled()
    {
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $this->volume()->id]);

        $this->beEditor();
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
            ])
            ->assertStatus(422);
        Queue::assertNotPushed(ProcessImageAutomaticJob::class);
    }

    public function testVolumeManual()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume()->id;
        $this->beEditor();

        // Missing distance
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        // Missing label
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
            ])
            ->assertStatus(422);

        $this->makeManualAnnotations($label, 1);

        // Images must have at least 2 laserpoint annotations
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 5);
        // Images cant have more than 4 laserpoint annotations
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 2, 1);
        $this->makeManualAnnotations($label, 3, 1);
        // Images don't have equal count of LP annotations
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);

        Image::getQuery()->delete();
        $this->makeManualAnnotations($label, 3);
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);
        Queue::assertPushed(ProcessVolumeManualJob::class, fn ($job) => !is_null($job->batchId));
    }

    public function testVolumeManualTiled()
    {
        $this->markTestIncomplete();
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume()->id;
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $id]);
        $this->makeManualAnnotations($label, 3);

        $this->beEditor();
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(200);
        Queue::assertPushed(ProcessVolumeManualJob::class);
    }

    public function testVolumeManualVideo()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume(['media_type_id' => MediaType::videoId()])->id;
        $this->beEditor();
        $this->makeManualAnnotations($label, 3);
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/manual", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);
    }

    public function testVolumeAutomatic()
    {
        $id = $this->volume()->id;
        $this->doTestApiRoute('POST', "/api/v1/volumes/{$id}/laserpoints/automatic");

        $this->beGuest();
        $this->post("/api/v1/volumes/{$id}/laserpoints/automatic")->assertStatus(403);

        $this->beEditor();
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic")
            ->assertStatus(422);

        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
            ])
            ->assertStatus(200);
        Queue::assertPushed(ProcessVolumeAutomaticJob::class, fn ($job) => !is_null($job->batchId));
    }

    public function testVolumeAutomaticBatchReleasesLock()
    {
        Bus::fake();
        $id = $this->volume()->id;
        $this->beEditor();

        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
            ])
            ->assertStatus(200);

        $this->assertTrue(Cache::has(DetectionLock::key($id)));

        Bus::assertBatched(function ($batch) use ($id) {
            $this->assertCount(1, $batch->jobs);
            $this->assertInstanceOf(ProcessVolumeAutomaticJob::class, $batch->jobs->first());
            $this->assertTrue($batch->allowsFailures());
            $this->assertCount(1, $batch->finallyCallbacks());
            $batch->finallyCallbacks()[0]($batch);
            $this->assertFalse(Cache::has(DetectionLock::key($id)));

            return true;
        });
    }

    public function testVolumeAutomaticNumLaserpoints()
    {
        $id = $this->volume()->id;
        $this->beEditor();

        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => LaserpointsImage::MIN_POINTS - 1,
            ])
            ->assertStatus(422);

        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => LaserpointsImage::MAX_POINTS + 1,
            ])
            ->assertStatus(422);

        Queue::assertNotPushed(ProcessVolumeAutomaticJob::class);

        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => LaserpointsImage::MAX_POINTS,
            ])
            ->assertStatus(200);

        Queue::assertPushed(ProcessVolumeAutomaticJob::class);
    }

    public function testVolumeAutomaticTiled()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume()->id;
        $image = ImageTest::create(['tiled' => true, 'volume_id' => $id]);
        $this->makeManualAnnotations($label, 3);

        $this->beEditor();
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);
        Queue::assertNotPushed(ProcessVolumeAutomaticJob::class);
    }

    public function testVolumeAutomaticVideo()
    {
        $label = LabelTest::create(['name' => 'Laser Point']);
        $id = $this->volume(['media_type_id' => MediaType::videoId()])->id;
        $this->beEditor();
        $this->makeManualAnnotations($label, 3);
        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'label_id' => $label->id,
            ])
            ->assertStatus(422);
    }

    public function testVolumeLockedByVolume()
    {
        $id = $this->volume()->id;
        $image = ImageTest::create(['volume_id' => $id]);
        $this->beEditor();
        DetectionLock::acquireVolume($id);

        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
            ])
            ->assertStatus(422);

        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
                'channel_mode' => 'red',
            ])
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function testVolumeLockedByImage()
    {
        $id = $this->volume()->id;
        $image = ImageTest::create(['volume_id' => $id]);
        $image2 = ImageTest::create(['volume_id' => $id, 'filename' => 'b.jpg']);
        $this->beEditor();

        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
                'channel_mode' => 'red',
            ])
            ->assertStatus(200);

        // Same image.
        $this->postJson("/api/v1/images/{$image->id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
                'channel_mode' => 'red',
            ])
            ->assertStatus(422);

        // Other image of the same volume.
        $this->postJson("/api/v1/images/{$image2->id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
                'channel_mode' => 'red',
            ])
            ->assertStatus(200);

        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
            ])
            ->assertStatus(422);

        Queue::assertPushed(ProcessImageAutomaticJob::class, 2);
        Queue::assertNotPushed(ProcessVolumeAutomaticJob::class);
    }

    public function testVolumeLockOtherVolume()
    {
        $id = $this->volume()->id;
        $this->beEditor();
        DetectionLock::acquireVolume($id + 1);

        $this->postJson("/api/v1/volumes/{$id}/laserpoints/automatic", [
                'distance' => 50,
                'num_laserpoints' => 2,
            ])
            ->assertStatus(200);
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
