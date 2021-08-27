<template>
  <div class="grpet">
    <div v-show="stage === 'loading'" >Petition Loading...
      <inlay-progress ref="loadingProgress"></inlay-progress>
    </div>
    <div v-show="stage === 'loadingError'" class="error" >
      <p class="error">{{loadingError}}</p>
      <p><a href="/petitions" >View all petitions</a></p>
    </div>

    <div v-if="stage === 'petitionsList'" >
      <h2>Petitions</h2>

      <form class="filters">
        <div class="text-filter">
          <label for="grpet-filter-text">
            <svg viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" width="18" height="18"><path d="M14.5 14.5l-4-4m-4 2a6 6 0 110-12 6 6 0 010 12z" stroke="currentColor"></path></svg>
            Search</label>
          <input
            id="grpet-filter-text"
            type="text"
            v-model="filters.text"
            title="Search"
            placeholder=" e.g. Sheffield"
            />
        </div>
        <div class="campaign-filter">
          <label
            for="grpet-filter-campaign"
            ><svg viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" width="18" height="18"><path d="M4.076 6.47l.495.07-.495-.07zm-.01.07l-.495-.07.495.07zm6.858-.07l.495-.07-.495.07zm.01.07l-.495.07.495-.07zM9.5 12.5v.5a.5.5 0 00.5-.5h-.5zm-4 0H5a.5.5 0 00.5.5v-.5zm-.745-3.347l.396-.306-.396.306zm5.49 0l-.396-.306.396.306zM6 15h3v-1H6v1zM3.58 6.4l-.01.07.99.14.01-.07-.99-.14zM7.5 3a3.959 3.959 0 00-3.92 3.4l.99.14A2.959 2.959 0 017.5 4V3zm3.92 3.4A3.959 3.959 0 007.5 3v1a2.96 2.96 0 012.93 2.54l.99-.14zm.01.07l-.01-.07-.99.14.01.07.99-.14zm-.79 2.989c.63-.814.948-1.875.79-2.99l-.99.142a2.951 2.951 0 01-.59 2.236l.79.612zM9 10.9v1.6h1v-1.599H9zm.5 1.1h-4v1h4v-1zm-3.5.5v-1.599H5V12.5h1zM3.57 6.47a3.951 3.951 0 00.79 2.989l.79-.612a2.951 2.951 0 01-.59-2.236l-.99-.142zM6 10.9c0-.823-.438-1.523-.85-2.054l-.79.612c.383.495.64.968.64 1.442h1zm3.85-2.054C9.437 9.378 9 10.077 9 10.9h1c0-.474.257-.947.64-1.442l-.79-.612zM7 0v2h1V0H7zM0 8h2V7H0v1zm13 0h2V7h-2v1zM3.354 3.646l-1.5-1.5-.708.708 1.5 1.5.708-.708zm9 .708l1.5-1.5-.708-.708-1.5 1.5.708.708z" fill="currentColor"></path></svg>
            Campaign</label>
          <select
            id="grpet-filter-campaign"
            v-model="filters.campaignID"
            title="Filter by campaign"
            >
            <option value="">All campaigns</option>
            <option
              v-for="campaign in campaigns"
              :value="campaign.id"
              :key="campaign.id"
              >{{campaign.label}}</option>
          </select>
        </div>
      </form>

      <p>Showing {{filteredPetitions.length}} petitions. <a href="/petitions-admin">Start your own petition</a></p>
      <ul class="grpet-petitions-list">
        <li v-for="petition in filteredPetitions" :key="petitions.id">
          <article>
            <div class="image">
              <a :href="'/petitions/' + petition.slug"><img :src="petition.imageUrl" :alt="petition.imageAlt" /></a>
            </div>
            <div class="texts">
              <h1><a :href="'/petitions/' + petition.slug">{{petition.petitionTitle}}</a></h1>
              <div class="campaign">
                <svg viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" width="18" height="18"><path d="M4.076 6.47l.495.07-.495-.07zm-.01.07l-.495-.07.495.07zm6.858-.07l.495-.07-.495.07zm.01.07l-.495.07.495-.07zM9.5 12.5v.5a.5.5 0 00.5-.5h-.5zm-4 0H5a.5.5 0 00.5.5v-.5zm-.745-3.347l.396-.306-.396.306zm5.49 0l-.396-.306.396.306zM6 15h3v-1H6v1zM3.58 6.4l-.01.07.99.14.01-.07-.99-.14zM7.5 3a3.959 3.959 0 00-3.92 3.4l.99.14A2.959 2.959 0 017.5 4V3zm3.92 3.4A3.959 3.959 0 007.5 3v1a2.96 2.96 0 012.93 2.54l.99-.14zm.01.07l-.01-.07-.99.14.01.07.99-.14zm-.79 2.989c.63-.814.948-1.875.79-2.99l-.99.142a2.951 2.951 0 01-.59 2.236l.79.612zM9 10.9v1.6h1v-1.599H9zm.5 1.1h-4v1h4v-1zm-3.5.5v-1.599H5V12.5h1zM3.57 6.47a3.951 3.951 0 00.79 2.989l.79-.612a2.951 2.951 0 01-.59-2.236l-.99-.142zM6 10.9c0-.823-.438-1.523-.85-2.054l-.79.612c.383.495.64.968.64 1.442h1zm3.85-2.054C9.437 9.378 9 10.077 9 10.9h1c0-.474.257-.947.64-1.442l-.79-.612zM7 0v2h1V0H7zM0 8h2V7H0v1zm13 0h2V7h-2v1zM3.354 3.646l-1.5-1.5-.708.708 1.5 1.5.708-.708zm9 .708l1.5-1.5-.708-.708-1.5 1.5.708.708z" fill="currentColor"></path></svg>
                Campaign: {{campaignNameFromID(petition.campaignID)}}</div>
              <div class="location" v-if="petition.location">
                <svg viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><path d="M7.5.5v14m7-7.005H.5m13 0a6.006 6.006 0 01-6 6.005c-3.313 0-6-2.694-6-6.005a5.999 5.999 0 016-5.996 6 6 0 016 5.996z" stroke="currentColor" stroke-linecap="square"></path></svg>
                {{petition.location}}</div>
              <div class="sigs-sign">
                <div class="signatures">
                  <svg viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" width="19" height="19"><path d="M10.5 14.49v.5h.5v-.5h-.5zm-10 0H0v.5h.5v-.5zm14 .01v.5h.5v-.5h-.5zM8 3.498a2.499 2.499 0 01-2.5 2.498v1C7.433 6.996 9 5.43 9 3.498H8zM5.5 5.996A2.499 2.499 0 013 3.498H2a3.499 3.499 0 003.5 3.498v-1zM3 3.498A2.499 2.499 0 015.5 1V0A3.499 3.499 0 002 3.498h1zM5.5 1A2.5 2.5 0 018 3.498h1A3.499 3.499 0 005.5 0v1zm5 12.99H.5v1h10v-1zm-9.5.5v-1.996H0v1.996h1zm2.5-4.496h4v-1h-4v1zm6.5 2.5v1.996h1v-1.997h-1zm-2.5-2.5a2.5 2.5 0 012.5 2.5h1a3.5 3.5 0 00-3.5-3.5v1zm-6.5 2.5a2.5 2.5 0 012.5-2.5v-1a3.5 3.5 0 00-3.5 3.5h1zM14 13v1.5h1V13h-1zm.5 1H12v1h2.5v-1zM12 11a2 2 0 012 2h1a3 3 0 00-3-3v1zm-.5-3A1.5 1.5 0 0110 6.5H9A2.5 2.5 0 0011.5 9V8zM13 6.5A1.5 1.5 0 0111.5 8v1A2.5 2.5 0 0014 6.5h-1zM11.5 5A1.5 1.5 0 0113 6.5h1A2.5 2.5 0 0011.5 4v1zm0-1A2.5 2.5 0 009 6.5h1A1.5 1.5 0 0111.5 5V4z" fill="currentColor"></path></svg>
                  {{petition.signatureCount.toLocaleString()}} signatures</div>
                <div class="buttons"><a class="button primary" :href="'/petitions/' + petition.slug" >Read / Sign<span class="visually-hidden"> {{petition.petitionTitle}}</span></a></div>
              </div>
            </div>
          </article>
        </li>
      </ul>
    </div>

    <form class="petition-form" action='#' @submit.prevent="submitForm" v-if="showTheForm">

      <div class="petition-info">
        <div class="petition-titles">
          <h1>{{publicData.petitionTitle}}</h1>
          <h2>To: {{publicData.targetName}}</h2>
        </div>

        <div class="petition-image" v-if="publicData.imageUrl">
          <img :src="publicData.imageUrl" :alt="publicData.imageAlt" />
        </div>

        <div class="petition-what" v-html="publicData.petitionWhatHTML"></div>

      </div>
      <div class="petition-form">
        <ometer :count="publicData.signatureCount"
           :target="publicData.targetCount"
           :stretch-target="stretchTarget"
           :last-signer="publicData.lastSigner"
           stmt="Signatures"></ometer>

        <div v-if="acceptingSignatures && stage === 'form'">
          <div>
            <label :for="myId + 'fname'" >First name</label>
            <input
              required
              type="text"
              name="first_name"
              ref="first_name"
              :id="myId + 'fname'"
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

          <inlay-socials
            icons=1
            :socials="socials"
            :button-style="inlay.initData.socialStyle" ></inlay-socials>

          <p><a href @click.prevent="stage='thanksFinalHTML'" >Skip</a></p>

        </div>

        <div v-show="stage === 'thanksFinalHTML'" >
          <div v-html="publicData.thanksFinalHTML"></div>
        </div>
      </div><!-- end .petition-form -->
    </form>

    <div class="petition-why" v-html="publicData.petitionWhyHTML"></div>
    <div class="petition-who" >Organisers: <em>{{publicData.organiser}}</em>.</div>
    <div class="grpet-updates" v-if="(publicData.updates || {length:0}).length > 0">
      <h2>Updates</h2>
      <div v-for="update in publicData.updates" class="update">
        <div class="text">
          <p>{{update.when}}</p>
          <div v-html="update.html"></div>
        </div>
        <div class="image" v-if="update.imageUrl"><img :src="update.imageUrl" :alt="update.imageAlt" /></div>
      </div>
    </div>

    <div class="grpet-social" v-if="showTheForm">
      <h2>Share this petition</h2>
      <inlay-socials icons=1
        :socials="socials"
        :button-style="inlay.initData.socialStyle"
        ></inlay-socials>
    </div>

  </div>
