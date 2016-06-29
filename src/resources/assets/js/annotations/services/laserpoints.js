/**
 * @namespace dias.annotations
 * @ngdoc service
 * @name laserpoints
 * @memberOf dias.annotations
 * @description Wrapper service the laserpoint information of an image
 */
angular.module('dias.annotations').service('laserpoints', function (LaserpointsImage, map, images) {
    "use strict";

    // maps image ID to the laserpoints object of the image
    var cache = {};

    var shown = false;

    var cancelUpdate;

    // a circle with a red and white stroke
    var style = [
        new ol.style.Style({
            image: new ol.style.Circle({
                radius: 6,
                stroke: new ol.style.Stroke({
                    color: 'white',
                    width: 4
                })
            })
        }),
        new ol.style.Style({
            image: new ol.style.Circle({
                radius: 6,
                stroke: new ol.style.Stroke({
                    color: '#ff0000',
                    width: 2,
                    lineDash: [1]
                })
            })
        })
    ];

    var source = new ol.source.Vector();
    var layer = new ol.layer.Vector({
        source: source,
        style: style,
        zIndex: 3
    });

    map.addLayer(layer);

    var showPoints = function (laserpoints) {
        if (laserpoints.method !== 'heuristic' || laserpoints.points.length === 0) {
            return;
        }

        var height = images.currentImage.height;

        laserpoints.points.forEach(function (coordinates) {
            var feature = new ol.Feature({geometry: new ol.geom.Point([
                // swap y coordinates for OpenLayers
                coordinates[0], height - coordinates[1]
            ])});
            source.addFeature(feature);
        });
    };

    var updateImage = function (e, image) {
        source.clear();

        if (!cache.hasOwnProperty(image._id)) {
            cache[image._id] = LaserpointsImage.get({image_id: image._id});
        }

        cache[image._id].$promise.then(showPoints);
    };

    this.show = function (scope) {
        if (shown) return;

        cancelUpdate = scope.$on('image.shown', updateImage);
        shown = true;
        if (images.currentImage) {
            updateImage(null, images.currentImage);
        }
    };

    this.hide = function () {
        if (!shown) return;

        cancelUpdate();
        shown = false;
    };

    this.setOpacity = function (opacity) {
        layer.setOpacity(opacity);
    };
});
