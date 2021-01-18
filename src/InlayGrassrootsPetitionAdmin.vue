<template>
  <div class="grpet-admin">
    <div v-show="stage === 'loading'" >{{loadingMessage}}</div>

    <form v-show="stage === 'unauthorised'"
          class="unauthorised"
          @submit.prevent="submitAuthEmail"
      >
      <div>
        <h2>Unauthorised</h2>
        <label :for="myId + 'authEmail'" >Enter the email you registered with</label>
        <input
          type="email"
          :id="myId + 'authEmail'"
          :disabled="$root.submissionRunning"
          v-model="authEmail"
          required
          />
        <button
          class="primary"
          type="submit"
          :disabled="$root.submissionRunning"
          >Send one-time login link</button>
      </div>
    </form>

    <div v-show="stage === 'authSent'" >
      <h2>Unauthorised</h2>
      <p>Thanks, check your inbox for an email from us which contains a link to let you in.</p>
      <p>(If you can't find it, check your spam/junk folder! And if you find it in there, be sure to click the Not Spam button so it doesn't happen with other emails from us.)</p>
    </div>
    <div v-if="stage === 'listPetitions'" class="grpet-list" >
      <h2>Your petitions</h2>
      <ul class="petition">
        <li v-for="petition in petitions" :key="petition.id">
          <p><a :href="'/petitions/' + petition.slug" target="_blank" rel="noopener" >{{ petition.title }}</a></p>
          <p>{{petition.signatureCount}} / {{petition.targetCount}}</p>
        </li>
      </ul>
    </div>

    <div v-show="stage === 'loadingError'" class="grpet-error" >{{loadingError}}</div>

    <inlay-progress ref="loadingProgress"></inlay-progress>
  </div>
</template>
<style lang="scss">
.grpet-admin {
  .grpet-error {
    color: #a00;
    text-align: center;
    padding: 1rem;
  }
  label {
    display: block;
  }
}
</style>
<script>
import InlayProgress from './InlayProgress.vue';
export default {
  props: ['inlay'],
  components: {InlayProgress},
  data() {
    const d = {
      stage: 'loading',
      myId: this.$root.getNextId(),
      loadingError: 'There was an error loading this petition, please get in touch.',
      loadingMessage: 'Loading...',

      authEmail: '',
      authToken: '',

      petitions: [],
    };
    return d;
  },
  computed: {
  },
  mounted() {
    // Are we authenticated?
    if (this.authToken) {
      // Assume so.
      this.bootList();
      return;
    }

    // Look for an auth hash as the fragment.
    var authHash = (window.location.hash || '#').substr(1);

    if (authHash.match(/^[0-9a-z]{16}$/)) {
      // Found a hash. Try to authenticate.
      this.inlay.request({method: 'post', body: { need: 'adminSessionToken', authHash }})
        .then(r => {
          if (r.success) {
            this.authToken = r.token;
            this.bootList();
          }})
        .catch(e => { this.stage = 'unauthorised'; return; });
    }
    else {
      // There's no hash. Perhaps we're already authorised.
      this.stage = 'unauthorised';;
    }
  },
  methods: {
    bootList() {
      this.stage = 'loading';
      this.loadingMessage = "Loading petitions...";
      this.petitions = [];
      // @todo send request to load petitions.
      this.inlay.request({method: 'post', body: { need: 'adminPetitionsList', authToken: this.authToken }})
        .then(r => {
          if (r.petitions) {
            this.petitions = r.petitions;
            this.stage = 'listPetitions';
          }
          else {
            console.warn("hmmm", r);
          }
        })
        .catch(e => { this.stage = 'unauthorised'; return; });
    },
    submitAuthEmail() {
      // Send request for auth email.
      const progress = this.$refs.loadingProgress;
      progress.startTimer(5, 100, true);
      this.$root.submissionRunning = true;
      this.inlay.request({method: 'post', body: { need: 'adminAuthEmail', email: this.authEmail }})
        .then(r => {
          if (r.publicError) {
            alert(r.publicError);
          }
          else {
            this.stage = 'authSent';
          }
        })
        .catch(e => {
          // Inlay has already chucked up an alert()
        })
        .finally( () => {
          this.$root.submissionRunning = false;
          progress.cancelTimer();
        });
    },
  }
}
</script>
