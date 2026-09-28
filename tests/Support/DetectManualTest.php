<?php

namespace Biigle\Tests\Modules\Laserpoints\Support;

use Biigle\Modules\Laserpoints\Support\DetectManual;
use TestCase;

class DetectManualTest extends TestCase
{
    /**
     * All test cases below span a shape with an edge length of 100 px for laser points
     * that are 50 cm apart. So 1000 px are 5 m and a 1000x1000 px image covers 25 m².
     */
    const EXPECTED_AREA = 25.0;

    public function testExecuteTwoPoints()
    {
        $detect = new DetectManual;
        $output = $detect->execute(1000, 1000, 50, [[0, 0], [100, 0]]);

        $this->assertFalse($output['error']);
        $this->assertEqualsWithDelta(self::EXPECTED_AREA, $output['area'], 1e-6);
        $this->assertSame(2, $output['count']);
        $this->assertSame('manual', $output['method']);
    }

    public function testExecuteThreePoints()
    {
        $detect = new DetectManual;
        // Equilateral triangle with an edge length of 100 px.
        $output = $detect->execute(1000, 1000, 50, [[0, 0], [100, 0], [50, 50 * sqrt(3)]]);

        $this->assertFalse($output['error']);
        $this->assertEqualsWithDelta(self::EXPECTED_AREA, $output['area'], 1e-6);
        $this->assertSame(3, $output['count']);
    }

    public function testExecuteFourPoints()
    {
        $detect = new DetectManual;
        // Square with an edge length of 100 px.
        $output = $detect->execute(1000, 1000, 50, [[0, 0], [100, 0], [100, 100], [0, 100]]);

        $this->assertFalse($output['error']);
        $this->assertEqualsWithDelta(self::EXPECTED_AREA, $output['area'], 1e-6);
        $this->assertSame(4, $output['count']);
    }

    public function testExecuteNonSquareImage()
    {
        $detect = new DetectManual;
        $output = $detect->execute(1000, 500, 50, [[0, 0], [100, 0]]);

        $this->assertFalse($output['error']);
        $this->assertEqualsWithDelta(self::EXPECTED_AREA / 2, $output['area'], 1e-6);
    }

    public function testExecuteFlipsPoints()
    {
        $detect = new DetectManual;
        $output = $detect->execute(1000, 1000, 50, [[10, 20], [110, 20]]);

        $this->assertSame([[20, 10], [20, 110]], $output['points']);
    }

    public function testExecuteUnknownDimensions()
    {
        $detect = new DetectManual;
        $output = $detect->execute(null, null, 50, [[0, 0], [100, 0]]);

        $this->assertTrue($output['error']);
        $this->assertSame('manual', $output['method']);
        $this->assertStringContainsString('dimensions', $output['message']);
    }

    public function testExecuteUnsupportedPointCount()
    {
        $detect = new DetectManual;

        $output = $detect->execute(1000, 1000, 50, [[0, 0]]);
        $this->assertTrue($output['error']);
        $this->assertSame('Unsupported number of laserpoints.', $output['message']);

        $output = $detect->execute(1000, 1000, 50, [[0, 0], [1, 1], [2, 2], [3, 3], [4, 4]]);
        $this->assertTrue($output['error']);
        $this->assertSame('Unsupported number of laserpoints.', $output['message']);
    }

    public function testExecuteIdenticalPoints()
    {
        $detect = new DetectManual;

        foreach ([2, 3, 4] as $count) {
            $output = $detect->execute(1000, 1000, 50, array_fill(0, $count, [100, 100]));
            $this->assertTrue($output['error'], "Failed for {$count} points.");
            $this->assertSame('Computed pixel area is zero.', $output['message']);
        }
    }

    public function testExecuteCollinearPoints()
    {
        $detect = new DetectManual;
        $output = $detect->execute(1000, 1000, 50, [[0, 0], [100, 0], [200, 0]]);

        $this->assertTrue($output['error']);
        $this->assertSame('Computed pixel area is zero.', $output['message']);
    }
}
