<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseLearningOutcome;
use App\Models\CourseRequirement;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * دورات تجريبية منشورة (ثنائية اللغة) موزّعة على التصنيفات، كل دورة بقسمين وأربعة دروس مقالية.
 * آمن للتشغيل المتكرر: يتخطّى أي دورة موجودة بنفس الـslug (حتى المحذوفة) ولا يعدّل شيئًا عليها.
 * المدرّس: يُحدَّد حسب التصنيف الرئيسي للدورة من InstructorSeeder (8 مدرّسين تجريبيين).
 *
 *   php artisan db:seed --class=CourseSeeder
 */
class CourseSeeder extends Seeder
{
    /** @var array<int, array{0:string,1:string,2:string,3:string,4:string,5:int|float,6:int|float|null,7:array<int, array{0:string,1:string}>}> */
    public const COURSES = [
        ['python-from-zero', 'python', 'بايثون من الصفر إلى الاحتراف', 'Python from Zero to Pro', 'beginner', 0, null, [['تثبيت بايثون وأول برنامج', 'Installing Python & your first program'], ['المتغيرات وأنواع البيانات', 'Variables and data types'], ['الشروط والحلقات', 'Conditions and loops'], ['الدوال والملفات', 'Functions and files']]],
        ['laravel-complete-course', 'php-laravel', 'لارافيل: بناء تطبيق ويب متكامل', 'Laravel: Build a Complete Web App', 'intermediate', 149, 99, [['بنية المشروع والمسارات', 'Project structure and routing'], ['قواعد البيانات وإيلوكوانت', 'Databases and Eloquent'], ['المصادقة والصلاحيات', 'Authentication and permissions'], ['الاختبارات والنشر', 'Testing and deployment']]],
        ['javascript-essentials', 'javascript', 'أساسيات جافاسكربت الحديثة', 'Modern JavaScript Essentials', 'beginner', 49, null, [['أساسيات اللغة', 'Language basics'], ['التعامل مع DOM', 'Working with the DOM'], ['الأحداث والنماذج', 'Events and forms'], ['الطلبات غير المتزامنة', 'Asynchronous requests']]],
        ['html-css-responsive', 'web-development', 'HTML وCSS وتصميم المواقع المتجاوبة', 'HTML & CSS Responsive Websites', 'beginner', 0, null, [['هيكلة الصفحة بـ HTML', 'Structuring pages with HTML'], ['التنسيق بـ CSS', 'Styling with CSS'], ['التخطيط بـ Flexbox وGrid', 'Layouts with Flexbox and Grid'], ['التصميم المتجاوب', 'Responsive design']]],
        ['java-oop', 'java', 'البرمجة كائنية التوجه بلغة جافا', 'Object-Oriented Programming in Java', 'intermediate', 79, null, [['الأصناف والكائنات', 'Classes and objects'], ['الوراثة وتعدد الأشكال', 'Inheritance and polymorphism'], ['الواجهات والأصناف المجردة', 'Interfaces and abstract classes'], ['معالجة الاستثناءات', 'Exception handling']]],
        ['mysql-databases', 'databases', 'قواعد البيانات MySQL من الأساس', 'MySQL Databases Fundamentals', 'beginner', 59, 39, [['تصميم الجداول والعلاقات', 'Designing tables and relations'], ['استعلامات SELECT', 'SELECT queries'], ['الربط والتجميع', 'Joins and aggregation'], ['الفهارس والأداء', 'Indexes and performance']]],
        ['mobile-flutter', 'mobile-development', 'تطبيقات الموبايل باستخدام Flutter', 'Mobile Apps with Flutter', 'intermediate', 129, null, [['مقدمة Flutter وDart', 'Intro to Flutter and Dart'], ['الواجهات والعناصر', 'Widgets and layouts'], ['إدارة الحالة', 'State management'], ['الاتصال بالخادم', 'Connecting to an API']]],
        ['machine-learning-intro', 'machine-learning', 'مقدمة في تعلّم الآلة', 'Introduction to Machine Learning', 'intermediate', 99, null, [['ما هو تعلّم الآلة؟', 'What is machine learning?'], ['الانحدار والتصنيف', 'Regression and classification'], ['تقييم النماذج', 'Model evaluation'], ['مشروع تطبيقي', 'A hands-on project']]],
        ['data-analysis-excel-python', 'data-analysis', 'تحليل البيانات بإكسل وبايثون', 'Data Analysis with Excel & Python', 'beginner', 69, null, [['تنظيف البيانات', 'Cleaning data'], ['الجداول المحورية', 'Pivot tables'], ['مكتبة Pandas', 'The Pandas library'], ['عرض النتائج', 'Presenting results']]],
        ['ethical-hacking-basics', 'ethical-hacking', 'أساسيات الاختراق الأخلاقي', 'Ethical Hacking Basics', 'intermediate', 119, 89, [['مفاهيم الأمن وأخلاقيات المهنة', 'Security concepts and ethics'], ['جمع المعلومات', 'Information gathering'], ['فحص الثغرات', 'Vulnerability scanning'], ['كتابة التقرير', 'Writing the report']]],
        ['network-security', 'network-security', 'أمن الشبكات والجدران النارية', 'Network Security & Firewalls', 'intermediate', 99, null, [['التهديدات الشائعة', 'Common threats'], ['الجدران النارية', 'Firewalls'], ['الشبكات الخاصة الافتراضية', 'VPNs'], ['المراقبة والاستجابة', 'Monitoring and response']]],
        ['cryptography-intro', 'cryptography', 'مدخل إلى التشفير', 'Introduction to Cryptography', 'intermediate', 0, null, [['التشفير المتماثل', 'Symmetric encryption'], ['التشفير غير المتماثل', 'Asymmetric encryption'], ['دوال التجزئة', 'Hash functions'], ['التوقيع الرقمي', 'Digital signatures']]],
        ['networking-ccna-basics', 'networking', 'أساسيات الشبكات (CCNA)', 'Networking Fundamentals (CCNA)', 'beginner', 89, null, [['نموذج OSI وTCP/IP', 'OSI and TCP/IP models'], ['عنونة IP والشبكات الفرعية', 'IP addressing and subnetting'], ['الموجّهات والمحوّلات', 'Routers and switches'], ['استكشاف الأعطال', 'Troubleshooting']]],
        ['linux-administration', 'linux', 'إدارة أنظمة لينكس', 'Linux System Administration', 'beginner', 0, null, [['أوامر الطرفية', 'Terminal commands'], ['الملفات والصلاحيات', 'Files and permissions'], ['إدارة الحزم والخدمات', 'Packages and services'], ['كتابة سكربتات Bash', 'Writing Bash scripts']]],
        ['devops-docker', 'devops', 'دوكر وCI/CD للمطوّرين', 'Docker & CI/CD for Developers', 'intermediate', 109, 79, [['مفاهيم الحاويات', 'Container concepts'], ['بناء صور Docker', 'Building Docker images'], ['Docker Compose', 'Docker Compose'], ['خطوط التكامل المستمر', 'CI pipelines']]],
        ['ui-ux-design', 'ui-ux', 'تصميم الواجهات وتجربة المستخدم', 'UI/UX Design', 'beginner', 89, null, [['مبادئ التصميم', 'Design principles'], ['بحث المستخدم', 'User research'], ['النماذج الأولية', 'Prototyping'], ['اختبار قابلية الاستخدام', 'Usability testing']]],
        ['graphic-design-basics', 'graphic-design', 'أساسيات التصميم الجرافيكي', 'Graphic Design Basics', 'beginner', 0, null, [['اللون والخطوط', 'Color and typography'], ['التكوين والتوازن', 'Composition and balance'], ['تصميم الشعارات', 'Logo design'], ['تجهيز الملفات للطباعة', 'Preparing files for print']]],
        ['video-editing-premiere', 'video-editing', 'المونتاج وتحرير الفيديو', 'Video Editing Essentials', 'beginner', 79, null, [['واجهة البرنامج والمشروع', 'Interface and project setup'], ['القص والانتقالات', 'Cutting and transitions'], ['الصوت والموسيقى', 'Audio and music'], ['التصدير', 'Exporting']]],
        ['photography-beginners', 'photography', 'التصوير الفوتوغرافي للمبتدئين', 'Photography for Beginners', 'beginner', 49, null, [['الكاميرا والإعدادات', 'Camera and settings'], ['الإضاءة', 'Lighting'], ['التكوين', 'Composition'], ['المعالجة الأساسية', 'Basic editing']]],
        ['project-management-pmp', 'project-management', 'إدارة المشاريع باحتراف', 'Professional Project Management', 'intermediate', 139, 99, [['دورة حياة المشروع', 'The project life cycle'], ['التخطيط والجدولة', 'Planning and scheduling'], ['إدارة المخاطر', 'Risk management'], ['الأجايل وسكرم', 'Agile and Scrum']]],
        ['entrepreneurship-startup', 'entrepreneurship', 'من الفكرة إلى الشركة الناشئة', 'From Idea to Startup', 'beginner', 0, null, [['توليد الفكرة والتحقق منها', 'Generating and validating ideas'], ['نموذج العمل', 'The business model'], ['التمويل', 'Funding'], ['الإطلاق والنمو', 'Launch and growth']]],
        ['digital-marketing-complete', 'digital-marketing', 'التسويق الرقمي المتكامل', 'Complete Digital Marketing', 'beginner', 99, 69, [['استراتيجية التسويق', 'Marketing strategy'], ['الإعلانات المدفوعة', 'Paid advertising'], ['البريد الإلكتروني', 'Email marketing'], ['قياس الأداء', 'Measuring performance']]],
        ['seo-from-scratch', 'seo', 'تحسين محركات البحث من الصفر', 'SEO from Scratch', 'beginner', 59, null, [['كيف تعمل محركات البحث', 'How search engines work'], ['الكلمات المفتاحية', 'Keyword research'], ['تحسين الصفحات', 'On-page SEO'], ['بناء الروابط', 'Link building']]],
        ['accounting-basics', 'accounting', 'مبادئ المحاسبة المالية', 'Financial Accounting Basics', 'beginner', 69, null, [['المعادلة المحاسبية', 'The accounting equation'], ['القيود اليومية', 'Journal entries'], ['القوائم المالية', 'Financial statements'], ['تحليل القوائم', 'Statement analysis']]],
        ['english-conversation', 'english', 'المحادثة بالإنجليزية بثقة', 'English Conversation with Confidence', 'beginner', 0, null, [['التعارف والحياة اليومية', 'Introductions and daily life'], ['السفر والمواصلات', 'Travel and transport'], ['العمل والمقابلات', 'Work and interviews'], ['النطق والاستماع', 'Pronunciation and listening']]],
        ['german-a1', 'german', 'اللغة الألمانية: المستوى A1', 'German Language: Level A1', 'beginner', 59, null, [['الحروف والتحيات', 'Alphabet and greetings'], ['الأعداد والوقت', 'Numbers and time'], ['الجمل الأساسية', 'Basic sentences'], ['المفردات اليومية', 'Everyday vocabulary']]],
        ['calculus-1', 'calculus', 'التفاضل والتكامل 1', 'Calculus 1', 'intermediate', 0, null, [['النهايات', 'Limits'], ['المشتقات', 'Derivatives'], ['تطبيقات المشتقات', 'Applications of derivatives'], ['التكامل', 'Integration']]],
        ['statistics-practical', 'statistics', 'الإحصاء التطبيقي', 'Practical Statistics', 'intermediate', 49, null, [['الإحصاء الوصفي', 'Descriptive statistics'], ['الاحتمالات', 'Probability'], ['اختبارات الفرضيات', 'Hypothesis testing'], ['الانحدار الخطي', 'Linear regression']]],
        ['first-aid-essentials', 'first-aid', 'الإسعافات الأولية الأساسية', 'First Aid Essentials', 'beginner', 0, null, [['تقييم الحالة', 'Assessing the situation'], ['الإنعاش القلبي الرئوي', 'CPR'], ['الجروح والحروق', 'Wounds and burns'], ['الكسور والاختناق', 'Fractures and choking']]],
        ['time-management', 'time-management', 'إدارة الوقت والإنتاجية', 'Time Management & Productivity', 'beginner', 0, null, [['تحديد الأهداف', 'Setting goals'], ['تحديد الأولويات', 'Prioritizing'], ['محاربة التسويف', 'Beating procrastination'], ['بناء العادات', 'Building habits']]],
        ['public-speaking', 'public-speaking', 'فن الإلقاء والتحدث أمام الجمهور', 'The Art of Public Speaking', 'beginner', 39, null, [['التغلّب على الرهبة', 'Overcoming stage fright'], ['بناء العرض', 'Structuring a talk'], ['لغة الجسد والصوت', 'Body language and voice'], ['التعامل مع الأسئلة', 'Handling questions']]],
        ['freelancing-start', 'freelancing', 'ابدأ العمل الحر بنجاح', 'Start Freelancing Successfully', 'beginner', 0, null, [['اختيار المجال والمنصات', 'Choosing a niche and platforms'], ['بناء الملف الشخصي', 'Building your profile'], ['التسعير والتفاوض', 'Pricing and negotiation'], ['إدارة العملاء', 'Managing clients']]],
        ['arabic-calligraphy', 'calligraphy', 'الخط العربي: النسخ والرقعة', 'Arabic Calligraphy: Naskh & Ruq\'ah', 'beginner', 45, null, [['الأدوات والجلسة', 'Tools and posture'], ['الحروف المفردة', 'Individual letters'], ['الربط والتركيب', 'Joining and composition'], ['تمارين تطبيقية', 'Practice exercises']]],
    ];

