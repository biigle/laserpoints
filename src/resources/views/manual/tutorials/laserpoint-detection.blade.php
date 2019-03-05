@extends('manual.base')

@section('manual-title') Laser point detection @stop

@section('manual-content')
    <div class="row">
        <p class="lead">
            The automatic laser point detection is used to determine the visual footprint of images.
        </p>

        <p>
            For many collections of benthic images a geometric laser point pattern is used to determine the pixel-to-centimetre ratio of displayed sea floor. BIIGLE can assist in the evaluation of your data and provides a method <a href="#ref1">[1]</a> to automatically detect laser points and compute the visual footprint of the images.
        </p>
        <div class="panel panel-warning">
            <div class="panel-body text-warning">
                Measurements based on the image footprint of the laser point detection are only accurate if the camera points straight down to the ground (nadir).
            </div>
        </div>
        <p>
            Before you can start the automatic laser point detection you have to manually annotate a few example laser points with point annotations in your images. You have to annotate at least {{Biigle\Modules\Laserpoints\Volume::MIN_DELPHI_IMAGES}} images this way. BIIGLE currently supports the following types of geometric laser point patterns:
        </p>
        <ul>
            <li>2 parallel lasers painting two points</li>
            <li>3 parallel lasers painting the points of an equilateral triangle</li>
            <li>4 parallel lasers painting the points of a square</li>
        </ul>
        <p>
            You can also annotate the laser points on all of your images manually. In this case BIIGLE will skip the automatic detection and will directly compute the visual footprint of the images. This is the most accurate method to determine the visual footprint but it may be very time consuming.
        </p>
        <p>
            The laser point detection and visual footprint calculation can be requested for a whole volume in the volume overview. Open the laser point tab in the sidebar on the left (<button class="btn btn-default btn-xs" onclick="$biiglePostMessage('info', 'Try the button in the volume overview 🙂')"><span class="fa fa-vector-square" aria-hidden="true"></span></button>), select the label that you have used to annotate the (example) laser points, enter the distance of the laser points in centimetre and submit your request.
        </p>
        <p>
            Depending on the size of your volume, the automatic laser point detection may take some time. You can check the progress using the "detected laser points" filter of the volume overview. This will show you all images where the automatic laser point detection was successful. You can view the detailed results for an individual image on the image information page (<button class="btn btn-default btn-xs"><span class="fa fa-info-circle" aria-hidden="true"></span></button>). Here you can also (re-)submit the laser point detection for an individual image.
        </p>
        <p>
            If you choose to detect laser points automatically, make sure you check the results before further processing your data. The easiest way is to apply the "detected laser points" filter in the volume overview and then cycle through all images that contain automatically detected laser points using the annotation tool. Detected laser points will be displayed as small circles on the image. If the detected laser points were not correct for an image, annotate them manually and resubmit the laser point detection for the image.
        </p>
    </div>
    <div class="row">
        <h3>References</h3>
        <ol>
            <li><a name="ref1"></a> Schoening, T., Kuhn, T., Bergmann, M., & Nattkemper, T. W. (2015). DELPHI—fast and adaptive computational laser point detection and visual footprint quantification for arbitrary underwater image collections. Frontiers in Marine Science, 2, 20. doi: <a href="https://doi.org/10.3389/fmars.2015.00020">10.3389/fmars.2015.00020</a></li>
        </ol>
    </div>
@endsection
