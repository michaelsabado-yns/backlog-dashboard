<?php

return [
    'url' => env('BACKLOG_URL'),
    'api_key' => env('BACKLOG_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Person in charge custom field ID
    |--------------------------------------------------------------------------
    |
    | Backlog custom field ID for "person in charge" (担当者).
    | Field IDs differ per project; leave empty to auto-detect by name in each project.
    |
    */
    'person_in_charge_custom_field_id' => env('BACKLOG_PERSON_IN_CHARGE_CUSTOM_FIELD_ID'),

    /*
    |--------------------------------------------------------------------------
    | Sub-assignee custom field IDs
    |--------------------------------------------------------------------------
    |
    | Comma-separated Backlog custom field IDs for "sub person in charge".
    | When empty, fields are auto-detected by name (e.g. サブ担当).
    |
    */
    'sub_assignee_custom_field_ids' => env('BACKLOG_SUB_ASSIGNEE_CUSTOM_FIELD_IDS'),
];
