<template>
  <div class="grpet-admin">
    <div v-show="stage === 'loading'" >{{loadingMessage}}</div>

    <form v-show="stage === 'unauthorised'"
          class="unauthorised"
          @submit.prevent="submitAuthEmail"
      >
      <div>
        <h2>Unauthorised</h2>
        <p class="grpet-error " v-show="getAuthHash()" >This link has expired</p>
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
          <article>
            <h1><a :href="'/petitions/' + petition.slug" target="_blank" rel="noopener" >{{ petition.title }}</a></h1>
            <span class="status" :class="petition.status" >{{getStatusMeta(petition.status).description }}</span>
            <p>Signatures: {{petition.signatureCount}} / {{petition.targetCount}}.</p>
            <ul>
              <li><a href @click.prevent="editPetition(petition)" >Edit petition (texts, targets etc.)</a></li>
              <li><a href @click.prevent="updatePetition(petition)" >Provide updates, mark Won or Closed</a></li>
              <li><a href @click.prevent="createEmail(petition)" >Email signers</a></li>
            </ul>
          </article>
        </li>
      </ul>
      <p><a href @click.prevent="createNewPetition()" >Create new petition</a></p>
    </div>

    <div v-if="stage === 'createNewPetition'"
          class="new-petition"
          @submit.prevent="submitAuthEmail"
    >
      <h2>Create new petition: choose type</h2>
      <ul v-for="campaign in campaigns" >
        <li>
          <p><strong>{{campaign.label}}</strong></p>
          <p>{{campaign.description}}</p>
          <p><a href @click.prevent="createNewPetitionFromType(campaign)" >Create local petition for this campaign</a></p>
        </li>
      </ul>
    </div><!-- /createNewPetition -->

    <form
      class="edit-petition"
      v-if="stage === 'editPetition'"
      @submit.prevent="savePetition"
    >
      <h2>{{ editingPetition ? 'Edit' : 'Create' }} Petition</h2>

      <div class="field" >
        <label :for="myId + 'petitionTitle'">Petition title</label>
        <input
          type="text" required
          :id="myId + 'petitionTitle'"
          :disabled="$root.submissionRunning"
          v-model="petitionBeingEdited.title"
        />
        <div class="field-help"></div>
      </div>

      <div class="field" >
        <label :for="myId + 'petitionTargetName'">Who/what is the petition to (the power holder)</label>
        <input
          type="text" required
          :id="myId + 'petitionTargetName'"
          :disabled="$root.submissionRunning"
          v-model="petitionBeingEdited.targetName"
          v-if="creatingPetition"
        />
        <p class="fixed" title="This can no longer be edited." v-if="editingPetition" >{{petitionBeingEdited.targetName}}</p>
      </div>

      <div class="field" >
        <label :for="myId + 'petitionLocation'">Where is this happening?</label>
        <input
          type="text" required
          :id="myId + 'petitionLocation'"
          :disabled="$root.submissionRunning"
          v-model="petitionBeingEdited.location"
          v-if="creatingPetition"
        />
        <p class="fixed" title="This can no longer be edited." v-if="editingPetition" >{{petitionBeingEdited.location}}</p>
      </div>

      <div class="field" >
        <label :for="myId + 'petitionWho'">Who’s organising this petition.</label>
        <input
          type="text" required
          :id="myId + 'petitionWho'"
          :disabled="$root.submissionRunning"
          v-model="petitionBeingEdited.who"
        />
        <div class="field-help">e.g. the name of your group.</div>
      </div>

      <div class="field" >
        <label :for="myId + 'petitionWhy'">Why should people sign?</label>
        <textarea
          required
          rows=5
          cols=60
          :id="myId + 'petitionWhy'"
          :disabled="$root.submissionRunning"
          v-model="petitionBeingEdited.why"
          ></textarea>
        <div class="field-help">Why is this issue important?</div>
      </div>

      <div class="field" >
        <label :for="myId + 'petitionWhat'">What are people agreeing to by signing?</label>
        <textarea
          required
          rows=5
          cols=60
          :id="myId + 'petitionWhat'"
          :disabled="$root.submissionRunning"
          v-model="petitionBeingEdited.what"
          v-if="creatingPetition"
        ></textarea>
        <p class="fixed" title="This can no longer be edited." v-if="editingPetition" >{{petitionBeingEdited.what}}</p>
        <div class="field-help" v-if="creatingPetition">This should be a short and clear statement. It cannot be changed later.</div>
      </div>

      <div class="field" >
        <label :for="myId + 'petitionTargetCount'">Target</label>
        <input
          type="number" required
          :id="myId + 'petitionTargetCount'"
          :disabled="$root.submissionRunning"
          v-model="petitionBeingEdited.targetCount"
        />
        <div class="field-help">How many signatures are you aiming for? This should be realistic; too high and it will put people off signing. Note that the target will auto-extend if you exceed this. You can also edit this manually later if you need to.</div>
      </div>

      <div class="field" >
        <label :for="myId + 'petitionImage'">Upload New Image</label>
        <input
          type="file"
          name="image"
          ref="imageFile"
          @change="mainImageFileCount=$refs.imageFile.files.length > 0"
          :id="myId + 'petitionImage'"
          :disabled="$root.submissionRunning"
          />
        <div class="field-help" >Make sure you upload a <em>landscape</em> image (i.e. wider than it is tall), otherwise important parts of the image might be cropped. Ideally your image should be 16:9 ratio and over 1000px wide.</div>
        <label :for="myId + 'petitionImageAlt'">Alternative text for paritally-sighted and blind people</label>
        <input
          type="text"
          :id="myId + 'petitionImageAlt'"
          :disabled="$root.submissionRunning"
          :required="mainImageFileCount"
          v-model="petitionBeingEdited.imageAlt"
          />
        <div class="field-help" >If you provide an image you are required to provide a short bit of text that describes the content of the image. e.g. "Photo of students dropping banner saying End Fossil Fuels". This way someone who uses screen reader technology won’t be excluded.</div>
      </div>

      <div class="field">
        <button class="secondary" type="submit" @click.prevent="stage='listPetitions';petitionBeingEdited=null;">Cancel</button>
        <button class="primary" type="submit" >{{ editingPetition ? 'Save Petition' : 'Create Petition'}}</button>
      </div>
    </form><!-- /editingPetition -->

    <form
      class="update-petition"
      v-if="stage === 'updatePetition'"
      @submit.prevent="addPetitionUpdate"
    >
      <h2>Provide updates</h2>
      <!-- todo list current updates -->
      <h3>Existing updates</h3>
      <ul>
        <li v-for="update in updates">
          {{update.activity_date_time}}
          {{update.subject}}
          {{update.detils}}
        </li>
      </ul>

    </form>

    <div v-show="stage === 'loadingError'" class="grpet-error" >{{loadingError}}</div>

    <inlay-progress ref="loadingProgress"></inlay-progress>
  </div>
