<?php

return [
    'title' => 'Orders',
    'subtitle' => 'Review student orders and payments. Orders are created when students check out; here you confirm manual payments, cancel or refund.',
    'breadcrumb' => ['home' => 'Home', 'group' => 'Sales'],
    'actions' => [
        'view' => 'View order', 'mark_paid' => 'Confirm payment', 'cancel_order' => 'Cancel order',
        'refund' => 'Refund order', 'clear' => 'Clear', 'reset' => 'Reset filters',
    ],
    'stats' => [
        'total' => 'Total orders', 'pending' => 'Pending', 'paid' => 'Paid',
        'refunded' => 'Refunded', 'revenue' => 'Revenue (paid)',
    ],
    'filters' => [
        'search_placeholder' => 'Search by order number, student or payment reference...',
        'all_statuses' => 'All statuses', 'from' => 'From', 'to' => 'To',
    ],
    'statuses' => [
        'pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded',
    ],
    'gateways' => [
        'manual' => 'Manual', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash',
        'free' => 'Free (100% coupon)', 'stripe' => 'Stripe', 'paypal' => 'PayPal',
    ],
    'fields' => [
        'student' => 'Student', 'email' => 'Email', 'paid_at' => 'Paid at', 'refunded_at' => 'Refunded at',
        'gateway' => 'Payment method', 'transaction_reference' => 'Payment reference', 'note' => 'Note / reason',
        'subtotal' => 'Subtotal', 'discount' => 'Discount', 'total' => 'Total',
    ],
    'table' => [
        'number' => 'Order', 'student' => 'Student', 'items' => 'Courses', 'total' => 'Total',
        'status' => 'Status', 'created_at' => 'Created', 'actions' => 'Actions', 'deleted_user' => 'Deleted',
    ],
    'modal' => [
        'show_title' => 'Order details', 'show_desc' => 'Prices below were fixed when the order was created.',
        'order_section' => 'Order', 'items_section' => 'Courses',
    ],
    'hints' => [
        'frozen' => 'Prices and titles are a snapshot from checkout time and do not change if the course is edited later.',
        'reference' => 'Receipt or transfer number. Each reference can be used once per payment method.',
    ],
    'placeholders' => ['reference' => 'e.g. TRX-20261103-001'],
    'ui' => ['close' => 'Close', 'cancel' => 'Cancel', 'confirm' => 'Confirm'],
    'empty' => [
        'title' => 'No orders yet', 'text' => 'Orders will appear here when students check out.',
        'no_results_title' => 'No matching results', 'no_results_text' => 'Try changing the search or filters.',
    ],
    'messages' => [
        'marked_paid' => 'Order :number marked as paid and access granted.',
        'cancelled' => 'Order :number cancelled.',
        'refunded' => 'Order :number refunded and access removed.',
    ],
    'system_notes' => [
        'superseded' => 'Replaced by a newer order.',
        'expired' => 'Closed automatically: payment was not completed in time.',
        'refund_revoked' => 'Order refunded',
    ],
    'errors' => [
        'account_inactive' => 'Your account is not active, so you cannot place orders.',
        'empty_cart' => 'Your cart has no valid courses to order.',
        'coupon_per_user_limit' => 'You have already used this coupon the maximum number of times.',
        'invalid_transition' => 'This action is not allowed for the order in its current status.',
        'duplicate_reference' => 'This payment reference is already used by another order.',
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
        'course_deleted' => 'Course deleted',
        'actions' => [
            'mark-paid' => [
                'title' => 'Confirm payment', 'desc' => 'Record that this order was paid. The student gets access to the courses immediately.',
                'confirm' => 'Confirm payment', 'note_required' => false,
            ],
            'cancel' => [
                'title' => 'Cancel this order?', 'desc' => 'Only unpaid orders can be cancelled. A coupon used on it is given back.',
                'confirm' => 'Cancel order', 'note_required' => false,
            ],
            'refund' => [
                'title' => 'Refund this order?', 'desc' => 'The student loses access to these courses and their certificates are revoked. The refund itself must be made with the payment provider.',
                'confirm' => 'Refund order', 'note_required' => true,
            ],
        ],
    ],
];
