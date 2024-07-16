<script>
import Circle from '@biigle/ol/style/Circle';
import Feature from '@biigle/ol/Feature';
import LaserpointsApi from '../api/laserpoints';
import Point from '@biigle/ol/geom/Point';
import Stroke from '@biigle/ol/style/Stroke';
import Style from '@biigle/ol/style/Style';
import VectorLayer from '@biigle/ol/layer/Vector';
import VectorSource from '@biigle/ol/source/Vector';
import {Events} from '../import';

/**
 * The plugin component to change the settings for the laser points in the annotation
 * tool.
 *
 * @type {Object}
 */
export default {
    props: {
        settings: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            opacityValue: '1',
            currentImageId: null,
            currentImage: null,
            cache: {},
        };
    },
    computed: {
        opacity() {
            return parseFloat(this.opacityValue);
        },
        shown() {
            return this.opacity > 0;
        },
    },
    methods: {
        maybeFetchLaserpoints(id) {
            if (this.shown && !this.cache.hasOwnProperty(id)) {
                this.cache[id] = LaserpointsApi.get({image_id: id})
                    .then((response) => response.data);
            }

            return this.cache[id];
        },
        updateCurrentImage(id, image) {
            this.layer.getSource().clear();
            this.currentImageId = id;
            this.currentImage = image;
        },
        maybeDrawLaserpoints(data) {
            if (data && data.method !== 'manual' && data.points && data.points.length > 0) {
                var height = this.currentImage.height;
                this.layer.getSource().addFeatures(data.points.map(function (point) {
                    return new Feature({geometry: new Point([
                        // Swap y coordinates for OpenLayers.
                        point[0], height - point[1]
                    ])});
                }));
            }
        },
        extendMap(map) {
            map.addLayer(this.layer);
        },
    },
    watch: {
        opacity(opacity, oldOpacity) {
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
        currentImageId(id) {
            if (this.shown) {
                this.maybeFetchLaserpoints(id).then(this.maybeDrawLaserpoints);
            }
        },
    },
    created() {
        this.layer = new VectorLayer({
            source: new VectorSource(),
            style: [
                new Style({
                    image: new Circle({
                        radius: 6,
                        stroke: new Stroke({
                            color: 'white',
                            width: 4
                        })
                    })
                }),
                new Style({
                    image: new Circle({
                        radius: 6,
                        stroke: new Stroke({
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

        if (this.settings.has('laserpointOpacity')) {
            this.opacityValue = this.settings.get('laserpointOpacity');
        }

        Events.$on('images.fetching', this.maybeFetchLaserpoints);
        Events.$on('images.change', this.updateCurrentImage);
        Events.$on('annotations.map.init', this.extendMap);
    },
};
</script>
