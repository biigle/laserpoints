<?php

namespace Biigle\Tests\Modules\Laserpoints\Support;

use Biigle\Modules\Laserpoints\Support\DetectionLock;
use Cache;
use TestCase;

class DetectionLockTest extends TestCase
{
    public function testVolume()
    {
        $this->assertTrue(DetectionLock::acquireVolume(1));
        $this->assertFalse(DetectionLock::acquireVolume(1));
        $this->assertFalse(DetectionLock::acquireImage(1, 1));
        $this->assertTrue(DetectionLock::acquireVolume(2));

        DetectionLock::releaseVolume(1);
        $this->assertFalse(Cache::has(DetectionLock::key(1)));
        $this->assertTrue(DetectionLock::acquireImage(1, 1));
    }

    public function testImage()
    {
        $this->assertTrue(DetectionLock::acquireImage(1, 1));
        $this->assertFalse(DetectionLock::acquireImage(1, 1));
        $this->assertTrue(DetectionLock::acquireImage(1, 2));
        $this->assertFalse(DetectionLock::acquireVolume(1));

        DetectionLock::releaseImage(1, 1);
        $this->assertFalse(DetectionLock::acquireVolume(1));

        DetectionLock::releaseImage(1, 2);
        $this->assertFalse(Cache::has(DetectionLock::key(1)));
        $this->assertTrue(DetectionLock::acquireVolume(1));
    }

    public function testMutexTimeout()
    {
        config(['laserpoints.lock_wait' => 0]);
        $lock = Cache::lock(DetectionLock::key(1).'-mutex', 10);
        $lock->get();

        try {
            $this->assertFalse(DetectionLock::acquireVolume(1));
        } finally {
            $lock->release();
        }
    }
}
