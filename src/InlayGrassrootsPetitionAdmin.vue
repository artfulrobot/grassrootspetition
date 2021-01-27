<template>
  <div class="grpet-admin">
    <div v-show="stage === 'loading'" >{{loadingMessage}}</div>

    <div v-show="stage === 'unauthorised'" class="unauthorised" >
      <form v-if="isPetitionSpecificSignin()"
          @submit.prevent="submitAuthEmailPetition"
      >
        <h2>Administer your petition</h2>

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
      </form>

      <form v-if="!isPetitionSpecificSignin()"
          @submit.prevent="submitAuthEmail"
      >
        <h2>Sign up to administer petitions</h2>

        <p class="grpet-error " v-show="getAuthHash()" >This link has expired</p>

        <div class="two-cols">
          <div class="col">
            <div class="field">
              <label :for="myId + 'authFirstName'" >First name</label>
              <input
                type="text"
                :id="myId + 'authFirstName'"
                :disabled="$root.submissionRunning"
                v-model="authFirstName"
                required
                />
            </div>
          </div>

          <div class="col">
            <div class="field">
              <label :for="myId + 'authLastName'" >last name</label>
              <input
                type="text"
                :id="myId + 'authLastName'"
                :disabled="$root.submissionRunning"
                v-model="authLastName"
                required
                />
            </div>
          </div>
        </div>

        <div class="two-cols">
          <div class="col">
            <div class="field">
              <label :for="myId + 'authEmail'" >Enter your email</label>
              <input
                type="email"
                :id="myId + 'authEmail'"
                :disabled="$root.submissionRunning"
                v-model="authEmail"
                required
                />
            </div>
          </div>

          <div class="col">
            <div class="field">
              <label :for="myId + 'authEmail2'" >Enter your email again (helps prevent typos)</label>
              <input
                type="email"
                ref="authEmail2"
                :id="myId + 'authEmail2'"
                :disabled="$root.submissionRunning"
                v-model="authEmail2"
                required
                />
            </div>
          </div>
        </div>

        <button
          class="primary"
          type="submit"
          :disabled="$root.submissionRunning"
          @click="validateSignup"
          >Send registration email</button>
      </form>
    </div>

    <div v-show="stage === 'authSent'" >
      <h2>Check your inbox</h2>
      <p>Thanks, check your inbox for an email from us which contains a link to let you in.</p>
      <p>(If you can't find it, check your spam/junk folder! And if you find it in there, be sure to click the Not Spam button so it doesn't happen with other emails from us.)</p>
    </div>

    <div v-if="stage === 'listPetitions'" class="grpet-list" >
      <h2>Your petitions</h2>
      <ul class="petition">
        <li v-for="petition in petitions" :key="petition.id">
          <article>
            <div class="image"><img :src="petition.imageUrl" :alt="petition.imageAlt" /></div>
            <div class="text">
              <h1><a :href="'/petitions/' + petition.slug" target="_blank" rel="noopener" >{{ petition.title }}</a></h1>
              <span class="status" :class="petition.status" >{{getStatusMeta(petition.status).description }}</span>
              <p>Signatures: {{petition.signatureCount}} / {{petition.targetCount}}.</p>
              <ul>
                <li><a href @click.prevent="editPetition(petition)" >Edit petition (texts, targets etc.)</a></li>
                <li><a href @click.prevent="updatePetition(petition)" >Provide updates, mark Won or Closed</a></li>
                <!-- Unimplemented <li><a href @click.prevent="createEmail(petition)" >Email signers</a></li> -->
              </ul>
            </div>
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
      <ul>
        <li v-for="campaign in campaigns" >
          <div class="grpet-card">
            <h3>{{campaign.label}}</h3>
            <div class="description">{{campaign.description}}</div>
            <div class="button"><button class="primary" @click.prevent="createNewPetitionFromType(campaign)" >Create local petition for this campaign</button></div>
          </div>
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
          @change="mainImageFileCount=($refs.imageFile.files.length > 0) ? true : null"
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
        &nbsp;
        <button class="primary" type="submit" >{{ editingPetition ? 'Save Petition' : 'Create Petition'}}</button>
      </div>
    </form><!-- /editingPetition -->

    <div
      class="update-petition"
      v-if="stage === 'updatePetition'"
    >
      <form @submit.prevent="addPetitionUpdate" >
        <h2>Provide updates</h2>
        <p><a href @click.prevent="stage = 'listPetitions';petitionBeingUpdated = null;" >Back to list</a></p>
        <p>Provide updates that will be shown on the petition page for your petition: <em>{{petitionBeingUpdated.title}}</em></p>
        <div class="field" >
          <label :for="myId + 'updateStatus'">Change Status</label>
          <select
            required
            v-if="petitionBeingUpdated.status != 'grpet_Pending'"
            :id="myId + 'updateStatus'"
            :disabled="$root.submissionRunning"
            v-model="petitionBeingUpdated.status"
            >
            <option value="Open" >Live, accepting signatures</option>
            <option value="grpet_Won" >Won! Not accepting new signatures</option>
            <option value="grpet_Dead" >Closed. Not accepting new signatures</option>
          </select>
          <div class="fixed" v-if="petitionBeingUpdated.status == 'grpet_Pending'" >
            This petition is not live yet; it’s waiting moderation.
          </div>
        </div>

        <div class="field" >
          <label :for="myId + 'updateText'">What’s happened?</label>
          <textarea
            required
            rows=5
            cols=60
            :id="myId + 'updateText'"
            :disabled="$root.submissionRunning"
            v-model="petitionBeingUpdated.text"
            ></textarea>
        </div>

        <div class="field" >
          <label :for="myId + 'updatePetitionImage'">Image (optional)</label>
          <input
            type="file"
            name="imageUpdate"
            ref="imageFileUpdate"
            @change="updateImageFileCount=$refs.imageFileUpdate.files.length > 0"
            :id="myId + 'updatePetitionImage'"
            :disabled="$root.submissionRunning"
            />
          <div class="field-help" >Make sure you upload a <em>landscape</em> image (i.e. wider than it is tall), otherwise important parts of the image might be cropped. Ideally your image should be 16:9 ratio and over 1000px wide.</div>

          <label :for="myId + 'petitionImageAlt'">Alternative text for paritally-sighted and blind people</label>
          <input
            type="text"
            :id="myId + 'petitionImageAlt'"
            :disabled="$root.submissionRunning"
            :required="updateImageFileCount ? true : null"
            v-model="petitionBeingUpdated.imageAlt"
            />
          <div class="field-help" >If you provide an image you are required to provide a short bit of text that describes the content of the image. e.g. "Photo of students dropping banner saying End Fossil Fuels". This way someone who uses screen reader technology won’t be excluded.</div>
        </div>

        <div class="field">
          <button class="secondary" type="submit" @click.prevent="stage='listPetitions';petitionBeingUpdated=null;">Cancel</button>
          &nbsp;
          <button class="primary" type="submit" >Publish Update</button>
        </div>
      </form><!-- /addPetitionUpdate form -->

      <template v-if="updates.length>0">
        <h3>Existing updates</h3>
        <ul class="grpet-updates">
          <li v-for="update in updates">
            <div class="image">
              <img v-if="update.imageUrl" :src="update.imageUrl" :alt="update.imageAlt" />
            </div>
            <div class="details">
              <p><strong>{{update.subject}}</strong></p>
              <p class="smallprint">{{update.activity_date_time}}</p>
              {{update.detils}}
            </div>
            <!-- @todo style this, provide Delete link -->
          </li>
        </ul>
      </template>

    </div><!-- /addPetitionUpdate -->

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

  .two-cols {
    margin-left: -1rem;
    margin-right: -1rem;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    &>.col {
      flex: 1 0 18rem;
      padding-left: 1rem;
      padding-right: 1rem;
    }
  }

  // Create new campaign
  .new-petition {
    ul {
      background-color: #f8f8f8;
      margin:0 0 2rem;
      padding: 0.5rem;
      display: flex;
      flex-wrap: wrap;
    }
    li {
      list-style: none;
      flex: 1 0 18rem;
      padding: 0.5rem;
    }
    div.grpet-card {
      height: 100%;
      background: white;
      padding: 1rem;
      display: flex;
      flex-direction: column;
      h3 { flex: 0 0 auto; }
      .description { flex: 1 0 auto; }
      .button { flex: 0 0 auto; }
    }
    h3 {
      margin: 0 0 1rem;
      font-size: 1.5rem;
      line-height: 1.2;
    }
  }

  label {
    font-weight: bold;
    display: block;
  }
  // Field is a container for a label and input. It may contain multiple elements vertically.
  .field {
    background: white;
    padding: 1rem;
    margin-bottom: 1rem;
  }
  .field-help {
    font-size: (14rem/16);
    margin-bottom: 1rem;
  }

  input[type="text"],
  textarea,
  input[type="email"],
  select {
    width: 100%;
  }

  .update-petition,
  .edit-petition,
  .grpet-list {
    background-color: #f8f8f8;
    padding: 1rem;
  }
  .edit-petition {
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
  // the petitions list.
  ul.petition {
    margin: 0;
    padding:0;

    &>li {
      margin: 0 0 2rem;
      padding: 0;
    }

    article {
      background: white;
      padding: 1rem;
      display: flex;
      .image {
        flex: 1 0 16rem;
      }
      .text {
        flex: 4 0 18rem;
        padding-left: 2rem;
      }
    }

    h1 {
      font-size: 1.4rem;
      line-height: 1;
      margin: 0 0 1rem;
      padding:0;
    }

    img {
      width: 100%;
      height: auto;
      display: block;
    }
  }
  .unauthorised {
    padding: 1rem;
    background: #f8f8f8;
  }
  ul.grpet-updates {
    margin:0;
    padding:0;
    li {
      list-style: none;
      margin:0;
      padding:0;
      display: flex;
      flex-wrap: wrap;
      .image {
        flex: 0 0 8rem;
        img {
          width: 100%;
          height: auto;
          display: block;
        }
      }
      .details {
        flex: 1 0 20rem;
        padding-left:1rem;
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
      authEmail2: '',
      authFirstName: '',
      authLastName: '',
      authPhone: '',
      authToken: '',

      petitions: [],
      campaigns: [],

      petitionBeingEdited: {},
      mainImageFileCount: null,

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
    validateSignup() {
      if (this.authEmail != this.authEmail2) {
        this.$refs.authEmail2.setCustomValidity('Emails do not match.');
      }
      else {
        this.$refs.authEmail2.setCustomValidity('');
      }
    },
    isPetitionSpecificSignin() {
      return this.parseHash().petition !== null;
    },
    getAuthHash() {
      return this.parseHash().auth;
    },
    parseHash() {
      var authHash = (window.location.hash || '#').substr(1);
      var m = authHash.match(/^P([0-9]{1,10})(?:-([TS][0-9a-z]{16}))?$/);
      if (m) {
        return { petition: m[1], auth: m[2] || null };
      }
      // Not petition specific.
      var m = authHash.match(/^[TS][0-9a-z]{16}$/);
      if (m) {
        return { petition: null, auth: authHash };
      }
      return { petition: null, auth: null };
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
        alert( e.publicError || e.error || 'Undocumented error. Oh no!');
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
    submitAuthEmailPetition() {
      // Send petition-specific request for auth email.
      const progress = this.$refs.loadingProgress;

      progress.startTimer(5, 100, true);
      this.$root.submissionRunning = true;
      this.inlay.request({method: 'post', xdebug:'foo', body: {
        need: 'adminAuthEmail',
        email: this.authEmail,
        petitionID: this.parseHash().petition
      }})
        .then(r => {
          if (r.publicError) {
            alert(r.publicError);
          }
          else {
            this.stage = 'authSent';
          }
        })
        .finally( () => {
          this.$root.submissionRunning = false;
          progress.cancelTimer();
        });
    },
    submitAuthEmail() {
      // Send request for auth email.
      const progress = this.$refs.loadingProgress;

      progress.startTimer(5, 100, true);
      this.$root.submissionRunning = true;
      var d = {
        need: 'adminAuthEmail',
        email: this.authEmail,
        first_name: this.authFirstName,
        last_name: this.authLastName,
      };
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
          // Real submission.
          progress.startTimer(2, 100);
          return this.inlay.request({method: 'post', body: d});
        })
        .then(r => {
          if (r.publicError) {
            alert(r.publicError);
          }
          else {
            this.stage = 'authSent';
          }
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
          this.petitionBeingUpdated = {
            status: petition.status,
            title: petition.title,
            id: petition.id,
            text: '',
            imageAlt: '',
          };
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
    addPetitionUpdate() {
      // The browser's checks say the fields are valid.
      // (do any custom stuff in response to the buttonclick)
      const d = {
        need: 'adminAddUpdate',
        petitionID: this.petitionBeingUpdated.id,
        status: this.petitionBeingUpdated.status,
        text: this.petitionBeingUpdated.text,
      };

      // Image?
      var p = new Promise((resolve, reject) => {
        if (this.$refs.imageFileUpdate.files.length === 1) {

          var fr = new FileReader();
          fr.addEventListener('load', e => {
            // File loaded.
            d.imageData = fr.result;
            resolve(d);
          });
          fr.readAsDataURL(this.$refs.imageFileUpdate.files[0]);
        }
        else {
          resolve(d);
        }
      });

      const progress = this.$refs.loadingProgress;

      p.then( d => {
        // Got data.
        progress.startTimer(5, 100, true);
        this.$root.submissionRunning = true;
        return this.authorisedRequest({ method: 'post', body: d });
      })
      .then(r => {
        // Were there any errors?
        // We're not expecting any, so just use alert.
        if (r.responseOk && r.success == 1) {
          this.stage = 'listPetitions';
          this.petitionBeingUpdated = null;
        }
        else {
          alert("Sorry, there was an error: " + (r.publicError || 'Unknown error SU1'));
        }
      })
      .finally( () => {
        this.$root.submissionRunning = false;
        progress.cancelTimer();
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
