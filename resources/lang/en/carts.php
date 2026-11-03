<?php

return [
    'title' => 'Shopping carts',
    'subtitle' => 'Monitor student carts and abandoned carts. Students manage their own cart from the site.',
    'breadcrumb' => ['home' => 'Home', 'group' => 'Sales'],
    'actions' => ['view' => 'View cart', 'clear_cart' => 'Clear cart', 'clear' => 'Clear', 'reset' => 'Reset filters'],
    'stats' => ['total' => 'Total carts', 'active' => 'Active carts', 'abandoned' => 'Abandoned (over :days days)', 'empty' => 'Empty carts'],
    'filters' => ['search_placeholder' => 'Search by student name or email...', 'all_states' => 'All states'],
    'states' => ['empty' => 'Empty', 'active' => 'Active', 'abandoned' => 'Abandoned'],
    'issues' => [
        'course_unavailable' => 'Course is no longer available',
        'own_course' => 'Student teaches this course',
        'already_enrolled' => 'Student is already enrolled',
        'became_free' => 'Course became free',
    ],
    'fields' => ['student' => 'Student', 'email' => 'Email'],
    'table' => [
        'student' => 'Student', 'items' => 'Items', 'total' => 'Total', 'updated_at' => 'Last activity',
        'state' => 'State', 'actions' => 'Actions', 'deleted_user' => 'Deleted',
    ],
    'modal' => [
        'show_title' => 'Cart details', 'show_desc' => 'Cart contents at current prices.',
        'student_section' => 'Student', 'items_section' => 'Cart items', 'subtotal' => 'Current subtotal',
    ],
    'hints' => ['subtotal' => 'Counts valid items only, at current course prices. The final price is fixed when the order is created.'],
    'ui' => ['close' => 'Close'],
    'empty' => [
        'title' => 'No carts yet', 'text' => 'Carts will appear here when students add courses to them.',
        'no_results_title' => 'No matching results', 'no_results_text' => 'Try changing the search or filter.',
    ],
    'messages' => ['cleared' => 'Cart cleared (:count items).'],
    'errors' => [
        'account_inactive' => 'Your account is not active, so you cannot add courses to the cart.',
        'course_unavailable' => 'This course is not available right now.',
        'own_course' => 'You cannot buy a course you teach.',
        'free_course' => 'This course is free, you can enroll directly.',
        'already_enrolled' => 'You are already enrolled in this course.',
        'already_in_cart' => 'This course is already in your cart.',
        'cart_full' => 'Your cart has reached the maximum number of courses.',
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
        'no_items' => 'The cart is empty.',
        'cancel' => 'Cancel',
        'clear_title' => 'Clear this cart?',
        'clear_text' => 'All items will be removed from the student cart. Their enrollments are not affected.',
        'clear_confirm' => 'Yes, clear it',
    ],
];
