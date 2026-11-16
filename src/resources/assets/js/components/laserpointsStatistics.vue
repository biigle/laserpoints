<template>
    <div class="laserpoints-statistics">
        <h4 class="laserpoints-statistics__title">
            Detection status
            <loader :active="loading"></loader>
        </h4>
        <div v-if="running" class="text-info laserpoints-statistics__running">
            A laser point detection is running. The numbers are updated automatically.
        </div>
        <p v-if="loaded && total === 0" class="text-muted">
            This volume has no images.
        </p>
        <div v-else-if="loaded" class="laserpoints-statistics__chart">
            <svg viewBox="0 0 42 42" class="laserpoints-statistics__donut" role="img" :aria-label="ariaLabel">
                <circle
                    class="laserpoints-statistics__ring"
                    cx="21"
                    cy="21"
                    :r="radius"
                    fill="transparent"
                    stroke-width="6"
                    ></circle>
                <circle
                    v-for="segment in segments"
                    :key="segment.key"
                    cx="21"
                    cy="21"
                    :r="radius"
                    fill="transparent"
                    stroke-width="6"
                    :stroke="segment.color"
                    :stroke-dasharray="segment.dasharray"
                    :stroke-dashoffset="segment.dashoffset"
                    >
                    <title v-text="segment.title"></title>
                </circle>
            </svg>
            <ul class="list-unstyled laserpoints-statistics__legend">
                <li v-for="item in items" :key="item.key" :title="item.description">
                    <span class="laserpoints-statistics__swatch" :style="{backgroundColor: item.color}"></span>
                    {{ item.name }}:
                    <strong v-text="item.value"></strong>
                    <span class="text-muted">({{ item.percent }}%)</span>
                </li>
            </ul>
        </div>
    </div>
</template>
<script>
import LaserpointsApi from '../api/laserpoints.js';
import {Events} from '../import.js';
import {handleErrorResponse} from '../import.js';
import {LoaderMixin} from '../import.js';

// Circumference of the donut is 100 so stroke lengths are percentages.
const RADIUS = 100 / (2 * Math.PI);
const POLL_INTERVAL = 5000;

/**
 * Statistics of the laser point detection in the laser points tab of the volume overview
 */
export default {
    mixins: [LoaderMixin],
    props: {
        volumeId: {
            type: Number,
            required: true,
        },
        // Name of the sidebar tab that contains this component.
        tab: {
            type: String,
            default: 'laserpoints',
        },
    },
    data() {
        return {
            loaded: false,
            open: false,
            stale: true,
            running: false,
            total: 0,
            successful: 0,
            failed: 0,
            none: 0,
            timeout: null,
            radius: RADIUS,
        };
    },
    computed: {
        items() {
            return [
                {
                    key: 'successful',
                    name: 'Successful',
                    description: 'Images with a successful laser point detection',
                    value: this.successful,
                    color: '#5cb85c', // $brand-success
                },
                {
                    key: 'failed',
                    name: 'Failed',
                    description: 'Images where the laser point detection failed',
                    value: this.failed,
                    color: '#d9534f', // $brand-danger
                },
                {
                    key: 'none',
                    name: 'No detection',
                    description: 'Images without a laser point detection',
                    value: this.none,
                    color: '#888888',
                },
            ].map((item) => {
                item.percent = this.percent(item.value);

                return item;
            });
        },
        segments() {
            let offset = 0;

            return this.items
                .filter((item) => item.value > 0)
                .map((item) => {
                    const length = this.total > 0 ? (item.value / this.total * 100) : 0;
                    const segment = {
                        key: item.key,
                        color: item.color,
                        title: `${item.name}: ${item.value} (${item.percent}%)`,
                        dasharray: `${length} ${100 - length}`,
                        // Start at the top of the circle.
                        dashoffset: 25 - offset,
                    };
                    offset += length;

                    return segment;
                });
        },
        ariaLabel() {
            return this.items
                .map((item) => `${item.name}: ${item.value}`)
                .join(', ');
        },
    },
    methods: {
        percent(value) {
            if (this.total === 0) {
                return 0;
            }

            return Math.round(value / this.total * 1000) / 10;
        },
        fetch() {
            if (this.loading) {
                return;
            }

            this.clearPoll();
            this.startLoading();
            LaserpointsApi.getVolumeStatistics({volume_id: this.volumeId})
                .then(this.update, handleErrorResponse)
                .finally(this.finishLoading);
        },
        update(response) {
            const stats = response.body;
            this.total = stats.total;
            this.successful = stats.successful;
            this.failed = stats.failed;
            this.none = stats.none;
            this.running = stats.running;
            this.loaded = true;
            // Fetch again when the tab is opened the next time if a detection is
            // running.
            this.stale = this.running;

            if (this.running && this.open) {
                this.schedulePoll();
            }
        },
        schedulePoll() {
            this.clearPoll();
            this.timeout = window.setTimeout(this.fetch, POLL_INTERVAL);
        },
        clearPoll() {
            if (this.timeout) {
                window.clearTimeout(this.timeout);
                this.timeout = null;
            }
        },
        handleOpen() {
            this.open = true;
            if (this.stale) {
                this.fetch();
            }
        },
        handleClose() {
            this.open = false;
            this.clearPoll();
        },
        handleSubmitted(volumeId) {
            if (volumeId !== this.volumeId) {
                return;
            }

            this.stale = true;
            if (this.open) {
                this.fetch();
            }
        },
    },
    created() {
        Events.on(`sidebar.open.${this.tab}`, this.handleOpen);
        Events.on(`sidebar.close.${this.tab}`, this.handleClose);
        Events.on('laserpoints.volume.submitted', this.handleSubmitted);
    },
    beforeUnmount() {
        this.clearPoll();
        Events.off(`sidebar.open.${this.tab}`, this.handleOpen);
        Events.off(`sidebar.close.${this.tab}`, this.handleClose);
        Events.off('laserpoints.volume.submitted', this.handleSubmitted);
    },
};
</script>

<style scoped>
    .laserpoints-statistics__title {
        margin-top: 0;
    }

    .laserpoints-statistics__running {
        margin-bottom: 10px;
    }

    .laserpoints-statistics__chart {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .laserpoints-statistics__donut {
        flex: 0 0 100px;
        width: 100px;
        height: 100px;
    }

    .laserpoints-statistics__ring {
        stroke: #444;
    }

    .laserpoints-statistics__legend {
        margin: 0;
    }

    .laserpoints-statistics__swatch {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 2px;
        margin-right: 3px;
    }
</style>
