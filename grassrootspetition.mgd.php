<?php

/**
 * The record will be automatically inserted, updated, or deleted from the
 * database as appropriate. For more details, see "hook_civicrm_managed" at:
 * https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
 */
return [
  [
    'name' => 'grpet_auth_email',
    'entity' => 'MessageTemplate',
    'params' => [
      'version' => 3,
      "msg_title" => "Grassroots Petition Admin Link",
      "msg_subject" => "Petition administration",
      "msg_html" => "<p>Hi,</p>\r\n\r\n<p>This email was sent because someone (hopefully you!) completed our form for creating or administering petitions. You can safely delete this email if it wasn’t you.</p>\r\n\r\n<p>You can create and administer petitions using the following link, <strong>which is valid for the next hour.</strong></p>\r\n\r\n<p><a href=\"{\$petitionLink}\">{\$petitionLink}</a></p>\r\n\r\n<p>Soldarity,</p>\r\n\r\n<p>{domain.name}</p>",
      "is_active" => "1",
      'is_reserved' => 1,
    ],
  ],
];
