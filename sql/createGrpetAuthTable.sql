CREATE TABLE IF NOT EXISTS civicrm_grpet_auth (
  id CHAR(17) NOT NULL PRIMARY KEY COMMENT 'T(emp) or S(ession) followed by 16 char hash',
  validTo TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Rows are deleted after this time',
  contact_id INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Contact that this token gives access to the petitions of',
  upgradedTo CHAR(17) COMMENT 'the id of another record that has a longer life token',
  KEY k_validTo (validTo)
);
