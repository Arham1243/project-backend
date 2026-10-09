<?php

return [
    'full_crud_entities' => [
        'administration.user-roles',
        'administration.users',
    ],

    'entity_actions' => [
        'administration.email-templates' => ['view', 'edit'],
        'administration.company' => ['view', 'edit'],
    ],

    'disabled_matrix_actions' => [
        'administration.email-templates' => ['create', 'delete'],
        'administration.company' => ['create', 'delete'],
    ],

    'matrix_sections' => [
        [
            'title' => 'Administration',
            'entities' => [
                'administration.company',
                'administration.users',
                'administration.user-roles',
                'administration.email-templates',
            ],
            'child_indent' => true,
            'order' => 40,
        ],
    ],
];
