<?php

return [
    'title'    => 'لوحة التحكم',
    'hello'    => 'أهلاً بعودتك، :name',
    'subtitle' => 'هذا ملخص ما يجري في المنصة اليوم.',
    'kpi' => [
        'users' => 'المستخدمون', 'courses' => 'الدورات المنشورة', 'enrollments' => 'التسجيلات النشطة',
        'revenue' => 'إجمالي الإيرادات', 'pending_orders' => 'طلبات معلّقة', 'pending_receipts' => 'إشعارات تنتظر المراجعة',
        'live' => 'جلسات مباشرة قادمة', 'of_total' => 'من :n', 'vs_last' => 'مقارنة بالشهر الماضي', 'no_change' => 'لا بيانات سابقة',
    ],
    'charts' => [
        'revenue' => 'الإيرادات', 'revenue_sub' => 'الطلبات المدفوعة آخر 12 شهراً',
        'enrollments' => 'التسجيلات', 'enrollments_sub' => 'التسجيلات الجديدة آخر 12 شهراً',
        'orders_by_status' => 'الطلبات حسب الحالة', 'enroll_by_status' => 'التسجيلات حسب الحالة',
        'top_courses' => 'أفضل الدورات', 'top_courses_sub' => 'حسب التسجيلات النشطة',
        'no_data' => 'لا توجد بيانات بعد', 'students' => ':n طالب',
    ],
    'lists' => [
        'recent_orders' => 'أحدث الطلبات', 'pending_receipts' => 'إشعارات تنتظر المراجعة', 'upcoming' => 'الجلسات المباشرة القادمة',
        'view_all' => 'عرض الكل', 'order' => 'الطلب', 'student' => 'الطالب', 'total' => 'الإجمالي', 'status' => 'الحالة',
        'empty_orders' => 'لا توجد طلبات بعد.', 'empty_receipts' => 'لا شيء للمراجعة. كل شيء منجز!', 'empty_live' => 'لا توجد جلسات مباشرة مجدولة.',
        'deleted_user' => 'مستخدم محذوف', 'review' => 'مراجعة',
    ],
    'quick' => [
        'title' => 'إجراءات سريعة', 'add_course' => 'دورة جديدة', 'add_user' => 'إضافة مستخدم', 'orders' => 'الطلبات', 'receipts' => 'الإشعارات', 'live' => 'الجلسات المباشرة',
    ],
];
