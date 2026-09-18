@extends('manual.base')

@section('manual-title') Laser point detection @stop

@section('manual-content')
    <div class="row">
        <p class="lead">
            The laser point detection is used to determine the visual footprint of images.
        </p>

        <p>
            For many collections of benthic images a geometric laser point pattern is used to determine the pixel-to-centimetre ratio of displayed sea floor. BIIGLE can assist in the evaluation of your data and offers two methods to determine the visual footprint of the images: the <a href="#automatic">automatic detection</a> of the laser points in the images and the <a href="#manual">computation based on manually annotated</a> laser points.
        </p>
        <div class="panel panel-warning">
            <div class="panel-body text-warning">
                Measurements based on the image footprint of the laser point detection are only accurate if the camera points straight down to the ground (nadir).
            </div>
        </div>
        <p>
            BIIGLE currently supports the following types of geometric laser point patterns:
        </p>
        <ul>
            <li>2 parallel lasers painting two points</li>
            <li>3 parallel lasers painting the points of an equilateral triangle</li>
            <li>4 parallel lasers painting the points of a square</li>
        </ul>
        <p>
            The laser point detection is only available for image volumes. It can not be used for volumes that contain very large (tiled) images.
        </p>
    </div>

    <div class="row">
        <h2><a name="automatic"></a>Automatic detection</h2>
        <p>
            The automatic detection looks for the laser points as small spots that are much brighter than their immediate surroundings (a "difference of Gaussians" filter). It does not process the whole color image but only a single color channel, so laser points of a distinct color clearly stand out from the sea floor. The strongest spots that are found this way are the candidates for the laser points. Of these candidates, the group is chosen that has the strongest response <em>and</em> a plausible geometry for a laser rig. Candidates that are very close to the image border, as well as groups of points that are too close together or too far apart relative to the image size, are discarded.
        </p>
        <p>
            You have to configure the following for an automatic detection:
        </p>
        <ul>
            <li>
                <strong>Laser distance in cm:</strong> The distance between two neighbouring laser points on a flat sea floor.
            </li>
            <li>
                <strong>Number of laser points:</strong> The number of laser points of your laser point pattern (2, 3 or 4). The detection only accepts an image if exactly this number of laser points was found.
            </li>
            <li>
                <strong>Color channel:</strong> The color channel that is used to find the laser points. Choose the color of your lasers (red, green or blue). Gray uses the brightness of the image instead of a color and can be used for white laser points or if the color of the laser points is washed out in the images.
            </li>
        </ul>
        <p>
            The automatic detection fails for an image if it can not find the configured number of laser points or if the resulting image footprint is implausible (not positive or larger than 50 m²). In this case the image information page shows an error message. You can always annotate the laser points of the image manually and run the manual computation instead.
        </p>
    </div>

    <div class="row">
        <h2><a name="manual"></a>Manual computation</h2>
        <p>
            Instead of the automatic detection you can annotate the laser points yourself with point annotations and let BIIGLE compute the visual footprint from these annotations. This is the most accurate method to determine the visual footprint but it may be very time consuming.
        </p>
        <p>
            Use the same label for all laser point annotations, as you have to select this label when you request the computation. Each image must have between {{Biigle\Modules\Laserpoints\Image::MIN_POINTS}} and {{Biigle\Modules\Laserpoints\Image::MAX_POINTS}} laser point annotations, and all images of a volume must have the same number of laser point annotations. Images without any annotation of the selected label are skipped.
        </p>
    </div>

    <div class="row">
        <h2>Requesting the detection</h2>
        <p>
            The laser point detection can be requested for a whole image volume in the volume overview. Open the laser point tab in the sidebar on the left (<button class="btn btn-default btn-xs"><span class="fa fa-vector-square" aria-hidden="true"></span></button>), choose "Automatic" or "Manual", fill in the form and submit your request.
        </p>
        <p>
            The detection can also be requested for an individual image on the image information page (<button class="btn btn-default btn-xs"><span class="fa fa-info-circle" aria-hidden="true"></span></button>). This page shows the results of the previous detection run of the image, too: the area covered by the image, the number of laser points, the detection method, the distance between the laser points and the color channel that was used.
        </p>
        <p>
            Depending on the size of your volume, the laser point detection may take some time. You can check the progress using the "detected laser points" filter of the volume overview. This will show you all images where the automatic laser point detection was successful.
        </p>
    </div>

    <div class="row">
        <h2>Checking the results</h2>
        <p>
            If you choose to detect laser points automatically, make sure you check the results before further processing your data. The easiest way is to apply the "detected laser points" filter in the image volume overview and then cycle through all images that contain automatically detected laser points using the image annotation tool. Detected laser points will be displayed as small circles on the image. If the detected laser points were not correct for an image, annotate them manually and resubmit the laser point detection for the image.
        </p>
    </div>
@endsection
