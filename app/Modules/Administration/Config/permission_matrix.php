<?php

/**
 * Admin role permission matrix (sanctum guard) and optional tenant role seeder metadata.
 */
return [
    'default_actions' => ['view', 'create', 'edit', 'delete'],

    'excluded_entities' => [],

    'tenant_role_seeder' => [
        'reference_role_names' => ['administrator'],
        'exclusions' => [],
    ],
];
