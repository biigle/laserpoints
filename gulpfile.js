"use strict";
process.env.DISABLE_NOTIFIER = true;

var gulp    = require('gulp');
var elixir  = require('laravel-elixir');
var angular = require('laravel-elixir-angular');
var shell   = require('gulp-shell');

elixir(function (mix) {
	process.chdir('src');
	// mix.sass('main.scss', 'public/assets/styles/main.css');
	mix.angular('resources/assets/js/laserpoints/', 'public/assets/scripts', 'main.js');
    mix.angular('resources/assets/js/transects/', 'public/assets/scripts', 'transects.js');
    mix.angular('resources/assets/js/annotations/', 'public/assets/scripts', 'annotations.js');
    mix.task('publish', 'public/assets/**/*');
});

gulp.task('publish', function () {
    gulp.src('').pipe(shell('php ../../../../artisan vendor:publish --provider="Dias\\Modules\\Laserpoints\\LaserpointsServiceProvider" --tag public --force'));
});
