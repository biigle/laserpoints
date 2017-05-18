/**
 * The plugin component to change the settings for the laser points
 *
 * @type {Object}
 */
biigle.$require('annotations.components.settingsTabPlugins').laserPoints = {
    props: {
        settings: {
            type: Object,
            required: true,
        },
    },
    data: function () {
        return {
            opacityValue: '1',
            currentImageId: null,
            currentImage: null,
            cache: {},
        };
    },
    computed: {
        opacity: function () {
            return parseFloat(this.opacityValue);
        },
        shown: function () {
            return this.opacity > 0;
        },
        laserpointsApi: function () {
            return biigle.$require('api.laserpoints');
        },
        layer: function () {
            return new ol.layer.Vector({
                source: new ol.source.Vector(),
                style: [
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
                    }),
                ],
                zIndex: 3,
                updateWhileAnimating: true,
                updateWhileInteracting: true,
            });
        },
    },
    methods: {
        maybeFetchLaserpoints: function (id) {
            if (this.shown && !this.cache.hasOwnProperty(id)) {
                this.cache[id] = this.laserpointsApi.get({image_id: id})
                    .then(function (response) {
                        return response.data;
                    });
            }

            return this.cache[id];
        },
        updateCurrentImage: function (id, image) {
            this.layer.getSource().clear();
            this.currentImageId = id;
            this.currentImage = image;
        },
        maybeDrawLaserpoints: function (data) {
            if (data && data.method !== 'manual' && data.points && data.points.length > 0) {
                var height = this.currentImage.height;
                this.layer.getSource().addFeatures(data.points.map(function (point) {
                    return new ol.Feature({geometry: new ol.geom.Point([
                        // Swap y coordinates for OpenLayers.
                        point[0], height - point[1]
                    ])});
                }));
            }
        },
    },
    watch: {
        opacity: function (opacity, oldOpacity) {
            if (opacity < 1) {
                this.settings.set('laserpointOpacity', opacity);
            } else {
                this.settings.delete('laserpointOpacity');
            }

            if (oldOpacity === 0) {
                this.maybeFetchLaserpoints(this.currentImageId)
                    .then(this.maybeDrawLaserpoints);
            }

            if (opacity === 0) {
                this.layer.getSource().clear();
            }

            this.layer.setOpacity(opacity);
        },
        currentImageId: function (id) {
            if (this.shown) {
                this.maybeFetchLaserpoints(id).then(this.maybeDrawLaserpoints);
            }
        },
    },
    created: function () {
        if (this.settings.has('laserpointOpacity')) {
            this.opacityValue = this.settings.get('laserpointOpacity');
        }

        var events = biigle.$require('biigle.events');
        events.$on('images.fetching', this.maybeFetchLaserpoints);
        events.$on('images.change', this.updateCurrentImage);

        var map = biigle.$require('annotations.stores.map');
        map.addLayer(this.layer);
    },
};
