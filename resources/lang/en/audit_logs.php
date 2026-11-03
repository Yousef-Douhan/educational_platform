<?php

return [
    'title' => 'Audit log',
    'subtitle' => 'A permanent record of who did what, when and from where. Entries can never be edited or deleted from the system, and passwords, tokens and payment details are never stored in it.',
    'breadcrumb' => ['home' => 'Home', 'group' => 'Security'],
    'actions' => ['view' => 'Details', 'clear' => 'Clear', 'reset' => 'Reset filters'],
    'stats' => ['total' => 'All entries', 'today' => 'Today', 'week' => 'Last 7 days', 'deletions' => 'Deletions', 'failed_logins' => 'Failed sign-ins'],
    'filters' => [
        'search_placeholder' => 'Search by user, subject, action or IP...', 'all_types' => 'All areas', 'all_events' => 'All actions',
        'from' => 'From', 'to' => 'To',
    ],
    'types' => [
        'user' => 'User', 'user_profile' => 'User profile', 'role' => 'Role', 'permission' => 'Permission',
        'category' => 'Category', 'course' => 'Course', 'course_section' => 'Course section', 'lesson' => 'Lesson',
        'enrollment' => 'Enrollment', 'quiz' => 'Quiz', 'certificate' => 'Certificate',
        'certificate_template' => 'Certificate template', 'coupon' => 'Coupon', 'order' => 'Order',
        'payment' => 'Payment', 'live_session' => 'Live session', 'quiz_attempt' => 'Quiz attempt', 'auth' => 'Sign-in',
    ],
    'events' => [
        'created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted', 'force_deleted' => 'Deleted permanently',
        'restored' => 'Restored', 'login' => 'Signed in', 'logout' => 'Signed out', 'login_failed' => 'Failed sign-in',
    ],
    'table' => ['when' => 'When', 'user' => 'User', 'action' => 'Action', 'subject' => 'Subject', 'ip' => 'IP address', 'actions' => 'Details', 'system' => 'Guest / system'],
    'fields' => ['email' => 'Email', 'subject_id' => 'Record number', 'user_agent' => 'Browser', 'description' => 'Notes'],
    'modal' => ['show_title' => 'Entry details', 'show_desc' => 'Exactly what happened, and what changed.', 'summary_section' => 'Summary', 'changes_section' => 'Changes'],
    'ui' => ['close' => 'Close'],
    'empty' => [
        'title' => 'No entries yet', 'text' => 'Actions taken in the system will be recorded here.',
        'no_results_title' => 'No matching results', 'no_results_text' => 'Try changing the search or filters.',
    ],
    'js' => [
        'errors' => [
            'network' => 'Network error. Check your connection.',
            'forbidden' => 'You do not have permission for this action.',
            'not_found' => 'Record not found.',
            'conflict' => 'There is a conflict with the current data.',
            'validation' => 'Please fix the highlighted fields.',
            'session_expired' => 'Your session expired. Reload the page.',
            'server' => 'Server error. Try again later.',
            'unknown' => 'An unexpected error occurred.',
        ],
        'no_changes' => 'No field values were recorded for this entry.',
        'field' => 'Field', 'old_value' => 'Before', 'new_value' => 'After',
        'redacted' => 'Hidden (sensitive)', 'system' => 'Guest / system',
    ],
];
