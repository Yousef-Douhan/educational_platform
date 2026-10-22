<?php

return [
    'title'    => 'User Profiles',
    'subtitle' => 'Manage profile details, contact info and avatars for your users.',

    'breadcrumb' => ['home' => 'Home', 'group' => 'User Management'],

    'actions' => [
        'add' => 'Add profile',
        'trash' => 'Trash',
        'clear' => 'Clear',
        'reset' => 'Reset filters',
        'close' => 'Close',
        'cancel' => 'Cancel',
        'save' => 'Save',
        'update' => 'Save changes',
        'choose_image' => 'Choose image',
        'view' => 'View details',
        'edit' => 'Edit record',
        'delete' => 'Delete',
        'restore' => 'Restore',
        'force_delete' => 'Delete forever',
    ],

    'stats' => [
        'total' => 'Total records',
        'active' => 'Active records',
        'with_avatar' => 'With avatar',
        'trashed' => 'Deleted records',
    ],

    'filters' => [
        'search_placeholder' => 'Search by name, email, phone or bio...',
        'all_users' => 'All users',
        'from' => 'From',
        'to' => 'To',
    ],

    'fields' => [
        'user' => 'User',
        'avatar' => 'Avatar',
        'phone' => 'Phone',
        'website' => 'Website',
        'social_links' => 'Social links',
        'bio' => 'Bio',
        'remove_avatar' => 'Remove current avatar',
    ],

    'table' => [
        'user' => 'User',
        'phone' => 'Phone',
        'website' => 'Website',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
        'deleted_at' => 'Deleted at',
        'actions' => 'Actions',
    ],

    'placeholders' => [
        'phone' => '+970 599 000 000',
        'website' => 'https://example.com',
        'social_links' => 'https://linkedin.com/in/username',
        'bio' => 'Write a short bio...',
    ],

    'hints' => [
        'avatar' => 'JPG, PNG or WEBP, up to 2 MB.',
        'social_links' => 'Up to 255 characters.',
    ],

    'modal' => [
        'create_title' => 'Add new profile',
        'create_desc' => 'Link a profile to an existing user.',
        'edit_title' => 'Edit profile',
        'edit_desc' => 'Update the profile information.',
        'show_title' => 'Profile details',
        'show_desc' => 'Full information about this record.',
        'contact_section' => 'Contact information',
        'bio_section' => 'Bio',
        'dates_section' => 'Dates',
        'trash_title' => 'Deleted profiles',
        'trash_desc' => 'Restore records or delete them permanently.',
    ],

    'trash' => [
        'warning' => 'Permanent deletion cannot be undone and also removes the stored avatar file.',
    ],

    'empty' => [
        'title' => 'No profiles yet',
        'text' => 'Start by adding the first user profile.',
        'no_results_title' => 'No matching results',
        'no_results_text' => 'Try changing or resetting the filters.',
        'trash_title' => 'Trash is empty',
        'trash_text' => 'There are no deleted records.',
    ],

    'pagination' => ['previous' => 'Previous', 'next' => 'Next'],

    'validation' => [
        'user_id_required' => 'Please select a user.',
        'user_id_exists'   => 'The selected user does not exist.',
        'user_id_unique'   => 'This user already has a profile (it may be in the trash).',
        'avatar_image'     => 'The avatar must be an image.',
        'avatar_max'       => 'The avatar must not be larger than 2 MB.',
        'website_url'      => 'Please enter a valid URL starting with http:// or https://',
    ],

    'messages' => [
        'created' => 'Profile created successfully.',
        'updated' => 'Profile updated successfully.',
        'deleted' => 'Profile moved to trash.',
        'restored' => 'Profile restored successfully.',
        'force_deleted' => 'Profile deleted permanently.',
        'error' => 'Something went wrong. Please try again.',
    ],

    // يُمرَّر إلى JavaScript. لا تستخدم علامة الاقتباس المفردة هنا.
    'js' => [
        'errors' => [
            'network' => 'Network error. Check your connection.',
            'forbidden' => 'You do not have permission for this action.',
            'not_found' => 'The record was not found.',
            'conflict' => 'A conflict occurred with the current data.',
            'validation' => 'Please correct the highlighted fields.',
            'session_expired' => 'Your session has expired. Reload the page.',
            'server' => 'Server error. Please try again later.',
            'unknown' => 'An unexpected error occurred.',
        ],
        'avatar_too_large' => 'The image must not be larger than 2 MB.',
        'select_user' => 'Select a user',
        'no_available_users' => 'All users already have profiles.',
        'cancel' => 'Cancel',
        'delete_title' => 'Move to trash?',
        'delete_text' => 'You can restore this record later from the trash.',
        'delete_confirm' => 'Yes, delete',
        'restore_title' => 'Restore this record?',
        'restore_text' => 'The profile will be visible in the list again.',
        'restore_confirm' => 'Yes, restore',
        'force_title' => 'Delete permanently?',
        'force_text' => 'This cannot be undone. The record and its avatar file will be removed.',
        'force_confirm' => 'Yes, delete forever',
    ],
];
