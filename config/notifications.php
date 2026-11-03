<?php

// Notification categories a user can choose channels for in Settings →
// Notifications. Each one can arrive in the app (bell and live update), as
// a push notification, or by email to the user's login address. Defaults
// match how each notification was sent before it was configurable.
return [

    'categories' => [

        'new_mail' => [
            'group' => 'Mail',
            'label' => 'New mail',
            'description' => 'A message arrives in one of your mailboxes.',
            'defaults' => ['in_app' => true, 'push' => true, 'email' => false],
        ],

        'job_applications_ready' => [
            'group' => 'Job search',
            'label' => 'Applications to review',
            'description' => 'Jobs matched your CV and drafts are waiting for your approval.',
            'defaults' => ['in_app' => true, 'push' => true, 'email' => true],
        ],

        'jobs_to_apply_manually' => [
            'group' => 'Job search',
            'label' => 'Jobs to apply for yourself',
            'description' => 'Good matches that take applications on the employer\'s own site.',
            'defaults' => ['in_app' => true, 'push' => true, 'email' => true],
        ],

        'job_application_sent' => [
            'group' => 'Job search',
            'label' => 'Application sent',
            'description' => 'An application went out from your mailbox.',
            'defaults' => ['in_app' => true, 'push' => true, 'email' => true],
        ],

        'job_application_failed' => [
            'group' => 'Job search',
            'label' => 'Application bounced',
            'description' => 'An application couldn\'t be delivered.',
            'defaults' => ['in_app' => true, 'push' => true, 'email' => true],
        ],

        'job_application_replied' => [
            'group' => 'Job search',
            'label' => 'Employer replied',
            'description' => 'The first reply to one of your applications.',
            'defaults' => ['in_app' => false, 'push' => false, 'email' => true],
        ],

        'job_search_paused' => [
            'group' => 'Job search',
            'label' => 'Job search paused',
            'description' => 'Job search switched itself off, for example after repeated bounces.',
            'defaults' => ['in_app' => true, 'push' => true, 'email' => true],
        ],

    ],

];
