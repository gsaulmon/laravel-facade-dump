<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Filename
    |--------------------------------------------------------------------------
    |
    | The default path to the helper file
    |
    */

        'filename' => 'laravel_facade_mapping.json',
        'helper_files' => array(
                base_path() . '/vendor/laravel/framework/src/Illuminate/Support/helpers.php',
        ),
    /*
    |--------------------------------------------------------------------------
    | Extra classes
    |--------------------------------------------------------------------------
    |
    | These implementations are not really extended, but called with magic functions
    |
    */

        'extra' => array(
                'Artisan' => array('Illuminate\Foundation\Artisan'),
                'Eloquent' => array('Illuminate\Database\Eloquent\Builder', 'Illuminate\Database\Query\Builder'),
                'Session' => array('Illuminate\Session\Store'),
                'SSH' => array('Illuminate\Remote\Connection'),
        ),

        'magic' => array(
                'Log' => array(
                        'debug' => 'Monolog\Logger::addDebug',
                        'info' => 'Monolog\Logger::addInfo',
                        'notice' => 'Monolog\Logger::addNotice',
                        'warning' => 'Monolog\Logger::addWarning',
                        'error' => 'Monolog\Logger::addError',
                        'critical' => 'Monolog\Logger::addCritical',
                        'alert' => 'Monolog\Logger::addAlert',
                        'emergency' => 'Monolog\Logger::addEmergency',
                )
        ),
    /*
    |--------------------------------------------------------------------------
    | Overrides
    |--------------------------------------------------------------------------
    |
    | Allow for manual over-riding of attributes or sections
    |
    */

        'overrides' => array(
            //'View.methods.Illuminate\View\Environment.__construct.desc' => 'Example',
            //'View.methods.Illuminate\View\Environment.addExtension' => array('desc' => 'Example', 'name' => 'Example', 'params' => '$example = true')
        )

);
