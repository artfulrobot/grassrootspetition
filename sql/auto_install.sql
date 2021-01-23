-- +--------------------------------------------------------------------+
-- | Copyright CiviCRM LLC. All rights reserved.                        |
-- |                                                                    |
-- | This work is published under the GNU AGPLv3 license with some      |
-- | permitted exceptions and without any warranty. For full license    |
-- | and copyright information, see https://civicrm.org/licensing       |
-- +--------------------------------------------------------------------+
--
-- Generated from schema.tpl
-- DO NOT EDIT.  Generated by CRM_Core_CodeGen
--


-- +--------------------------------------------------------------------+
-- | Copyright CiviCRM LLC. All rights reserved.                        |
-- |                                                                    |
-- | This work is published under the GNU AGPLv3 license with some      |
-- | permitted exceptions and without any warranty. For full license    |
-- | and copyright information, see https://civicrm.org/licensing       |
-- +--------------------------------------------------------------------+
--
-- Generated from drop.tpl
-- DO NOT EDIT.  Generated by CRM_Core_CodeGen
--
-- /*******************************************************
-- *
-- * Clean up the exisiting tables
-- *
-- *******************************************************/

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `civicrm_grpet_campaign`;

SET FOREIGN_KEY_CHECKS=1;
-- /*******************************************************
-- *
-- * Create new tables
-- *
-- *******************************************************/

-- /*******************************************************
-- *
-- * civicrm_grpet_campaign
-- *
-- * Each is campaign, used to group signatures from various local campaigns into a total
-- *
-- *******************************************************/
CREATE TABLE `civicrm_grpet_campaign` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Unique GrassrootsPetitionCampaign ID',
     `name` varchar(255)    COMMENT 'Administrative name',
     `label` varchar(255)    COMMENT 'Public name',
     `description` longtext    COMMENT 'Describes the campaign',
     `is_active` tinyint   DEFAULT 1 COMMENT 'Whether to allow these petitions',
     `slug` varchar(255)    COMMENT 'URL path component shared by this campaign\'s petitions',
     `template_what` longtext    COMMENT 'HTML template for the \'What\' of new petitions',
     `template_why` longtext    COMMENT 'HTML template for the \'Why\' of new petitions',
     `template_title` varchar(255)    COMMENT 'HTML template for the title of new petitions' 
,
        PRIMARY KEY (`id`)
 
 
 
)    ;

 