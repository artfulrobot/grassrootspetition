# What data is stored where?

- The Inlay Configuration - which typically you would only have one of - stores global settings. (Theoretically you could maybe use more than one Inlay instance, I suppose, but as routes on the front end site are hard coded, this would only be useful for supporting different front-end sites with different requirements.)

- Campaigns are separate entities, accessible via API4.

- Petitions are Cases, and most data should be accessed/set via `\Civi\GrassrootsPetition\CaseWrapper`.

- A lot of petition data is stored as custom fields on the Case; see `Civi\Inlay\GrassrootsPetition::getCustomFields()`.

- The main petition image is stored as an Attachment on the Grassroots Petition Created activity (which is the Open Case activity for that Case Type), again this can be accessed through CaseWrapper.

