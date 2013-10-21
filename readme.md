## Laravel Facade Dump Generator

Create a json dump of Laravel's Facade structure. Meant for having an auto updated cheat sheet

Require this package in your composer.json and run composer update:

    "gsaulmon/laravel-facade-dump": "dev-master"

After updating composer, add the ServiceProvider to the providers array in app/config/app.php

    'Gsaulmon\LaravelFacadeDump\FacadeDumpServiceProvider',

You can now create the json dump

    php artisan facade-dump

Note: bootstrap/compiled.php has to be cleared first, so run `php artisan clear-compiled` before generating (and `php artisan optimize` after..)

You can also publish the config-file to change implementations (ie. interface to specific class) or add an override

    php artisan config:publish gsaulmon/laravel-facade-dump


https://github.com/barryvdh/laravel-ide-helper was used as the base for this module & I highly recommend it for IDE auto-completion.
