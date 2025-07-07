<?php

/**
 * Quick test script to verify LaserLineFitter works without fatal errors
 */

require_once __DIR__ . '/../../../bootstrap/app.php';

use Biigle\Modules\Laserpoints\Support\LaserLineFitter;
use Illuminate\Support\Collection;

// Create a mock image object
$mockImage = new class {
    public $id = 12345;
    public $filename = 'test_image.jpg';
    public $uuid = 'test-uuid-12345';
    
    public function url() {
        return __DIR__ . '/resources/scripts/test_files/test_image_1.jpg';
    }
};

// Create a collection with one mock image
$images = new Collection([$mockImage]);

// Create LaserLineFitter instance
$lineFitter = new LaserLineFitter();

echo "Testing LaserLineFitter execution...\n";

try {
    // This should work without fatal errors
    $linesFile = $lineFitter->execute($images, 10.0);
    echo "✅ Line fitting completed successfully!\n";
    echo "Lines file stored at: {$linesFile}\n";
} catch (Exception $e) {
    echo "❌ Line fitting failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "🎉 Test completed without fatal errors!\n";