    public function run(): void
    {
        $this->call(CategorySeeder::class);
        $this->call(InstructorSeeder::class);

        $instructors = User::whereIn('email', array_keys(InstructorSeeder::INSTRUCTORS))->pluck('id', 'email');

        foreach (self::COURSES as [$slug, $catSlug, $ar, $en, $level, $price, $discount, $topics]) {
            if (Course::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            $category = Category::where('slug', $catSlug)->first();
            if (! $category) {
                continue;
            }

            $root = $category->parent_id ? Category::find($category->parent_id) : $category;
            $email = $root ? InstructorSeeder::emailForRoot($root->slug) : null;
            $instructorId = $email ? ($instructors[$email] ?? null) : null;
            if (! $instructorId) {
                continue;
            }

            DB::transaction(function () use ($instructorId, $category, $slug, $ar, $en, $level, $price, $discount, $topics) {
                $course = Course::create([
                    'instructor_id'  => $instructorId,
                    'category_id'    => $category->id,
                    'title_ar'       => $ar,
                    'title_en'       => $en,
                    'slug'           => $slug,
                    'description_ar' => "دورة «{$ar}» تأخذك خطوة بخطوة من الأساسيات إلى التطبيق العملي، بشرح مبسّط وأمثلة واضحة تناسب المستوى.",
                    'description_en' => "\"{$en}\" takes you step by step from the fundamentals to hands-on practice, with clear explanations and examples suited to your level.",
                    'price'          => $price,
                    'discount_price' => $discount,
                    'level'          => $level,
                    'status'         => 'published',
                ]);

                foreach ($topics as [$tAr, $tEn]) {
                    CourseLearningOutcome::create(['course_id' => $course->id, 'outcome_ar' => "إتقان: {$tAr}", 'outcome_en' => "Master: {$tEn}"]);
                }

                CourseRequirement::create(['course_id' => $course->id, 'requirement_ar' => 'حاسوب واتصال بالإنترنت', 'requirement_en' => 'A computer and an internet connection']);
                CourseRequirement::create([
                    'course_id' => $course->id,
                    'requirement_ar' => $level === 'beginner' ? 'لا تحتاج إلى خبرة سابقة' : 'معرفة أساسية بالمجال',
                    'requirement_en' => $level === 'beginner' ? 'No prior experience needed' : 'Basic knowledge of the field',
                ]);

                $sections = [
                    ['المقدمة والأساسيات', 'Introduction & Fundamentals', array_slice($topics, 0, 2)],
                    ['التطبيق العملي', 'Hands-on Practice', array_slice($topics, 2, 2)],
                ];

                $n = 0;
                foreach ($sections as $si => [$sAr, $sEn, $lessons]) {
                    $section = CourseSection::create(['course_id' => $course->id, 'title_ar' => $sAr, 'title_en' => $sEn, 'sort_order' => $si + 1]);

                    foreach ($lessons as $li => [$tAr, $tEn]) {
                        Lesson::create([
                            'section_id'       => $section->id,
                            'title_ar'         => $tAr,
                            'title_en'         => $tEn,
                            'content_type'     => 'article',
                            'content_ar'       => "في هذا الدرس نتناول «{$tAr}» ضمن دورة «{$ar}».\n\nنبدأ بالفكرة الأساسية ثم ننتقل إلى مثال تطبيقي، وننهي بتمرين قصير لترسيخ ما تعلّمته.",
                            'content_en'       => "In this lesson we cover \"{$tEn}\" as part of \"{$en}\".\n\nWe start with the core idea, move to a practical example, and finish with a short exercise to reinforce what you learned.",
                            'duration_minutes' => 8 + (($n * 3 + $li * 2) % 13),
                            'is_free_preview'  => $n === 0,
                            'is_published'     => true,
                            'sort_order'       => $li + 1,
                        ]);
                        $n++;
                    }
                }
            });
        }
    }
}
