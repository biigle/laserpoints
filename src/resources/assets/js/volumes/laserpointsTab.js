/**
 * Content of the laserpoints tab in the volume overview sidebar
 */
Vue.component('laserpoints-form', {
    mixins: [biigle.$require('core.mixins.loader')],
    data: function () {
        return {
            distance: 0,
        };
    },
    methods: {
        submit: function () {
            this.startLoading();
        },
    },
});
