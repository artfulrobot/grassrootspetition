# How to install

1. Ensure you have CiviCase available.
1. Install and configure the [Inlay extension](https://lab.civicrm.org/extensions/inlay).
1. Install and configure the [Extended Contact Matcher (XCM)](https://github.com/systopia/de.systopia.xcm/) extension.
1. Install this Grassroots Petition Inlay extension.
1. Create a Grassroots Petition Inlay
1. Configure your initial Campaigns
1. Install on the destination site.


## Installing extensions

This is done in the normal ways: download and unzip a release archive, or clone
the git repo, into your extensions directory, then enable the extension(s) from
**CiviCRM's Administer » System Settings » Extensions** screen.

You'll need to configure XCM so that it can effectively match existing contacts.

You'll need to configure Inlay (and possibly your webserver) to allow your
external website as a CORS origin.

## Create a welcome/thank you message template

Assuming you want to send these, create a Message Template for it.


## Create a Grassroots Petition Inlay

Once you have these installed you should find you have a menu entry
**Administer » Inlays**. From that screen you can add a new **Grassroots
Petition**. You only need one of these for all your petitions.

Initial notes on the fields:

- These fields will apply to *all* your petitions across all your campaigns.

- Name: e.g. "Petitions" - it's not going to matter much, it's just what shows up in the list of Inlays.

- Various texts: either put something in that makes sense to you, or leave it for now - you can always edit this later.

- Social Media options. Choose which to use and in which order.

- Thank you email - this is sent to people who consent to further
  communications. It offers you a list of message templates to choose from. You
  can create these in the normal way. Remember it is not campaign/petition specific.

Once you have saved, you should see the list of inlays again, including your
new one. Click **Get Code** and copy the `<script>` tag. You'll need this later
(see below).


## Configure your first campaign.

A campaign has nothing to do with *CiviCampaign campaigns*. The concept is that
an organisation might have a national/international campaign for X and within
it, activists may set up *local* campaigns too. e.g. a campaign for ethical,
respectful treatment of stories to do with people who have migrated might have
a national petition, but there may be local ones targeted at local media. If you
don't run campaigns like this you could just use something generic sounding.

Go to **Cases » Grassroots Petitions** and click **Create new campaign**.

Most fields should be self-explanatory, for the others there's the following notes:

- Active: active campaigns' petitions are listed. Inactive ones are not.

- template fields (title, why, what...) this is content that is used when
  campaigners set up a new petition for this campaign. They are free to change
  any of it, it's just the defaults.

- The "template why" is background text to convince someone about the merits of
  the campaign, why they should sign. It can be updated at any time by the
  campaigner who owns the petition.

- The "template what" text cannot be changed by the campaigner once set,
  because it determines what participants are signing. (Staff with access to
  CiviCRM can change it, should they need to make corrections, but utmost care
  must be taken not to deviate from what people signed up to.)

- The template texts use [CommonMark](https://commonmark.org/help/) so you can
  add **strong emphasis**, *emphasis* and [links](https://artfulrobot.uk "Links
  to Artful Robot")


## Install on the external website

This is more involved than most inlays. In your CMS, you need to provide the following routes:

- `/petitions/`
- `/petitions/*` Here the `*` means anything. It will be the campaign+petition url.
- `/petitions-admin/`

On each of these routes you need to put the `<script>` tag for the inlay (you
copied it earlier from **Administer » Inlays » Get Code**).

## That's it.

Imagining your website is at example.org, visiting
`https://example.org/petitions/` should show see a list of petitions. Except
there won't be any yet. You should have an option to create a petition.

See [petition lifecycle tutorial](../tutorial/lifecycle.md)
