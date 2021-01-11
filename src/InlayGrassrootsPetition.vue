<template>
  <div class="grpet">
    <div v-show="stage === 'loading'" >Petition Loading...</div>
    <div v-show="stage === 'loadingError'" class="grpet-error" >{{loadingError}}</div>

    <form action='#' @submit.prevent="submitForm" v-if="stage === 'form'">
      <h1>{{publicData.title}}</h1>

      <!-- todo image -->
      <h2>To: {{publicData.targetName}} <br />
        {{publicData.location}}</h2>

      <div class="petition-text" v-html="publicData.petitionHTML"></div>
      <div class="petition-form">
        <!-- ometer @todo -->
        <div>{{publicData.signatureCount}} / {{publicData.targetCount}}</div>

        <div v-if="acceptingSignatures">
          <div>
            <label :for="myId + 'fname'" >First name</label>
            <input
              required
              type="text"
              :id="myId + 'fname'"
              name="first_name"
              ref="first_name"
              :disabled="$root.submissionRunning"
              v-model="first_name"
              />
          </div>

          <div>
            <label :for="myId + 'lname'" >Last name</label>
            <input
              required
              type="text"
              :id="myId + 'lname'"
              name="last_name"
              ref="last_name"
              :disabled="$root.submissionRunning"
              v-model="last_name"
              />
          </div>

          <div>
            <label :for="myId + 'email'" >Email</label>
            <input
              required
              type="email"
              :id="myId + 'email'"
              name="email"
              ref="email"
              :disabled="$root.submissionRunning"
              v-model="email"
              />
          </div>

          <div v-show="email">
            <label :for="myId + 'email2'" >Re-enter Email</label>
            <input
              required
              type="email"
              :id="myId + 'email2'"
              name="email2"
              ref="email2"
              :disabled="$root.submissionRunning"
              v-model="email2"
              @input="checkEmailsMatch"

              />
          </div>

          <div class="ifg-submit">
            <button
              @click="wantsToSubmit"
              :disabled="submissionRunning"
              >{{ submissionRunning ? "Please wait.." : 'Sign' }}</button>
          </div>
        </div><!-- end if acceptingSignatures -->
      </div><!-- end .petition-form -->
    </form>

    <inlay-progress ref="progress"></inlay-progress>
  </div>
</template>
<style lang="scss">
.grpet {
  .error {
    color: #a00;
    text-align: center;
    padding: 1rem;
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
      publicData: {},
      // Form data
      first_name: '',
      last_name: '',
      email: '',
      email2: '',
    };
    console.log("zzzzzzzzzzzzz", d);
    return d;
  },
  computed: {
    submissionRunning() {
      return this.$root.submissionRunning;
    },
    acceptingSignatures() {
      if (this.publicData.status === 'Open') {
        return true;
      }
      return false;
    }
  },
  mounted() {
    // We need to send a request to load our petition.
    // First, identify which petition.
    var m = window.location.pathname.match(/^\/petitions\/([^/#?]+)/);
    if (!m) {
      this.stage = 'loadingError';
      this.loadingError = 'There was an error loading this petition (invalid URL). Please check your link.';
      return;
    }
    // Submit a request for the petition.
    const progress = this.$refs.progress;
    progress.startTimer(5, 100, true);
    this.inlay.request({method: 'get', body: { need: 'publicData', petitionSlug: m[1] }})
    .then(r => {
      console.log(r);
      if (r.publicData) {
        this.stage = 'form';
        this.publicData = r.publicData;
      }
      else {
        throw r;
      }
    })
    .catch(e => {
      this.loadingError = e.publicError ?? 'There was an error loading this petition.';
      this.stage = 'loadingError';
    })
    .then( () => {
      progress.cancelTimer();
    });
  },
  methods: {
    checkEmailsMatch() {
      if (this.email === this.email2) {
        this.$refs.email2.setCustomValidity('');
      }
      else {
        this.$refs.email2.setCustomValidity('Emails do not match');
      }
    },
    wantsToSubmit() {
      // validate all fields.
    },
    submitForm() {
      // Form is valid according to browser.
      this.$root.submissionRunning = true;
      const d = {};
      Object.keys(this.$root.values).forEach(fieldName => {
        if (this.$root.inlay.initData.fieldDefs[fieldName].include) {
          d[fieldName] = this.$root.values[fieldName];
        }
      });
      const progress = this.$refs.progress;
      progress.startTimer(5, 20, 1);
      this.inlay.request({method: 'post', body: d})
        .then(r => {
          if (r.token) {
            d.token = r.token;
            progress.startTimer(6, 80);
            // Force 5s wait for the token to become valid
            return new Promise((resolve, reject) => {
              window.setTimeout(resolve, 5000);
            });
          }
          else {
            console.warn("unexpected resonse", r);
            throw (r.error || 'Unknown error');
          }
        })
        .then(() => {
          progress.startTimer(2, 100);
          return this.inlay.request({method: 'post', body: d});
        })
        .then(r => {
          if (r.error) {
            throw (r.error);
          }
          this.stage = 'thanks';
          progress.cancelTimer();
        })
        .catch(e => {
          console.error(e);
          if (typeof e === 'String') {
            alert(e);
          }
          else {
            alert("Unexpected error");
          }
          this.$root.submissionRunning = false;
          progress.cancelTimer();
        });
    }
  }
}
</script>
