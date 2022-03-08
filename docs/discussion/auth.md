# Authentication

Who do we let create petitions? How do we know it's them?

Aims:

- Easy to sign up / get in to administer things

- Lightweight authentication; don’t want to require people have user accounts on the CMS.

- Spam free

- Gather data from new people.

## Implementation

People will enter details which will mean they get an email with a special
short-lived link that authorises them to use the petition system. Anyone on the
CRM can get a link like this and start making petitions. Petitions aren’t
public until they have been moderated.

To make it easier for people once they have a petition, we provide a link that
allows them to get a new link by just providing their email (along with the
petition ID which is in the link).

- The signup form has delayed antispam.

- The petition-specific auth form does not need spam as we require the email and the petition ID to match, so it’s no use to a spammer putting in random emails. If the email does not match up with the petition the user is none the wiser and nothing is emailed.

- The email sent does not contain anything user-entered, except for the email address given. (So no *"Dear buy these pills at http://spam.example.com"* attacks.)

- The links provided in emails last for 1 hour. When they are used they obtain a 24 hour token in the background. This means you can use the link with 1 minute to go and still be able to edit your petition all day without losing auth; but that the link is only useful for 1 hour.

Once you're authenticated, we assume it's safe to email you what could
include personal data, and that you're not a spammer.

The data is stored in the `civicrm_grpet_auth` table which maps a hash to a contactID with an expiry datetime.

## Spam risk?

A spammer could steal a session token, then make post requests with that.
We should put some flood control on that.
