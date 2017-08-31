<?php

return [

    /*
    | label_id of a laser point
    */
    'label_id' => null,

    /*
    | Path to the Python executable.
    */
    'python' => '/usr/bin/python',

    /*
    | Path to the detect script.
    */
    'detect_script' => __DIR__.'/../resources/scripts/detect.py',

    /*
    | Path to the Delphi gather script.
    */
    'delphi_gather_script' => __DIR__.'/../resources/scripts/delphiGather.py',

    /*
    | Path to the Delphi apply script.
    */
    'delphi_apply_script' => __DIR__.'/../resources/scripts/delphiApply.py',

    /*
    | Directory for temporary files to share data between workers. Note that this
    | directory must be accessible for all workers! The storage directory might be a
    | good idea.
    */
    'tmp_dir' => storage_path('framework/cache/laserpoints'),
];
