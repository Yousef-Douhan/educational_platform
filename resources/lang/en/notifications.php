<?php

return [
    'title' => 'Notifications',
    'subtitle' => 'Every notification the platform sent to users (read only). Read ones are removed after 90 days and unread ones after a year.',
    'breadcrumb' => ['home' => 'Home', 'group' => 'System'],
    'actions' => ['view' => 'Details', 'clear' => 'Clear', 'reset' => 'Reset filters'],
    'stats' => ['total' => 'All notifications', 'unread' => 'Unread', 'read' => 'Read', 'today' => 'Today'],
    'filters' => [
        'search_placeholder' => 'Search by recipient name, email or notification type...',
        'all_kinds' => 'All types', 'all_states' => 'Read and unread', 'unread' => 'Unread', 'read' => 'Read',
        'from' => 'From', 'to' => 'To',
    ],
    'states' => ['read' => 'Read', 'unread' => 'Unread'],
    'table' => ['when' => 'When', 'recipient' => 'Recipient', 'notification' => 'Notification', 'state' => 'Status', 'actions' => 'Details'],
    'fields' => ['kind' => 'Type', 'email' => 'Email', 'read_at' => 'Read at', 'params' => 'Parameters'],
    'modal' => ['show_title' => 'Notification details', 'show_desc' => 'As the recipient sees it.', 'summary_section' => 'Summary', 'params_section' => 'Stored data'],
    'ui' => ['close' => 'Close', 'deleted_user' => 'Deleted user'],
    'empty' => [
        'title' => 'No notifications yet', 'text' => 'They will appear here when events such as payments or enrollments happen.',
        'no_results_title' => 'No matching results', 'no_results_text' => 'Try changing the search or filters.',
    ],
    'unknown' => ['title' => 'Notification', 'body' => 'This notification type is no longer supported.'],

    'kinds' => [
        'order' => [
            'paid' => ['name' => 'Payment confirmed', 'title' => 'Payment confirmed', 'body' => 'Payment for order :order_number (:money) was confirmed.'],
            'refunded' => ['name' => 'Order refunded', 'title' => 'Your order was refunded', 'body' => 'Order :order_number (:money) was refunded and access to its courses was removed.'],
        ],
        'enrollment' => [
            'activated' => ['name' => 'Enrollment activated', 'title' => 'You were enrolled in a course', 'body' => 'You can now access the course ":course_title".'],
        ],
        'certificate' => [
            'issued' => ['name' => 'Certificate issued', 'title' => 'Your certificate was issued', 'body' => 'The completion certificate for ":course_title" was issued with verification code :code.'],
            'revoked' => ['name' => 'Certificate revoked', 'title' => 'A certificate was revoked', 'body' => 'The certificate for ":course_title" was revoked and no longer verifies.'],
        ],
        'live_session' => [
            'cancelled' => [
                'name' => 'Session cancelled', 'title' => 'A live session was cancelled',
                'body' => 'The session ":title" scheduled for :when was cancelled.',
                'body_with_reason' => 'The session ":title" scheduled for :when was cancelled. Reason: :reason',
            ],
            'rescheduled' => ['name' => 'Session rescheduled', 'title' => 'A live session was rescheduled', 'body' => 'The new time for the session ":title" is :when.'],
        ],
    ],

    'mine' => [
        'title' => 'My notifications',
        'subtitle' => 'Only yours. You can mark a notification as read or delete it.',
        'stats' => ['total' => 'All my notifications', 'unread' => 'Unread'],
        'mark_read' => 'Mark as read', 'mark_all' => 'Mark all as read', 'delete' => 'Delete',
        'only_unread' => 'Unread only',
        'new' => 'New',
        'marked_read' => 'Notification marked as read.', 'marked_all' => 'All notifications marked as read.', 'deleted' => 'Notification deleted.',
        'empty_title' => 'No notifications', 'empty_text' => 'Payment, enrollment, certificate and session alerts will show up here.',
    ],

    'js' => [
        'errors' => [
            'network' => 'Network error. Check your connection.',
            'forbidden' => 'You are not allowed to do this.',
            'not_found' => 'Notification not found.',
            'conflict' => 'This conflicts with the current data.',
            'validation' => 'Please fix the highlighted fields.',
            'session_expired' => 'Your session expired. Reload the page.',
            'server' => 'Server error. Try again later.',
            'unknown' => 'An unexpected error occurred.',
        ],
        'delete_title' => 'Delete this notification?', 'delete_text' => 'It will be permanently removed from your list.',
        'delete_confirm' => 'Yes, delete', 'cancel' => 'Cancel',
        'read' => 'Read', 'unread' => 'Unread',
    ],
];
