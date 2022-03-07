ALTER TABLE civicrm_grpet_campaign
  ADD `download_permissions` varchar(128)    COMMENT 'What data can be downloaded by petition owners by default JSON array or empty to mean global defaults.',
  ADD `allow_mailings` varchar(8)   DEFAULT 'default' COMMENT 'Whether to allow mailings by default. Valid values are default/yes/no' 
  ;

