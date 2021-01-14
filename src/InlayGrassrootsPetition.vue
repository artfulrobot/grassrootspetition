<template>
  <div class="grpet">
    <div v-show="stage === 'loading'" >Petition Loading...
      <inlay-progress ref="loadingProgress"></inlay-progress>
    </div>
    <div v-show="stage === 'loadingError'" class="grpet-error" >{{loadingError}}</div>

    <form action='#' @submit.prevent="submitForm" v-if="showTheForm">
      <div class="petition-info">
        <div class="petition-titles">
          <h1>xx{{publicData.title}}</h1>

          <h2>To: {{publicData.targetName}}</h2>
        </div>

        <div class="petition-image" v-if="publicData.imageUrl">
          <img :src="publicData.imageUrl" :alt="publicData.imageAlt" />
        </div>

        <div class="petition-text" v-html="publicData.petitionHTML"></div>
      </div>
      <div class="petition-form">
        <ometer :count="publicData.signatureCount"
           :target="publicData.targetCount"
           stmt="Signatures"></ometer>

        <div v-if="acceptingSignatures && stage === 'form'">
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
              @blur="leftEmailField"
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

          <div>
            <label :for="myId + 'phone'" >Phone (optional)</label>
            <input
              type="text"
              :id="myId + 'phone'"
              name="phone"
              ref="phone"
              :disabled="$root.submissionRunning"
              v-model="phone"
              />
            <div class="grpet-smallprint" v-show="phone.length > 0">
              Provide your phone number if you’re happy for us to get in touch about this and other projects.
            </div>
          </div>


          <div class="grpet-consent-intro" v-html="publicData.consentIntroHTML"></div>
          <div class="grpet-consent-options">
            <div class="grpet-radio-wrapper">
              <input
                name="optin"
                type="radio"
                required
                value="yes"
                :id="myId + 'optinYes'"
                :disabled="$root.submissionRunning"
                v-model="optin"
                /><label :for="myId + 'optinYes'">{{publicData.consentYesText}}</label>
            </div>
            <div class="grpet-radio-wrapper">
              <input
                name="optin"
                type="radio"
                required
                value="no"
                :id="myId + 'optinNo'"
                :disabled="$root.submissionRunning"
                v-model="optin"
                /><label :for="myId + 'optinNo'">{{publicData.consentNoText}}</label>
            </div>
            <div class="grpet-consent-no-warning"
                 v-show="optin === 'no'" >{{publicData.consentNoWarning}}</div>
          </div>

          <div class="ifg-submit">
            <button
              class="primary grpet-submit"
              @click="wantsToSubmit"
              :disabled="submissionRunning"
              >{{ submissionRunning ? "Please wait.." : 'Sign' }}</button>
          </div>
          <inlay-progress ref="submitProgress"></inlay-progress>
        </div><!-- end if acceptingSignatures -->
        <div v-show="stage === 'thanksShareAsk'" >
          <div v-html="publicData.thanksShareAskHTML"></div>

          <inlay-socials icons=1 :socials="inlay.initData.socials" :button-style="inlay.initData.socialStyle" ></inlay-socials>

          <p><a href @click.prevent="stage='thanksDonateAsk'" >Skip sharing</a></p>

        </div>
        <div v-show="stage === 'thanksDonateAsk'" >
          <div v-html="publicData.thanksDonateAskHTML"></div>
        </div>
      </div><!-- end .petition-form -->
    </form>
  </div>
</template>
<style lang="scss">
.grpet {
  .error {
    color: #a00;
    text-align: center;
    padding: 1rem;
  }
  $colgap: 2rem;
  $flexgap: ($colgap/2);
  // Accessibly swap presentation order of titles.
  .petition-titles {
    display: flex;
    flex-direction: column;

    h2 { order: 1; margin: 0; text-transform: none; font-size: 2rem; }
    h1 { order: 2; text-transform: none; margin-top: 0; }
  }
  form {
    display: flex;
    flex-wrap: wrap;
    padding:0;
    margin: 0 (-$flexgap) 2rem;
  }
  .petition-image {
    margin-bottom: 1rem;
    img { max-width: 100%; height:auto; display:block; }
  }
  .petition-info {
    padding: 0 $flexgap;
    flex: 2 0 20rem;
  }
  .petition-form {
    padding: 0 $flexgap;
    flex: 1 0 20rem;
  }

  label {
    display: block;
  }
  input[type="text"],
  input[type="email"] {
    width: 100%;
  }
  button {
    width: 100%;
  }

  .grpet-consent-intro {
    margin-top: 1rem;
    margin-bottom: 0.5rem;
  }
  .grpet-radio-wrapper {
    margin-bottom: 0.5rem;
  }
  // @todo move this to local stylesheet
  .grpet-consent-no-warning {
    color: #933202;
    font-style: italic;
    padding-left: 36px;
  }
}
</style>
<script>
import InlayProgress from './InlayProgress.vue';
import InlaySocials from './InlaySocials.vue';
import Ometer from './Ometer.vue';
export default {
  props: ['inlay'],
  components: {InlayProgress, Ometer, InlaySocials},
  data() {
    const d = {
      stage: 'loading',
      myId: this.$root.getNextId(),
      loadingError: 'There was an error loading this petition, please get in touch.',
      publicData: {},
      petitionSlug: (window.location.pathname.match(/^\/petitions\/([^/#?]+)/) || [null, null])[1],
      location: window.location.href,
      // Form data
      first_name: '',
      last_name: '',
      email: '',
      email2: '',
      phone: '',
      optin: null,
    };
    return d;
  },
  computed: {
    showTheForm() {
      return ['form', 'thanksShareAsk', 'thanksDonateAsk'].includes(this.stage);
    },
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
    if (!this.petitionSlug) {
      this.stage = 'loadingError';
      this.loadingError = 'There was an error loading this petition (invalid URL). Please check your link.';
      return;
    }
    // Submit a request for the petition.
    const progress = this.$refs.loadingProgress;
    progress.startTimer(5, 100, true);
    this.inlay.request({method: 'get', body: { need: 'publicData', petitionSlug: this.petitionSlug }})
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
    leftEmailField() {
      // Focus the email2 field after leaving email field, unless it's already OK.
      if (this.email2 !== this.email) {
        this.$refs.email2.focus();
      }
    },
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
      // Collect data to send.
      const d = {
        need: 'submitSignature',
        petitionSlug: this.petitionSlug,
        location: this.location,
        // User data
        first_name: this.first_name,
        last_name: this.last_name,
        phone: this.phone,
        email: this.email,
        optin: this.optin,
      };
      console.log("submitting ", d);
      const progress = this.$refs.submitProgress;
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
          // update signature count
          this.publicData.signatureCount++;
          this.stage = 'thanksShareAsk';
        })
        .catch(e => {
          console.error(e);
          if (typeof e === 'String') {
            alert(e);
          }
          else {
            alert("Unexpected error");
          }
        })
        .then( () => {
          progress.cancelTimer();
          this.$root.submissionRunning = false;
        });
    }
  }
}
</script>
