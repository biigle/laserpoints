@extends('manual.base')

@section('manual-title') Laser point detection @stop

@section('manual-content')
    <div class="row">
        <p class="lead">
            The improved automatic laser point detection uses advanced color threshold analysis to determine the visual footprint of images with enhanced accuracy and convenience.
        </p>

        <p>
            For many collections of benthic images a geometric laser point pattern is used to determine the pixel-to-centimetre ratio of displayed sea floor. BIIGLE can assist in the evaluation of your data and provides an improved method to automatically detect laser points and compute the visual footprint of the images.
        </p>
        <div class="panel panel-warning">
            <div class="panel-body text-warning">
                Measurements based on the image footprint of the laser point detection are only accurate if the camera points straight down to the ground (nadir).
            </div>
        </div>
        
        <h3>Improved Detection Algorithm</h3>
        <p>
            BIIGLE now uses an advanced color threshold-based laser point detection algorithm that significantly improves accuracy compared to the previous method. The new algorithm features:
        </p>
        <ul>
            <li><strong>Color threshold detection:</strong> Uses adaptive color analysis to identify laser points based on their distinctive color signature</li>
            <li><strong>Line-based constraints:</strong> Optionally applies geometric line fitting to improve detection accuracy when enabled</li>
            <li><strong>No manual annotations required:</strong> The algorithm can work without pre-annotated example images, making it much more convenient to use</li>
            <li><strong>Better handling of challenging conditions:</strong> Improved performance in varying lighting conditions and backgrounds</li>
        </ul>
        
        <h3>Detection Modes</h3>
        <p>
            The laser point detection now offers two modes of operation:
        </p>
        <ul>
            <li><strong>Standard mode:</strong> Direct color threshold detection without geometric constraints</li>
            <li><strong>Line detection mode (recommended):</strong> Uses a two-step approach that first fits lines to a subset of images, then applies these line constraints during detection for improved accuracy</li>
            <div class="panel panel-warning">
                <div class="panel-body text-warning">
                    The line detection mode assumes that lasers and camera are fixed relative to each other, i.e. the camera cannot be moved without moving the lasers in the same way.
                </div>
            </div>
        </ul>
        
        <h3>Supported Laser Point Patterns</h3>
        <p>
            BIIGLE currently supports the following types of geometric laser point patterns:
        </p>
        <ul>
            <li>2 parallel lasers painting two points</li>
            <li>3 parallel lasers painting the points of an equilateral triangle</li>
            <li>4 parallel lasers painting the points of a square</li>
        </ul>
        
        <h3>Manual Annotation Option</h3>
        <p>
            While no longer required, you can still choose to annotate the laser points on all of your images manually if desired. In this case BIIGLE will skip the automatic detection and will directly compute the visual footprint of the images. This is the most accurate method to determine the visual footprint but may be very time consuming.
        </p>
        <h3>Starting the Detection</h3>
        <p>
            The laser point detection and visual footprint calculation can be requested for a whole image volume in the volume overview. Open the laser point tab in the sidebar on the left (<button class="btn btn-default btn-xs" onclick="$biiglePostMessage('info', 'Try the button in the volume overview 🙂')"><span class="fa fa-vector-square" aria-hidden="true"></span></button>), enter the distance of the laser points in centimetres, and choose your detection settings:
        </p>
        <ul>
            <li><strong>Laser distance:</strong> The distance between two laser points in centimetres (as configured on your camera system)</li>
            <li><strong>Line detection mode:</strong> Enable this option (recommended) for improved accuracy. When enabled, the algorithm first analyzes a subset of images to fit line constraints, then applies these constraints during detection</li>
        </ul>
        <p>
            Submit your request to start the detection process. The new algorithm no longer requires manual annotation of example laser points, making the process much more streamlined.
        </p>
        <h3>Monitoring Progress and Results</h3>
        <p>
            The automatic laser point detection may take some time depending on the size of your volume and the detection mode selected. When line detection mode is enabled, the process includes an initial line fitting phase followed by individual image detection. You can check the progress using the "detected laser points" filter of the volume overview, which will show you all images where the automatic laser point detection was successful.
        </p>
        <p>
            You can view the detailed results for an individual image on the image information page (<button class="btn btn-default btn-xs"><span class="fa fa-info-circle" aria-hidden="true"></span></button>). Here you can also (re-)submit the laser point detection for an individual image if needed.
        </p>
        <p>
            Always verify the detection results before further processing your data. The easiest way is to apply the "detected laser points" filter in the image volume overview and then cycle through all images that contain automatically detected laser points using the image annotation tool. Detected laser points will be displayed as small circles on the image. If the detected laser points were not correct for an image, you can annotate them manually and resubmit the laser point detection for that specific image.
        </p>
        
        <h3>Fallback and Error Handling</h3>
        <p>
            The new detection algorithm includes robust error handling and fallback mechanisms:
        </p>
        <ul>
            <li>If line detection mode fails, the algorithm automatically falls back to standard color threshold detection</li>
            <li>Individual images that cannot be processed are skipped, allowing the detection to continue for the rest of the volume</li>
            <li>Detailed error messages help identify any issues that may occur during processing</li>
        </ul>
    </div>
@endsection
