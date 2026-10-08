# grassrootspetition

This CiviCRM extension uses Inlay to provide websites with a way to provide a platform for the public to create petitions on issues they care about. Petitions are moderated by staff before they become public. Petition owners can send mailings (moderated), download petition signatures, provide updates on their campaigns for the website etc. It can replace very expensive subscription products.

You can see examples at https://peopleandplanet.org/petitions

Use of this extension requires a bit more work on the website end than most inlays in order to do things like providing page SEO/SM-satisfying titles + meta tags like og:image.

Due to this and the fact that not that many orgs will need this functionality, I have not put a lot of work into making it easy to install. **Therefore if you want to implement this for your campaigns, you'd be advised to contact me at [Artful Robot](https://artfulrobot.uk/).**

## Versions

### 1.2

Public petition list changes: petitions now have a "list order" field which can be set from the manage case screen to one of: Normal|Priority|Unlisted. Public petitions are now listed: active campaigns, Priority petitions, number of signatures. Previously it was just number of signatures.

### 1.1

Brings ability for petition owners to (draft) mailings to their supporters
(requires SearchKit, Civi 5.47+), and also to download signatures. Both of
these require permissions that can be set globally, at a campaign level, or at
a petition level.
