import Vue from 'vue';
import InlayGrassrootsPetition from './InlayGrassrootsPetition.vue';
import InlayGrassrootsPetitionAdmin from './InlayGrassrootsPetitionAdmin.vue';

(() => {
  if (!window.inlayGrpetInit) {
    // This is the first time this *type* of Inlay has been encountered.
    // We need to define anything global here.

    // Create the boot function.
    window.inlayGrpetInit = inlay => {
      const inlayNode = document.createElement('div');
      inlay.script.insertAdjacentElement('afterend', inlayNode);

      // We need to choose the UX we offer based on the URL.
      // Supported URLs are:
      // /petitions/<slug>
      // /petition-admin/
      var path = window.location.pathname;
      // Default ux is the public petition.
      var ux = InlayGrassrootsPetition;
      // Check for public petition page.
      if (path.match(/^\/petitions-admin\/?$/)) {
        ux = InlayGrassrootsPetitionAdmin;
      }

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
        render: h => h(ux, {props: {inlay}}),
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
