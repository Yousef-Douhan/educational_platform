<?php

return [
    'title' => 'Payments',
    'subtitle' => 'A read-only ledger of every payment attempt. Payments are recorded automatically when orders are paid, so nothing is added or edited here.',
    'breadcrumb' => ['home' => 'Home', 'group' => 'Sales'],
    'actions' => ['view' => 'View payment', 'clear' => 'Clear', 'reset' => 'Reset filters'],
    'stats' => [
        'total' => 'Total attempts', 'success' => 'Successful', 'pending' => 'Pending',
        'failed' => 'Failed', 'collected' => 'Collected',
    ],
    'filters' => [
        'search_placeholder' => 'Search by reference, order number or student...',
        'all_statuses' => 'All statuses', 'all_gateways' => 'All gateways', 'from' => 'From', 'to' => 'To',
    ],
    'statuses' => ['pending' => 'Pending', 'success' => 'Successful', 'failed' => 'Failed', 'refunded' => 'Refunded'],
    'fields' => [
        'student' => 'Student', 'email' => 'Email', 'gateway' => 'Gateway', 'payment_method' => 'Payment method',
        'transaction_reference' => 'Payment reference', 'paid_at' => 'Paid at', 'refunded_at' => 'Refunded at',
    ],
    'table' => [
        'id' => 'Payment', 'order' => 'Order', 'student' => 'Student', 'method' => 'Method / reference',
        'amount' => 'Amount', 'status' => 'Status', 'created_at' => 'Date', 'actions' => 'Actions', 'deleted_user' => 'Deleted',
    ],
    'modal' => [
        'show_title' => 'Payment details', 'show_desc' => 'One payment attempt for an order.',
        'payment_section' => 'Payment', 'response_section' => 'Gateway response',
    ],
    'hints' => ['response' => 'Card numbers, tokens, secrets and signatures are removed before the response is stored.'],
    'ui' => ['close' => 'Close'],
    'empty' => [
        'title' => 'No payments yet', 'text' => 'Payment attempts will appear here when orders are paid.',
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
        'no_response' => 'No response was stored (manual or free payment).',
    ],
];
