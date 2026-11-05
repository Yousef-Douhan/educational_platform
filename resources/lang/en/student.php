<?php

return [
    'free' => 'Free',
    'lessons_count' => '{0} No lessons|{1} 1 lesson|[2,*] :count lessons',

    'nav' => [
        'notes' => 'My notes', 'schedule' => 'My weekly schedule',
        'certificates' => 'Certificates', 'more' => 'More', 'verify_certificate' => 'Verify a certificate', 'categories' => 'Categories', 'home' => 'Home', 'courses' => 'Courses', 'my_courses' => 'My courses', 'orders' => 'My orders',
        'cart' => 'Cart', 'admin' => 'Admin panel', 'logout' => 'Log out',
        'language' => 'Language', 'theme' => 'Dark / light mode', 'notifications' => 'Notifications', 'live_sessions' => 'Live sessions', 'profile' => 'My account',
        'login' => 'Log in', 'register' => 'Sign up',
    ],

    'notif' => [
        'subtitle' => 'You have :n unread notifications.', 'show_all' => 'Show all', 'open' => 'Open',
    ],

    'live' => [
        'subtitle' => 'Sessions for your courses. The join button opens :m minutes before the start.',
        'upcoming' => 'Upcoming and live', 'past' => 'Past sessions', 'none' => 'No upcoming sessions.',
        'join' => 'Join session', 'opens_at' => 'Opens at :t', 'min' => 'min',
        'not_open' => 'This session is not open for joining right now.', 'bad_link' => 'The session link is invalid. Contact the instructor.',
    ],

    'verify' => [
        'title' => 'Verify your email', 'text' => 'We sent a verification link to :email. Open it to activate enrollment and purchases.',
        'resend' => 'Resend the link', 'browse' => 'Browse courses', 'sent' => 'A new verification link has been sent.',
        'done' => 'Your email has been verified. You can enroll and buy now.',
        'banner' => 'Verify your email to enroll in courses and buy.',
    ],

    'pw' => [
        'forgot_link' => 'Forgot password?', 'forgot_title' => 'Forgot your password?',
        'forgot_hint' => 'Enter your email and we will send you a link to reset your password.',
        'send_link' => 'Send reset link',
        'link_sent' => 'If the email belongs to an active account, a reset link has been sent.',
        'reset_title' => 'Set a new password', 'reset_submit' => 'Reset password',
        'invalid' => 'The reset link is invalid or has expired. Request a new one.',
        'reset_done' => 'Your password has been reset. You can sign in now.',
    ],

    'profile' => [
        'title' => 'My account', 'info' => 'Personal information', 'save' => 'Save',
        'email_locked' => 'The email cannot be changed here.',
        'change_password' => 'Change password', 'current_password' => 'Current password',
        'new_password' => 'New password', 'update_password' => 'Update password',
        'saved' => 'Your information has been saved.', 'password_changed' => 'Your password has been changed.',
    ],

    'auth' => [
        'google' => 'Continue with Google',
        'or' => 'or',
        'google_failed' => 'Google sign-in failed. Please try again.',
        'google_unavailable' => 'Google sign-in is currently unavailable.',
        'google_unverified' => 'This Google account email is not verified, so it cannot be used.',
        'google_blocked' => 'This account is not available for sign-in.',
        'google_staff' => 'Staff accounts sign in with email and password.',
        'create_account' => 'Create account', 'name' => 'Name', 'email' => 'Email',
        'password' => 'Password', 'password_confirmation' => 'Confirm password',
        'password_hint' => 'At least 8 characters with letters and numbers.',
        'have_account' => 'Already have an account?',
        'sign_in' => 'Sign in', 'sign_in_hint' => 'Welcome back, sign in to continue learning.', 'remember' => 'Remember me',
        'no_account' => "Don't have an account?",
        'failed' => 'These credentials do not match our records, or the account is not active.',
    ],

    'messages' => [
        'enrolled' => 'You are enrolled in the course.',
        'added' => 'Course added to your cart.',
        'removed' => 'Course removed from your cart.',
        'welcome' => 'Welcome! Your account has been created.',
    ],

    'errors' => [
        'account_inactive' => 'Your account is not active.',
        'course_unavailable' => 'This course is not available.',
        'not_free' => 'This course is paid, add it to your cart.',
        'own_course' => 'You cannot enroll in a course you teach.',
        'already_enrolled' => 'You are already enrolled in this course.',
        'enrollment_closed' => 'Enrollment for this course is closed.',
    ],

    'levels' => ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'],

    'home' => [
        'tagline' => 'A better future starts with education', 'browse_all' => 'Browse all courses',
        'stat_courses' => 'Courses', 'stat_instructors' => 'Instructors', 'stat_students' => 'Students',
        'footer_about' => 'A learning platform with recorded and live courses, quizzes and completion certificates, so you can learn anytime, anywhere.',
        'why' => 'Why learn with us?', 'trainers' => 'Our instructors',
        'features' => [
            'f1_title' => 'Recorded and live lessons', 'f1_text' => 'Watch lessons any time and join live sessions with the instructor.',
            'f2_title' => 'Easy to use', 'f2_text' => 'A simple interface that works on desktop and mobile.',
            'f3_title' => 'Quizzes and certificates', 'f3_text' => 'Test yourself and earn a completion certificate.',
            'f4_title' => 'Expert staff', 'f4_text' => 'Experienced instructors in their fields.',
        ],
        'title' => 'Learn new skills at your own pace',
        'subtitle' => 'Structured courses from expert instructors, with progress tracking and certificates.',
        'inst_eyebrow' => 'Our experts', 'inst_sub' => 'Specialists in their fields, guiding you step by step.', 'categories' => 'Categories', 'cat_eyebrow' => 'Explore', 'cat_sub' => 'Pick the field that suits you and start learning.', 'cat_courses' => '{0} No courses yet|{1} 1 course|[2,*] :count courses', 'latest' => 'Latest courses', 'all' => 'View all',
    ],

    'catalog' => [
        'title' => 'All courses', 'search_placeholder' => 'Search for a course', 'search' => 'Search',
        'all_categories' => 'All categories', 'all_levels' => 'All levels', 'any_price' => 'Any price',
        'paid' => 'Paid', 'apply' => 'Apply', 'reset' => 'Reset', 'empty' => 'No matching courses.',
        'sort' => ['newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'],
    ],

    'course' => [
        'login_required' => 'Browsing is open to everyone. You will be asked to log in when you enroll or buy.', 'logged_in_continue' => 'You are logged in. You can now continue with this course.', 'details' => 'Details', 'by' => 'By', 'minutes' => ':count min', 'about' => 'About this course', 'outcomes' => 'What you will learn',
        'requirements' => 'Requirements', 'curriculum' => 'Curriculum', 'preview' => 'Free preview',
        'no_curriculum' => 'The curriculum has not been published yet.', 'you_teach' => 'You teach this course.',
        'go_to_my_courses' => 'Go to my courses', 'login_to_enroll' => 'Log in to enroll',
        'enroll_free' => 'Enroll for free', 'in_cart' => 'In your cart, go to cart', 'add_to_cart' => 'Add to cart',
    ],

    'footer' => ['quick_links' => 'Quick links'],

    'learn' => [
        'no_lessons' => 'This course has no published lessons yet.', 'not_enrolled' => 'You must be enrolled in the course to watch this lesson.',
        'completed' => 'Lesson completed. Continue to the next one.', 'completed_last' => 'Lesson completed.', 'uncompleted' => 'Lesson marked as not completed.',
        'continue' => 'Continue learning', 'start' => 'Start course', 'back_to_course' => 'Course page', 'back_to_lesson' => 'Back to the lesson',
        'mark_done' => 'Mark as completed', 'mark_undone' => 'Completed (undo)', 'prev' => 'Previous', 'next' => 'Next',
        'open_video' => 'Open the video in a new window', 'video_unavailable' => 'The video cannot be shown right now.', 'article_empty' => 'This lesson has no text content.',
        'live_note' => 'This is a live session. The join link will reach you at its time.',
        'attachments' => 'Attachments', 'login_for_files' => 'Log in to download attachments.',
        'no_quiz' => 'The quiz is not available right now.', 'pass_mark' => 'Pass mark', 'time_limit' => 'Time limit', 'no_limit' => 'No limit',
        'attempts' => 'Attempts', 'unlimited' => 'Unlimited', 'quiz_passed' => 'You have passed this quiz.',
        'start_quiz' => 'Start quiz', 'retry_quiz' => 'New attempt', 'resume_quiz' => 'Resume current attempt',
        'past_attempts' => 'Your previous attempts', 'in_progress' => 'In progress', 'result_passed' => 'Passed', 'result_failed' => 'Not passed',
        'attempt_no' => 'Attempt #:n', 'remaining' => 'Time left', 'points' => 'pts', 'submit_quiz' => 'Submit quiz',
        'time_up_note' => 'Time is up; only the saved answers were graded.',
    ],

    'certs' => [
        'ready' => 'You completed these courses and can claim their certificates', 'get' => 'Claim certificate', 'issued' => 'Your certificate has been issued.',
        'empty' => 'No certificates yet. Complete a course to earn one.', 'revoked' => 'This certificate has been revoked.',
        'download' => 'Download PDF', 'verify' => 'Verify', 'pdf_unavailable' => 'The PDF cannot be generated right now. Please try again later.',
    ],

    'pay' => [
        'pay_now' => 'Pay now', 'secure_note' => 'Payment is made on the secure Stripe page; we never receive your card details.',
        'processing' => 'We received your payment and are confirming it, which takes a moment. Refresh shortly and your course access will open.',
        'canceled' => 'The payment was not completed. You can try again any time before the order expires.',
        'go_learn' => 'Start learning',
        'errors' => [
            'not_configured' => 'Online payment is not enabled right now. Please contact the administration.',
            'unreachable' => 'Could not reach the payment gateway. Please try again shortly.',
            'rejected' => 'The payment gateway rejected the request. Try again or contact the administration.',
            'bad_response' => 'Unexpected response from the payment gateway. Please try again.',
        ],
    ],

    'mine' => [
        'completed' => 'Course completed', 'empty' => 'You are not enrolled in any course yet.', 'browse' => 'Browse courses',
        'progress' => 'Progress', 'expires' => 'Access ends on :date',
    ],

    'cart' => [
        'empty' => 'Your cart is empty.', 'remove' => 'Remove', 'subtotal' => 'Subtotal',
        'coupon' => 'Coupon code (optional)', 'checkout' => 'Place order',
        'issues' => [
            'course_unavailable' => 'This course is no longer available and will be skipped.',
            'own_course' => 'You teach this course and it will be skipped.',
            'already_enrolled' => 'You are already enrolled and it will be skipped.',
            'became_free' => 'It is now free, enroll from its page.',
        ],
    ],

    'orders' => [
        'empty' => 'No orders yet.', 'number' => 'Order', 'date' => 'Date', 'items' => 'Items',
        'total' => 'Total', 'status' => 'Status', 'discount' => 'Discount',
        'pending_note' => 'This order is awaiting payment. Access is granted once payment is confirmed.',
    ],

    'order_status' => [
        'pending' => 'Awaiting payment', 'paid' => 'Paid', 'failed' => 'Payment failed',
        'cancelled' => 'Cancelled', 'refunded' => 'Refunded',
    ],
    'notes' => [
        'title' => 'My notes', 'subtitle' => 'Write notes for each course and sketch on the whiteboard. Everything is saved to your account.',
        'tab_notes' => 'My notes', 'tab_board' => 'Whiteboard',
        'general' => 'General', 'new_note' => 'New note', 'untitled' => 'Untitled', 'title_ph' => 'Note title',
        'body_ph' => 'Write your notes here... (Ctrl+S to save)', 'save' => 'Save', 'clear' => 'Clear', 'delete' => 'Delete',
        'confirm_delete' => 'Delete this note permanently?', 'confirm_clear_note' => 'Clear this note\'s text?',
        'saved_at' => 'Saved :time', 'saving' => 'Saving...', 'save_failed' => 'Could not save. Please try again.',
        'select' => 'Pick a note from the list or create a new one.', 'empty_folder' => 'No notes in this folder.',
        'preview' => 'Preview', 'edit' => 'Edit', 'folders' => 'Folders', 'limit' => 'You reached the maximum number of notes.',
        'tb' => ['bold' => 'Bold', 'italic' => 'Italic', 'heading' => 'Heading', 'bullet' => 'Bulleted list', 'number' => 'Numbered list', 'quote' => 'Quote', 'code' => 'Code', 'rule' => 'Divider'],
        'board' => [
            'page' => 'Page :n', 'add_page' => 'New page', 'delete_page' => 'Delete page', 'limit' => 'You reached the maximum number of pages.',
            'tool' => 'Tool', 'pen' => 'Pen', 'eraser' => 'Eraser', 'line' => 'Line', 'rect' => 'Rectangle', 'circle' => 'Circle',
            'shape' => 'Image', 'image' => 'Upload image', 'color' => 'Color', 'size' => 'Size', 'undo' => 'Undo', 'clear' => 'Clear', 'download' => 'Download',
            'confirm_clear' => 'Clear the whole board?', 'confirm_page' => 'Delete this page and everything on it?', 'image_bad' => 'The image file is invalid or too large.',
        ],
    ],
    'schedule' => [
        'title' => 'Weekly Schedule Maker', 'subtitle' => 'Arrange your weekly lectures and labs with automatic conflict checking, saving and high-quality printing.',
        'add' => 'Add lecture / lab', 'print' => 'Print', 'clear_all' => 'Clear all', 'confirm_clear' => 'Clear the whole schedule? This cannot be undone.',
        'time' => 'Time', 'edit' => 'Edit', 'new' => 'Add to schedule', 'name' => 'Name', 'name_ph' => 'e.g. Programming 1',
        'kind' => 'Type', 'day' => 'Day', 'from' => 'From', 'to' => 'To', 'room' => 'Room / place (optional)', 'color' => 'Color',
        'save' => 'Save', 'cancel' => 'Cancel', 'delete' => 'Delete', 'confirm_delete' => 'Delete this item?',
        'kinds' => ['lecture' => 'Lecture', 'exam' => 'Exam', 'lab' => 'Lab', 'other' => 'Other'],
        'days' => ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        'conflict_one' => 'Schedule conflict: two or more items overlap (outlined in red).',
        'no_conflict' => 'No conflicts.', 'empty' => 'Your schedule is empty. Add your first lecture.',
        'end_after_start' => 'End time must be after start time.', 'too_many' => 'You reached the maximum number of items.',
        'saving_failed' => 'Could not save, please try again.', 'conflict_badge' => 'Conflict',
    ],
    'pomodoro' => [
        'title' => 'Focus timer (Pomodoro)', 'open' => 'Focus timer', 'close' => 'Close',
        'start' => 'Start timer', 'pause' => 'Pause', 'reset' => 'Reset', 'done' => 'Session finished! Take a short break.',
        'focus' => 'Focus (25 min)', 'short' => 'Break (5 min)', 'long' => 'Break (15 min)',
    ],
];
