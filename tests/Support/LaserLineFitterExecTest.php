<?php

namespace Biigle\Modules\Laserpoints\Tests\Support;

use Biigle\Modules\Laserpoints\Support\LaserLineFitter;
use PHPUnit\Framework\TestCase;

class LaserLineFitterExecTest extends TestCase
{
    public function testExecLineFittingSuccess()
    {
        $lineFitter = new LaserLineFitter();
        
        // Test with a simple command that succeeds
        $result = $this->invokeMethod($lineFitter, 'execLineFitting', ['echo "test"']);
        
        $this->assertEquals(0, $result);
    }
    
    public function testExecLineFittingFailure()
    {
        $lineFitter = new LaserLineFitter();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Fatal error with line fitting (code 1)');
        
        // Test with a command that fails
        $this->invokeMethod($lineFitter, 'execLineFitting', ['exit 1']);
    }
    
    /**
     * Call protected/private method of a class.
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
