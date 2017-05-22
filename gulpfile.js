"use strict"

var gulp = require('gulp');
var h = require('gulp-helpers');
var publish = h.publish('Biigle\\Modules\\Laserpoints\\LaserpointsServiceProvider', 'public');

h.paths.sass = 'src/resources/assets/sass/';
h.paths.js = 'src/resources/assets/js/';
h.paths.public = 'src/public/assets/';

gulp.task('js', function (cb) {
    h.js('**/*.js', 'main.js', cb);
});

gulp.task('watch', function () {
    gulp.watch(h.paths.js + '**/*.js', ['js']);
    gulp.watch(h.paths.public + '**/*', publish);
});

gulp.task('default', ['js'], publish)
