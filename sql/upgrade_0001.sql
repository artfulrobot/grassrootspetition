ALTER TABLE `civicrm_grpet_campaign`
ADD `notify_contact_id` int unsigned COMMENT 'FK to Contact to notify about new petitions',
ADD `notify_email` varchar(255)      COMMENT 'Email address to notify about new petitions',
ADD CONSTRAINT FK_civicrm_grpet_campaign_notify_contact_id FOREIGN KEY (`notify_contact_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE CASCADE
;
