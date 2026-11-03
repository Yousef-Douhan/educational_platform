<?php

return [
    'title' => 'سجل التدقيق',
    'subtitle' => 'سجل دائم لمن فعل ماذا ومتى ومن أين. لا يمكن تعديل أي سطر فيه أو حذفه من النظام، ولا تُخزَّن فيه كلمات المرور ولا الرموز السرية ولا بيانات الدفع.',
    'breadcrumb' => ['home' => 'الرئيسية', 'group' => 'الأمان'],
    'actions' => ['view' => 'التفاصيل', 'clear' => 'مسح', 'reset' => 'إعادة ضبط الفلاتر'],
    'stats' => ['total' => 'كل السجلات', 'today' => 'اليوم', 'week' => 'آخر 7 أيام', 'deletions' => 'عمليات الحذف', 'failed_logins' => 'محاولات دخول فاشلة'],
    'filters' => [
        'search_placeholder' => 'ابحث بالمستخدم أو العنصر أو العملية أو الـ IP...', 'all_types' => 'كل الأقسام', 'all_events' => 'كل العمليات',
        'from' => 'من', 'to' => 'إلى',
    ],
    'types' => [
        'user' => 'مستخدم', 'user_profile' => 'ملف مستخدم', 'role' => 'دور', 'permission' => 'صلاحية',
        'category' => 'تصنيف', 'course' => 'دورة', 'course_section' => 'قسم دورة', 'lesson' => 'درس',
        'enrollment' => 'تسجيل', 'quiz' => 'اختبار', 'certificate' => 'شهادة',
        'certificate_template' => 'قالب شهادة', 'coupon' => 'كوبون', 'order' => 'طلب',
        'payment' => 'دفعة', 'live_session' => 'جلسة مباشرة', 'quiz_attempt' => 'محاولة اختبار', 'auth' => 'تسجيل الدخول',
    ],
    'events' => [
        'created' => 'إنشاء', 'updated' => 'تعديل', 'deleted' => 'حذف', 'force_deleted' => 'حذف نهائي',
        'restored' => 'استرجاع', 'login' => 'دخول', 'logout' => 'خروج', 'login_failed' => 'دخول فاشل',
    ],
    'table' => ['when' => 'الوقت', 'user' => 'المستخدم', 'action' => 'العملية', 'subject' => 'العنصر', 'ip' => 'عنوان IP', 'actions' => 'التفاصيل', 'system' => 'زائر / النظام'],
    'fields' => ['email' => 'البريد الإلكتروني', 'subject_id' => 'رقم السجل', 'user_agent' => 'المتصفح', 'description' => 'ملاحظات'],
    'modal' => ['show_title' => 'تفاصيل السجل', 'show_desc' => 'ما حدث بالضبط وما الذي تغيّر.', 'summary_section' => 'الملخص', 'changes_section' => 'التغييرات'],
    'ui' => ['close' => 'إغلاق'],
    'empty' => [
        'title' => 'لا توجد سجلات بعد', 'text' => 'ستُسجَّل هنا العمليات التي تتم في النظام.',
        'no_results_title' => 'لا توجد نتائج مطابقة', 'no_results_text' => 'جرّب تغيير البحث أو الفلاتر.',
    ],
    'js' => [
        'errors' => [
            'network' => 'خطأ في الشبكة. تحقق من اتصالك.',
            'forbidden' => 'ليست لديك صلاحية لهذا الإجراء.',
            'not_found' => 'السجل غير موجود.',
            'conflict' => 'هناك تعارض مع البيانات الحالية.',
            'validation' => 'يرجى تصحيح الحقول المظللة.',
            'session_expired' => 'انتهت جلستك. أعد تحميل الصفحة.',
            'server' => 'خطأ في الخادم. حاول لاحقًا.',
            'unknown' => 'حدث خطأ غير متوقع.',
        ],
        'no_changes' => 'لم تُسجَّل قيم حقول لهذا السطر.',
        'field' => 'الحقل', 'old_value' => 'قبل', 'new_value' => 'بعد',
        'redacted' => 'محجوب (حساس)', 'system' => 'زائر / النظام',
    ],
];
