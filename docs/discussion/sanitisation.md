# Security

## Data from public petition form

- first, last names: disallowed characters: `<>` and emojis. Limited length.
  'http' not allowed neither is '//'

- email: validated with PHP's builtin filter.

- phone: everything except + and numbers gets stripped out; it's only
  valid if we have at least 11 characters remaining.

- opt-in: only yes|no strings allowed.

- location: (URL) anything allowed except `<>`

- token: strict validation; exact match.

## Data from public admin forms

- first, last name, email, phone, token: as above.

- caseID (internal petition ID): cast to integer. Case ID is always
  correlated with the contact owner before it's accepted.

- campaignLabel. Must be a valid (matching active campaign) string label.

- why, title, targetName, location (e.g. "Sheffield Uni"), who what: as
  with names.

- imageData. Ignored unless decoding from base64 is successful. Only
  png/jpeg supported. Saved data is not publicly accessible unless the
  petition is published. todo: should delete images when case deleted or
  made un-public so that left over public images are cleaned up.
