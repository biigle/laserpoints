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
];
