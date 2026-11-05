<?php

return [
    'title'    => 'Dashboard',
    'hello'    => 'Welcome back, :name',
    'subtitle' => "Here's what's happening on the platform today.",
    'kpi' => [
        'users' => 'Users', 'courses' => 'Published courses', 'enrollments' => 'Active enrollments',
        'revenue' => 'Total revenue', 'pending_orders' => 'Pending orders', 'pending_receipts' => 'Receipts to review',
        'live' => 'Upcoming live sessions', 'of_total' => ':n total', 'vs_last' => 'vs last month', 'no_change' => 'No prior data',
    ],
    'charts' => [
        'revenue' => 'Revenue', 'revenue_sub' => 'Paid orders, last 12 months',
        'enrollments' => 'Enrollments', 'enrollments_sub' => 'New enrollments, last 12 months',
        'orders_by_status' => 'Orders by status', 'enroll_by_status' => 'Enrollments by status',
        'top_courses' => 'Top courses', 'top_courses_sub' => 'By active enrollments',
        'no_data' => 'No data yet', 'students' => ':n students',
    ],
    'lists' => [
        'recent_orders' => 'Recent orders', 'pending_receipts' => 'Receipts awaiting review', 'upcoming' => 'Upcoming live sessions',
        'view_all' => 'View all', 'order' => 'Order', 'student' => 'Student', 'total' => 'Total', 'status' => 'Status',
        'empty_orders' => 'No orders yet.', 'empty_receipts' => 'Nothing to review. All caught up!', 'empty_live' => 'No live sessions scheduled.',
        'deleted_user' => 'Deleted user', 'review' => 'Review',
    ],
    'quick' => [
        'title' => 'Quick actions', 'add_course' => 'New course', 'add_user' => 'Add user', 'orders' => 'Orders', 'receipts' => 'Receipts', 'live' => 'Live sessions',
    ],
];
