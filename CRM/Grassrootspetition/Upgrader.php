<?php
use CRM_Grassrootspetition_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Grassrootspetition_Upgrader extends CRM_Extension_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Create the auth table.
   */
  public function install() {
    $this->executeSqlFile('sql/createGrpetAuthTable.sql');
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   */
   public function postInstall() {
    CRM_Core_Transaction::create()->run([$this, 'ensureDataStructuresExist']);
   }
   /**
   */
  public function ensureDataStructuresExist() {

    //
    // Create activity types.
    //
    $activityTypeNamesToValues = [];
    foreach ([
      [
        'name' => 'Grassroots Petition created',
        'description' => '',
        'icon' => 'fa-pencil-square-o',
      ],
      [
        'name' => 'Grassroots Petition progress',
        'description' => 'Petition owner has provided an update on the campaign. This will be shown on the petition page.',
        'icon' => 'fa-pencil-square-o',
      ],
      [
        'name' => 'Grassroots Petition mailing',
        'description' => 'Petition owner has created a mailing to the signees.',
        'icon' => 'fa-pencil-square-o',
      ],
      [
        'name' => 'Grassroots Petition signed',
        'description' => 'Contact has signed a petition',
        'icon' => 'fa-pencil-square-o',
      ],
    ] as $activityType) {
      $activityTypeNamesToValues[$activityType['name']] = Civi\Api4\OptionValue::get()
        ->setCheckPermissions(FALSE)
        ->addWhere('option_group_id:name', '=', 'activity_type')
        ->addWhere('name', '=', $activityType['name'])
        ->execute()
        ->first()['value'] ?? NULL;
      if (empty($activityTypeNamesToValues[$activityType['name']])) {
        // Not found, create it now.
        $activityTypeNamesToValues[$activityType['name']] = \Civi\Api4\OptionValue::create()
          ->setCheckPermissions(FALSE)
          ->addValue('option_group_id:name', 'activity_type')
          ->addValue('label', $activityType['name'])
          ->addValue('name', $activityType['name'])
          ->addValue('description', $activityType['description'])
          ->addValue('is_active', 1)
          // ->addValue('color', $colour)
          // ->addValue('grouping', $tpl['grouping'])
          ->execute()->first()['value'];
      }
    }

    //
    // Create new activity statuses
    //
    foreach ([
      'grpet_pending_moderation' => ['Pending Moderation', 'This update is LIVE but has not been checked by staff yet.']
    ] as $name => $details) {
      // Check if it exists.
      if (Civi\Api4\OptionValue::get()
        ->setCheckPermissions(FALSE)
        ->selectRowCount()
        ->addWhere('option_group_id:name', '=', 'activity_status')
        ->addWhere('name', '=', "$name")
        ->execute()
        ->count() == 0) {
        // Does not exist, create it now.
        \Civi\Api4\OptionValue::create()
          ->setCheckPermissions(FALSE)
          ->addValue('option_group_id:name', 'activity_status')
          ->addValue('label', $details[0])
          ->addValue('description', $details[1])
          ->addValue('name', $name)
          ->addValue('is_active', 1)
          ->execute();
      }
    }

    //
    // Create our case statuses.
    //
    $case_stage_option_group_id = (int) civicrm_api3('OptionGroup', 'getvalue', ['return' => 'id', 'name'=>'case_status']);
    foreach ([
      ['Pending', 'Submitted, waiting for staff to approve before it is live.', '#f7e6a2', 'Opened'],
      // Assume we have Ongoing
      ['Won', 'Petition has succeeded and is no longer open.', '#a3e2b0', 'Closed'],
      ['Dead', 'Petition is no longer relevant. Might have failed, been abandonded or become surplus to requirements.', '#cccccc', 'Closed'],
    ] as $weight => $details) {
      list($name, $description, $colour, $grouping) = $details;
      // Check if it exists.
      if (Civi\Api4\OptionValue::get()
        ->setCheckPermissions(FALSE)
        ->selectRowCount()
        ->addWhere('option_group_id', '=', $case_stage_option_group_id)
        ->addWhere('name', '=', "grpet_$name")
        ->execute()
        ->count() > 0) {

        continue;
      }
      // Create it.
      \Civi\Api4\OptionValue::create()
        ->setCheckPermissions(FALSE)
        ->addValue('option_group_id', $case_stage_option_group_id)
        ->addValue('label', $name)
        ->addValue('name', "grpet_$name")
        ->addValue('description', $description)
        ->addValue('grouping', $grouping)
        ->addValue('color', $colour)
        ->addValue('is_active', 1)
        ->addValue('weight', $weight * 2)
        ->execute();
    }


    //
    // Create case type.
    //
    $baseParams = ['name' => 'grassrootspetition'];
    $timelineActivityTypes = [
      [
        "name" => "Grassroots Petition created",
        "status" => "Completed",
        "label" => "Grassroots Petition created",
        "default_assignee_type" => "1" /* None */
      ]
    ];
    $allParams = [
      "title" => "Grassroots Petition",
      "is_active" => "1",
      "definition" => [
        "activityTypes" => [
          //[ "name" => "Open Case", "max_instances" => "1" ],
          [ "name" => "Grassroots Petition created", "max_instances" => "1" ],
          [ "name" => "Grassroots Petition progress"],
          [ "name" => "Grassroots Petition mailing"],
          [ "name" => "Grassroots Petition signed"],
        ],
        "statuses" => [
          "grpet_Pending",
          "Open",
          "grpet_Won",
          "grpet_Dead"
        ],
        "activitySets" => [
          [
            'name' => 'standard_timeline',
            'label' => 'Standard Timeline',
            'timeline' => 1,
            'activityTypes' => $timelineActivityTypes,
          ]
        ],
        "timelineActivityTypes" => $timelineActivityTypes,
        "caseRoles" => [
          [
            "name" => "Case Coordinator",
            "creator" => "1",
            "manager" => "1"
          ]
        ],
      ]
    ];
    $case_id = $this->createOrUpdate('CaseType', $baseParams, $allParams, FALSE);

    //
    // Create custom field group for the case.
    //
    $baseParams = [
      'name'       => 'grpet_petition',
      'table_name' => 'civicrm_grpet_petition',
    ];
    $allParams = [
      "title"                       => "Petition Details",
      "extends"                     => "Case",
      "extends_entity_column_value" => [ $case_id ],
      "style"                       => "Inline",
      "collapse_display"            => "0",
      "is_active"                   => "1",
      "collapse_adv_display"        => "0",
      "is_reserved"                 => "0",
      "is_public"                   => "0",
    ];
    $customGroupIDPetition = $this->createOrUpdate('CustomGroup', $baseParams, $allParams);

    // Create Target Name
    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => "grpet_target_name",
    ];
    $allParams = [
      'column_name'     => "target_name",
      'label'           => "Target (who)",
      'data_type'       => "String",
      'text_length'     => 255,
      'html_type'       => "Text",
      'is_searchable'   => 1,
      'is_required'     => 1,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    // Create petition slug (url path element)
    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => "grpet_slug",
    ];
    $allParams = [
      'column_name'     => "slug",
      'label'           => "URL part",
      'data_type'       => "String",
      'text_length'     => 255,
      'html_type'       => "Text",
      'is_searchable'   => 1,
      'is_required'     => 1,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    // Create Target Count
    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => "grpet_target_count",
    ];
    $allParams = [
      'column_name'     => "target_count",
      'label'           => "Target (how many signatures)",
      'data_type'       => "Int",
      'html_type'       => "Text",
      'is_required'     => 0,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    // Tweet text.
    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => "grpet_tweet_text",
    ];
    $allParams = [
      'column_name'     => "tweet_text",
      'label'           => "Suggested tweet",
      'data_type'       => "Memo",
      // 'text_length'     => 512,
      'html_type'       => "TextArea",
      'note_rows'       => 3,
      'note_columns'    => 40,
      'is_required'     => 0,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    // Location (typically uni name)
    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => "grpet_location",
    ];
    $allParams = [
      'column_name'     => "location",
      'label'           => "Location (e.g. uni name)",
      'data_type'       => "String",
      'text_length'     => 255,
      'html_type'       => "Text",
      'is_searchable'   => 1,
      'is_required'     => 1,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    // which campaign it belongs to. (FK to a GrassrootsPetitionCampaign entity).
    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => "grpet_campaign",
    ];
    $allParams = [
      'column_name'     => "campaign_id",
      'label'           => "Campaign",
      'data_type'       => "Integer",
      'html_type'       => 'Text',
      'is_searchable'   => 1,
      'is_required'     => 1,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    // Why, What
    // (originally stored as case details, but there's no UI for that field, which begins as a copy of the open case activity)
    // Decided it was simpler to just put it on a custom field
    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => "grpet_who",
    ];
    $allParams = [
      'column_name'     => "who",
      'label'           => "Who is running this petition?",
      'data_type'       => "String",
      'html_type'       => "Text",
      'is_required'     => 1,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => "grpet_why",
    ];
    $allParams = [
      'column_name'     => "why",
      'label'           => "Why is this important? What's the reasoning for this petition?",
      'data_type'       => "Memo",
      'html_type'       => "TextArea",
      'note_rows'       => 3,
      'note_columns'    => 40,
      'is_required'     => 0,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => "grpet_what",
    ];
    $allParams = [
      'column_name'     => "what",
      'label'           => "What are people putting their names to?",
      'data_type'       => "Memo",
      //'html_type'       => "RichTextEditor",
      'html_type'       => "TextArea",
      'note_rows'       => 3,
      'note_columns'    => 40,
      'is_required'     => 0,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    // Download override {{{
    // Create the optiongroup
    $baseParams = [
      'name' => "grpet_download_permissions",
    ];
    $allParams = [
      'title'     => "Grassroots Petition Download Permissions",
      'data_type' => "String",
      'is_active' => 1,
      'is_reserved' => 1,
      'is_locked' => 1, // We do not want people adding to this.
    ];
    $downloadOptionGroupID = $this->createOrUpdate('OptionGroup', $baseParams, $allParams);

    // Create the options.
    foreach ([
      'override' => 'Override defaults',
      'email' => 'Email',
      'name' => 'Name',
    ] as $name => $label) {

      // We need the 'unallocated' option.
      $baseParams = [
        'option_group_id' => $downloadOptionGroupID,
        'name'            => $name,
      ];
      $allParams = [
        'label'           => $label,
        'value'           => $name,
      ];
      $this->createOrUpdate('OptionValue', $baseParams, $allParams);
    }

    // Create the field that references the option group
    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => 'grpet_download_permissions'
    ];
    $allParams = [
      'label'           => "Download permissions",
      'data_type'       => 'String',
      'html_type'       => 'CheckBox',
      'text_length'     => 255,
      'column_name'     => 'download_permissions',
      'option_group_id' => $downloadOptionGroupID,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);
    // }}}

    // List order {{{
    // Create the optiongroup
    $baseParams = [
      'name' => "grpet_list_order",
    ];
    $allParams = [
      'title'     => "Grassroots Petition List Order",
      'data_type' => "String",
      'is_active' => 1,
      'is_reserved' => 1,
      'is_locked' => 1, // We do not want people adding to this.
    ];
    $listOrderOptionGroupID = $this->createOrUpdate('OptionGroup', $baseParams, $allParams);

    // Create the options.
    foreach ([
      'normal' => 'Normal',
      'priority' => 'Priority',
      'unlisted' => 'Unlisted',
    ] as $name => $label) {

      $baseParams = [
        'option_group_id' => $listOrderOptionGroupID,
        'name'            => $name,
      ];
      $allParams = [
        'label'           => $label,
        'value'           => $name,
      ];
      $this->createOrUpdate('OptionValue', $baseParams, $allParams);
    }

    // Create the field that references the option group
    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => 'grpet_list_order'
    ];
    $allParams = [
      'label'           => "List order",
      'data_type'       => 'String',
      'html_type'       => 'Select',
      'text_length'     => 20,
      'column_name'     => 'list_order',
      'option_group_id' => $listOrderOptionGroupID,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);
    // }}}

    // Mailing override {{{
    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => 'grpet_allow_mailings'
    ];
    $allParams = [
      'label'           => "Allow mailings?",
      'help_pre'        => 'Unset this field to use the default policy',
      'data_type'       => 'Boolean',
      'html_type'       => 'Radio',
      'column_name'     => 'allow_mailings'
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);
    // }}}

    $baseParams = [
      'custom_group_id' => $customGroupIDPetition,
      'name'            => "grpet_confirm_msg_template_id",
    ];
    $allParams = [
      'column_name'     => "confirm_msg_template_id",
      'label'           => "Thank you email override (not opted-in)",
      'data_type'       => "Integer",
      'html_type'       => 'Text',
      'is_searchable'   => 0,
      'is_required'     => 0,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    //
    // Create custom field group for the petition signed activity.
    //
    $baseParams = [
      'name'       => 'grpet_signature',
      'table_name' => 'civicrm_grpet_signature',
    ];
    $allParams = [
      "title"                       => "Petition Signer Details",
      "extends"                     => "Activity",
      "extends_entity_column_value" => [ $activityTypeNamesToValues['Grassroots Petition signed'] ],
      "style"                       => "Inline",
      "collapse_display"            => "0",
      "is_active"                   => "1",
      "collapse_adv_display"        => "1",
     // "is_reserved"                 => "1",
      "is_public"                   => "0",
    ];
    $customGroupIDSig = $this->createOrUpdate('CustomGroup', $baseParams, $allParams);

    // Create 'Show public'
    $baseParams = [
      'custom_group_id' => $customGroupIDSig,
      'name'            => "grpet_sig_public",
    ];
    $allParams = [
      'column_name'     => "sig_public",
      'label'           => "Show publicly",
      'data_type'       => "Boolean",
      'html_type'       => "Radio",
      'default_value'   => 0,
      'is_searchable'   => 1,
      'is_required'     => 0,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    // Create 'Opted In'
    $baseParams = [
      'custom_group_id' => $customGroupIDSig,
      'name'            => "grpet_sig_optin",
    ];
    $allParams = [
      'column_name'     => "optin",
      'label'           => "Opted in",
      'data_type'       => "Boolean",
      'html_type'       => "Radio",
      'default_value'   => 0,
      'is_searchable'   => 1,
      'is_required'     => 0,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    // Create 'Shared'
    $baseParams = [
      'custom_group_id' => $customGroupIDSig,
      'name'            => "grpet_sig_shared",
    ];
    $allParams = [
      'column_name'     => "sig_shared",
      'label'           => "Shared?",
      'data_type'       => "String",
      'text_length'     => 128, // e.g. "facebook, twitter"
      'html_type'       => "Text",
      'is_required'     => 0,
      'is_searchable'   => 1,
    ];
    $this->createOrUpdate('CustomField', $baseParams, $allParams);

    // Create header and footers for mailings.
    $baseParams = [
      'name' => "Header for Grassroots Petition updates",
      'component_type' => 'Header',
    ];
    $allParams = [
      'subject' => "Header for Grassroots Petition updates",
      'body_html' => '',
      'body_text' => '',
      'is_active' => 1,
    ];
    $this->createOrUpdate('MailingComponent', $baseParams, $allParams);

    $baseParams = [
      'name' => "Footer for Grassroots Petition updates",
      'component_type' => 'Footer',
    ];
    $allParams = [
      'subject' => "Footer for Grassroots Petition updates",
      'body_html' => '<div style="margin-top:2rem;border-top: solid 1px #aaa; padding: 2rem 1rem; font-size: 14px; color: #666;" >'
        . '<p style="margin:0 0 14px;">You were send this email because you signed the petition and opted-in to updates.<br/>'
        . '<a href="{action.unsubscribeUrl}">Unsubscribe</a></p>'
        . '<p style="margin:0;">This petition site is managed by {domain.address}</p></div>',
      'body_text' =>
          "\r\n\r\n--------------------------------------------------------------------------\r\n"
        . 'You were send this email because you signed the petition and opted-in to updates.'
        . "\r\n{action.unsubscribeUrl}\r\n\r\n"
        . "This petition site is managed by {domain.address}\r\n",
      'is_active' => 1,
    ];
    $this->createOrUpdate('MailingComponent', $baseParams, $allParams);

    // // Require a dir.
    // $dir = Civi::paths()->getPath('[civicrm.files]/grassrootspetition-images');
    // if (!$dir) {
    //   throw new \RuntimeException("Failed to obtain path to civicrm.files");
    // }
    // else {
    //   if (!is_dir($dir)) {
    //     // Create the dir now.
    //     mkdir($dir);
    //   }
    // }

  }
  /**
   * Helper function
   *
   * @param string $entity e.g. Contact
   * @param array $baseParams used to find the entity
   * @param array $allParams used to update details of the entity. Added to $baseParams for new entities.
   * @param bool $update. If entity found, should we still do a create call to update it?
   *
   * @return int ID of thing created.
   */
  protected function createOrUpdate($entity, $baseParams, $allParams, $update=TRUE) {
    $results = civicrm_api3($entity, 'get', $baseParams);

    $logPrefix = "createOrUpdate $entity with " . json_encode($baseParams);

    $params = $allParams;
    $debug_details = '';
    $count = $results['count'];
    if ($count === 1) {
      if (!$update) {
        $this->ctx->log->info("$logPrefix: found entity $result[id], no change");
        return $results['id'];
      }
      $this->ctx->log->info("$logPrefix: found entity $result[id], will call update");
      $params['id'] = $results['id'];
      $debug_details = 'update';
    }
    elseif ($count === 0) {
      $this->ctx->log->info("$logPrefix: entity NOT found, will create");
      // Create.
      $params += $baseParams;
      $debug_details = 'create';
    }
    elseif ($count > 1) {
      throw new Exception("Found multiple $entity entities matching: " . json_encode($baseParams));
    }
    //print "$debug_details entity.create " . json_encode($params, JSON_PRETTY_PRINT) . "\n";
    $id = (int) civicrm_api3($entity, 'create', $params)['id'] ?? NULL;
    if (!$id) {
      throw new \Exception("Failed to $debug_details '$entity' entity with: " . json_encode($params));
    }
    $this->ctx->log->info("$logPrefix: created entity $id");
    return $id;
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   */
  public function uninstall() {
   $this->executeSqlFile('sql/dropGrpetAuthTable.sql');
  }

  /**
   * Example: Run a simple query when a module is enabled.
   */
  // public function enable() {
  //  CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  // }

  /**
   * Example: Run a simple query when a module is disabled.
   */
  // public function disable() {
  //   CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  // }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0001() {
    $this->ctx->log->info('Applying update 0001');
    $this->executeSqlFile('sql/upgrade_0001.sql');
    return TRUE;
  }

  /**
   * Add a couple of fields.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0002() {
    $this->ctx->log->info('Applying update 0002');
    $this->executeSqlFile('sql/upgrade_0002.sql');

    $this->ctx->log->info('Applying update 0002: adding fields');
    CRM_Core_Transaction::create()->run([$this, 'ensureDataStructuresExist']);
    return TRUE;
  }

  /**
   * Add a template_image_alt field.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0003() {
    $this->ctx->log->info('Applying update 0003');
    $this->executeSqlFile('sql/upgrade_0003.sql');
    $this->ctx->log->info('Applying update 0003: added template_image_alt field');
    return TRUE;
  }

  /**
   * Add a template_tweet field.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0004() {
    $this->ctx->log->info('Applying update 0004');
    $this->executeSqlFile('sql/upgrade_0004.sql');
    $this->ctx->log->info('Applying update 0004: added template_tweet field');
    return TRUE;
  }
  /**
   * Add download permissions and mailing
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0005() {
    $this->ctx->log->info('Applying update 0005');
    $this->executeSqlFile('sql/upgrade_0005.sql');
    $this->ctx->log->info('Applied update 0005');
    return TRUE;
  }

  /**
   * Add download permissions and mailing
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0006() {
    $this->ctx->log->info('Applying update 0006');
    $this->ensureDataStructuresExist();
    return TRUE;
  }

  /**
   * Add download permissions and mailing
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0007() {
    $this->ctx->log->info('Applying ' . __METHOD__);
    $this->ensureDataStructuresExist();
    return TRUE;
  }

  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4201() {
  //   $this->ctx->log->info('Applying update 4201');
  //   // this path is relative to the extension base dir
  //   $this->executeSqlFile('sql/upgrade_4201.sql');
  //   return TRUE;
  // }


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4202() {
  //   $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

  //   $this->addTask(E::ts('Process first step'), 'processPart1', $arg1, $arg2);
  //   $this->addTask(E::ts('Process second step'), 'processPart2', $arg3, $arg4);
  //   $this->addTask(E::ts('Process second step'), 'processPart3', $arg5);
  //   return TRUE;
  // }
  // public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  // public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  // public function processPart3($arg5) { sleep(10); return TRUE; }

  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4203() {
  //   $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

  //   $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
  //   $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
  //   for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
  //     $endId = $startId + self::BATCH_SIZE - 1;
  //     $title = E::ts('Upgrade Batch (%1 => %2)', array(
  //       1 => $startId,
  //       2 => $endId,
  //     ));
  //     $sql = '
  //       UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
  //       WHERE id BETWEEN %1 and %2
  //     ';
  //     $params = array(
  //       1 => array($startId, 'Integer'),
  //       2 => array($endId, 'Integer'),
  //     );
  //     $this->addTask($title, 'executeSql', $sql, $params);
  //   }
  //   return TRUE;
  // }

}

