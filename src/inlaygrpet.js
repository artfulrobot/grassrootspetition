import Vue from 'vue';
import InlayGrassrootsPetition from './InlayGrassrootsPetition.vue';

(() => {
  if (!window.inlayGrpetInit) {
    // This is the first time this *type* of Inlay has been encountered.
    // We need to define anything global here.

    // Create the boot function.
    window.inlayGrpetInit = inlay => {
      console.log("boooooooooting", inlay);
      const inlayNode = document.createElement('div');
      inlay.script.insertAdjacentElement('afterend', inlayNode);
      /* eslint no-unused-vars: 0 */
      const app = new Vue({
        el: inlayNode,
        data() {
          var d = {
            inlay,
            submissionRunning: false,
            formID: 0
          };
          return d;
        },
        render: h => h(InlayGrassrootsPetition, {props: {inlay}}),
        methods: {
          // Generate a unique ID.
          getNextId() {
            this.formID++;
            return `i${this.inlay.publicID}-${this.formID}`;
          }
        }
      });
    };
  }
})();
