ALTER TABLE `civicrm_grpet_campaign`
  ADD `thanks_msg_template_id`  int unsigned COMMENT 'FK to MessageTemplate for opted-in signers of this campaign',
  ADD `confirm_msg_template_id` int unsigned COMMENT 'FK to MessageTemplate for not-opted-in signers of this campaign',
  ADD CONSTRAINT FK_civicrm_grpet_campaign_thanks_msg_template_id  FOREIGN KEY (`thanks_msg_template_id`)  REFERENCES `civicrm_msg_template`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT FK_civicrm_grpet_campaign_confirm_msg_template_id FOREIGN KEY (`confirm_msg_template_id`) REFERENCES `civicrm_msg_template`(`id`) ON DELETE SET NULL
  ;