</template>
<style lang="scss">
@use "sass:math";
.grpet {
  padding-top: 2rem;

  // Prevent that weird thing when there's nothing to show and the site's footer sort of floats mid-window.
  min-height: 70vh;

  .error {
    color: #a00;
  }

  // Visual users will have enough context to not need to see this.
  .visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    text-indent: 2px;
  }

  // Icons.
  svg {
    display: inline-block;
    margin-right: 0.5rem;
    vertical-align: baseline;
  }

  form.filters {
    display: flex;
    align-items: center;
    margin-bottom: 2rem;

    label {
      display: block;
      line-height:2;
    }

    .text-filter {
      flex: 1 0 15rem;
      padding-right: 2rem;
    }

    .campaign-filter {
      flex: 0 0 auto;
    }

  }

  // Petitions list page
  ul.grpet-petitions-list {
    margin:0 0 2rem;
    padding: 0;
    &>li {
      list-style: none;
      padding:0;
      margin: 1rem 0;
      padding: 1rem;
      background: #f8f8f8;
    }
    article {
      margin:0;
      padding:0;
      display:flex;
      flex-wrap: wrap;
    }
    .image {
      flex: 1 0 16rem;
      img {
        display: block;
        width: 100%;
        height: auto;
      }
    }
    .texts {
      flex: 4 0 16rem;
      padding-left: 2rem;
    }
    h1 {
      margin: 0 0 0.5rem;
      font-size: 2rem;
      line-height: 1.2;
    }
    .campaign {
    }
    .location {
    }
    .sigs-sign {
      display: flex;
      align-items: baseline;
    }
    .signatures {
      flex:1 0 8rem;
      padding-right: 2rem;
      font-weight: bold;
    }
    .buttons {
      flex:0 0 auto;
      a {
        margin:0;
      }
    }
  }

  // Petition form page

  $colgap: 3rem;
  $flexgap: math.div($colgap, 2);
  // Accessibly swap presentation order of titles.
  .petition-titles {
    display: flex;
    flex-direction: column;

    h2 { order: 1; margin: 0 0 1rem; line-height: 1.2; text-transform: none; font-size: 2rem; }
    h1 { order: 2; text-transform: none; margin-top: 0; line-height: 1.2; }
  }
  form.petition-form {
    display: flex;
    flex-wrap: wrap;
    padding:0;
    margin: 0 (-$flexgap) 2rem;
    border-bottom: solid 1px #ddd;
  }
  .ipetometer { margin-top: 1rem; }
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
  .petition-why {
    padding-bottom: 2rem;
  }
  .petition-what {
    padding-bottom: 1rem;
  }
  .petition-who {
    margin-bottom: 1rem;
  }

  .grpet-updates {
    background-color: #f8f8f8;
    padding: 1rem;
    h2 {
      margin-top: 0;
    }

    .update {
      display: flex;
      flex-wrap: wrap;
      align-items: top;
      margin-bottom: 2rem;
      background: white;

      .text {
        flex: 4 0 18rem;
        padding: 1rem;
      }
      .image {
        flex: 1 0 18rem;
        img {
          display:block;
          width: 100%;
          height: auto;
        }
      }
    }
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

      // Array of petitions from publicPetitionList
      petitions: [],
      campaigns: {},
      filters: {campaignID: null, text: ''},
      // Object of data of current petition from its publicData
      publicData: {},
      // Petition slug (if poss) from the url.
      petitionSlug: (window.location.pathname.match(/^\/petitions\/([^#?]+)/) || [null, null])[1],

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
    /*
    petitionSocials() {
      return [
        { name: 'facebook' },
        { name: 'twitter', tweet: this.publicData.tweet },
        { name: 'email', subject: this.publicData.petitionTitle },
        { name: 'whatsapp', whatsappText: this.publicData.petitionTitle },
      ];
    },
    */
    filteredPetitions() {
      return this.petitions.filter(p => {
        if (this.filters.campaignID && p.campaignID != this.filters.campaignID) {
          return false;
        }
        if (!this.filters.text) return true;
        // Does text filter match?
        const parts = this.filters.text.toLowerCase().split(/\s+/);
        var m = true;
        parts.forEach(part => {
          if ((p.location + p.petitionTitle).toLowerCase().indexOf(part) === -1) {
            m = false;
          }
        });
        return m;
      });
    },
    stretchTarget() {
      if (this.publicData.signatureCount > this.publicData.targetCount) {
        // We need to do a stretch target.

        var m = (this.publicData.signatureCount > 10000)
          ? 10000
          : ((this.publicData.signatureCount > 1000)
            ? 1000
            : 100);
        return Math.ceil((this.publicData.signatureCount / 0.75) / m) * m;
      }
      else {
        return this.publicData.targetCount;
      }
    },
    isStretchTarget() {
      return (this.publicData.signatureCount > this.publicData.targetCount);
    },
    showTheForm() {
      return ['form', 'thanksShareAsk', 'thanksFinalHTML'].includes(this.stage);
    },
    submissionRunning() {
      return this.$root.submissionRunning;
    },
    acceptingSignatures() {
      if (this.publicData.status === 'Open') {
        return true;
      }
      return false;
    },
    socials() {
      // Take deep copy.
      let s = JSON.parse(JSON.stringify(this.inlay.initData.socials));

      // If the petition has configured tweet text, use that
      if (this.publicData.tweet) {
        s.find(i => i.name === 'twitter').tweet = this.publicData.tweet;
      }

      return s;
    }
  },
  mounted() {
    const progress = this.$refs.loadingProgress;

    // We need to send a request to load our petition.
    // First, identify which petition.

    if (!this.petitionSlug) {
      // We'll be presenting the list of petitions.
      progress.startTimer(5, 100, {reset: 1});
      this.inlay.request({method: 'get', body: { need: 'publicPetitionList' }})
      .then(r => {
        console.log(r);
        if (r.petitions) {
          this.stage = 'petitionsList';
          this.petitions = r.petitions;
          this.campaigns = r.campaigns;
        }
        else {
          throw r;
        }
      })
      .catch(e => {
        this.loadingError = e.publicError ?? 'There was an error loading this petition.';
        this.stage = 'loadingError';
      })
      .finally( () => {
        progress.cancelTimer();
      });

      return;
    }

    // We need the public data.
    if (window.grpetPreload && window.grpetPreload.publicData && window.grpetPreload.publicData.slug && window.grpetPreload.publicData.slug === this.petitionSlug) {
      // Preloaded!
      this.publicData = window.grpetPreload.publicData;
      this.stage = 'form';
      return;
    }

    // Submit a request for the petition.
    progress.startTimer(5, 100, {reset: 1});
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
    campaignNameFromID(campaignID) {
      return (this.campaigns[campaignID] || {label: ''}).label;
    },
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
      progress.startTimer(5, 20, {reset: 1});
      this.inlay.request({method: 'post', body: d})
        .then(r => {
          if (r.token) {
            d.token = r.token;
            progress.startTimer(6, 80, {easing: false});
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
          this.publicData.lastSigner = {
            name: this.first_name,
            ago: 'just now'
          };
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