</template>
<style lang="scss">
.grpet-admin {
  box-sizing: border-box;

  .grpet-error {
    color: #a00;
    padding: 1rem;
  }
  label {
    font-weight: bold;
    display: block;
  }
  .edit-petition,
  .grpet-list {
    background-color: #f8f8f8;
    padding: 1rem;
  }
  .edit-petition {
    .field {
      background: white;
      padding: 1rem;
      margin-bottom: 1rem;
    }
    input[type="text"],
    textarea,
    select {
      width: 100%;
    }
    .fixed {
      background: #f0f0f0;
      padding: 0.25rem 1rem;
      color: #555;
      font-size: (14rem/16);
    }
  }
  .status {
    border-radius: 1rem;
    padding: 0 1rem;
    line-height: 1;
    white-space: no-break;
    color: white;
    &.grpet_Won { background: #566a4a; }
    &.grpet_Dead { background: #a4a19e; }
    &.grpet_Pending { background: #747707; }
    &.Open { background: #4aa219; }
  }
  ul.petition {
    margin: 2rem -1rem;
    padding:0;
    display:flex;
    flex-wrap: wrap;
    &>li {
      flex: 1 0 18rem;
      margin: 0 0 2rem;
      padding: 0 1rem;
    }
    article {
      background: white;
      padding: 1rem;
      h1 {
        font-size: 1.4rem;
        line-height: 1;
        margin: 0 0 1rem;
        padding:0;
      }

    }
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
      campaigns: [],

      petitionBeingEdited: {},
      mainImageFileCount: 0,

      petitionBeingUpdated: {},
      updateImageFileCount: 0,
    };
    return d;
  },
  computed: {
    creatingPetition() {
      return this.petitionBeingEdited && !('id' in this.petitionBeingEdited);
    },
    editingPetition() {
      return this.petitionBeingEdited && ('id' in this.petitionBeingEdited);
    },
  },
  mounted() {
    this.bootList();
  },
  methods: {
    getAuthHash() {
      var authHash = (window.location.hash || '#').substr(1);
      if (authHash.match(/^[TS][0-9a-z]{16}$/)) {
        return authHash;
      }
    },
    authorisedRequest(opts) {
      if (this.authToken) {
        // We have already converted the temporary URL token to a session one.
        opts.body.authToken = this.authToken;
      }
      else {
        // App has no session token yet..
        var authHash = this.getAuthHash();
        if (authHash) {
          opts.body.authToken = authHash;
        }
        else {
          console.log("Failed to find suitable means of authenticating.");
          this.stage = 'unauthorised';
          // We have to return a promise.
          return Promise.resolve({ error: 'Unauthorised', responseOk: false, responseStatus: 401 });
        }
      }
      opts.xdebug='foo'; // xxx

      return this.inlay.request(opts).then(r => {
        if (!r.responseOk) {
          console.log("authorisedRequest: did not get responseOk", r);
          throw r;
        }
        if (r.token) {
          // Store updated token, if one sent.
          this.authToken = r.token;
        }
        return r;
      })
      .catch( e => {
        console.warn("InlayGrassrootsPetitionAdmin authorisedRequest caught", e);
        if (e.responseStatus == 401) {
          // Unauthorised. Reset everything.
          this.stage = 'unauthorised';
          this.petitions = [];
          this.authToken = '';
          this.authEmail = '';;
          // Handled.
          return { error: 'Unauthorised', responseOk: false, responseStatus: 401 };
        }
        // Unhandled.
        throw e;
      })
      ;
    },
    bootList() {
      this.stage = 'loading';
      this.loadingMessage = "Loading petitions...";
      this.petitions = [];
      // Send request to load petitions.
      this.authorisedRequest({method: 'post', body: {need: 'adminPetitionsList'}})
        .then(r => {
          if (r.petitions) {
            this.petitions = r.petitions;
            this.campaigns = r.campaigns;
            if (this.petitions.length === 0) {
              // Create new petition, since there are none.
              this.createNewPetition();
            }
            else {
              this.stage = 'listPetitions';
            }
          }
          else {
            console.warn("hmmm", r);
          }
        });
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
    editPetition(petition) {
      if (!petition.id) {
        // New petition, nothing to load.
        this.stage = 'editPetition';
        this.petitionBeingEdited = petition;
        return;
      }
      // Load exsting petition.
      this.stage = 'loading';
      this.loadingMessage = 'Loading petition...';
      const progress = this.$refs.loadingProgress;
      progress.startTimer(5, 100, true);
      this.authorisedRequest({ method: 'post', body: { need: 'adminLoadPetition', petitionID: petition.id}})
      .then(r => {
        progress.cancelTimer();
        if (r.responseOk && r.success == 1 && r.petition) {
          this.petitionBeingEdited = r.petition;
          this.stage = 'editPetition';
        }
        else {
          alert("Sorry, there was an error: " + (r.publicError || 'Unknown error LP1'));
        }
      })
    },
    updatePetition(petition) {
      // Load exsting petition.
      this.stage = 'loading';
      this.loadingMessage = 'Loading petition updates...';
      const progress = this.$refs.loadingProgress;
      progress.startTimer(5, 100, true);
      this.authorisedRequest({ method: 'post', body: { need: 'adminLoadUpdates', petitionID: petition.id}})
      .then(r => {
        progress.cancelTimer();
        if (r.responseOk && r.success == 1 && r.updates) {
          this.updates = r.updates;
          this.stage = 'updatePetition';
        }
        else {
          alert("Sorry, there was an error: " + (r.publicError || 'Unknown error UPL1'));
        }
      })
    },
    createNewPetition() {
      this.stage = 'createNewPetition';
      this.petitionBeingEdited = null;
    },
    createNewPetitionFromType(campaign) {
      console.log('createNewPetitionFromType', campaign);
      const petition = {
        campaignLabel: campaign.label,
        title: campaign.template_title,
        what: campaign.template_what,
        why: campaign.template_why,
        // @todo other defaults
      };
      this.editPetition(petition);
    },
    savePetition() {
      // The browser's checks say the fields are valid.
      // (do any custom stuff in response to the buttonclick)
      const d = {
        need: 'adminSavePetition',
      };
      // Copy our fields.
      ['title', 'targetName', 'who', 'what', 'why', 'targetCount', 'location', 'imageAlt'].forEach(f => {
        d[f] = this.petitionBeingEdited[f];
      });

      var p = new Promise((resolve, reject) => {
        if (this.$refs.imageFile.files.length === 1) {

          var fr = new FileReader();
          fr.addEventListener('load', e => {
            // File loaded.
            d.imageData = fr.result;
            resolve(d);
          });
          fr.readAsDataURL(this.$refs.imageFile.files[0]);
        }
        else {
          resolve(d);
        }
      });

      p.then( d => {

        if (this.editingPetition) {
          // send ID of existing petitions.
          d.id = this.petitionBeingEdited.id;
        }
        else {
          // new petitions need this.
          d.campaignLabel = this.petitionBeingEdited.campaignLabel;
        }
        // Got data.
        const progress = this.$refs.loadingProgress;
        progress.startTimer(5, 100, true);
        this.$root.submissionRunning = true;
        this.authorisedRequest({ method: 'post', body: d })
        .then(r => {
          this.$root.submissionRunning = false;
          progress.cancelTimer();

          // Were there any errors?
          // We're not expecting any, so just use alert.
          if (r.responseOk && r.success == 1) {
            // The result of saving successfully is an updated set of petitions.
            this.petitions = r.petitions;
            this.stage = 'listPetitions';
            this.petitionBeingEdited = null;
          }
          else {
            alert("Sorry, there was an error: " + (r.publicError || 'Unknown error SP1'));
          }
        });
      });
    },
    getStatusMeta(status) {
      return {
        grpet_Pending: { description: 'Waiting on moderation', open: false },
        Open: { description: 'Live', open: true },
        grpet_Won: { description: 'Won!', open: false },
        grpet_Dead: { description: 'Closed', open: false },
      }[status];
    }
  }
}
</script>
