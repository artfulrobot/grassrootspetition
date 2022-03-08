<?php
return [
  'grpet_public_admin_url' => [
    'name'        => 'grpet_public_admin_url',
    'title'       => ts('Public Admin URL stub'),
    'description' => ts('The URL that lets petition owners access their petitions'),
    'group_name'  => 'domain',
    'type'        => 'String',
    'html_type'   => 'text',
    'default'     => 'https://example.org/petitions-admin#',
    'is_domain'   => 1,
    'is_contact'  => 0,
     'settings_pages' => ['settings' => ['weight' => 1]],
  ],
  'grpet_public_url' => [
    'name'        => 'grpet_public_url',
    'title'       => ts('Public petitions URL stub'),
    'description' => ts('The URL that lets the public see and sign petitions'),
    'group_name'  => 'domain',
    'html_type'   => 'text',
    'type'        => 'String',
    'default'     => 'https://example.org/petitions/',
    'is_domain'   => 1,
    'is_contact'  => 0,
     'settings_pages' => ['settings' => ['weight' => 2]],
  ],
];
