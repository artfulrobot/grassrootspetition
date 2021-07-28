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
  [
    'name' => 'grpet_login_email',
    'entity' => 'MessageTemplate',
    'params' => [
      'version' => 3,
      "msg_title" => "Grassroots Petition Login Link",
      "msg_subject" => "Petition administration",
      "msg_html" => "<p>Hi,</p>\r\n\r\n<p>Thanks for creating a petition. Our staff will get it live on the site for you ASAP.</p><p>Once live, you’ll find your petition at <br/><a href=\"{\$publicLink}\" >{\$publicLink}</a></p><p>To administer your petition (e.g. provide updates, or mark it as Won etc.) use the following link to log-in:<br/><a href=\"{\$loginLink}\" >{\$loginLink}</p>\r\n\r\n<p><a href=\"{\$petitionLink}\">{\$petitionLink}</a></p>\r\n\r\n<p>Soldarity,</p>\r\n\r\n<p>{domain.name}</p>",
      "is_active" => "1",
      'is_reserved' => 1,
    ],
  ],
  [
    'name' => 'grpet_notify_new_petition',
    'entity' => 'MessageTemplate',
    'params' => [
      'version' => 3,
      "msg_title" => "Grassroots Petition New Petition Notification",
      "msg_subject" => "New petition requires moderation",
      "msg_html" => "<p>Hi {contact.first_name},</p>\r\n\r\n<p>A new {\$campaignName} petition on the has been created that needs you to check and publish it.</p>\r\n\r\n<p>Please visit the petitions page at <strong>Cases » Grassroots Petitions</strong> to check for this and any other petitions needing moderation.</p><p>{domain.name}</p>",
      "is_active" => "1",
      'is_reserved' => 1,
    ],
  ],
];
