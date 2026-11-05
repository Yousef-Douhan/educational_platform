<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * تصنيفات جاهزة للمنصة (ثنائية اللغة بأيقونات Font Awesome). آمن للتشغيل أكثر من مرة:
 * لا يكرّر ولا يعدّل ما عدّلته يدويًا، ويستعيد التصنيف المحذوف (soft delete) بدل أن يتعارض مع الـslug.
 *
 *   php artisan db:seed --class=CategorySeeder
 */
class CategorySeeder extends Seeder
{
    /** @var array<int, array{0:string,1:string,2:string,3:string,4:array<int, array{0:string,1:string,2:string}>}> */
    private const TREE = [
        ['programming', 'البرمجة', 'Programming', 'fa-solid fa-code', [
            ['web-development', 'تطوير الويب', 'Web Development'], ['mobile-development', 'تطبيقات الموبايل', 'Mobile Development'],
            ['python', 'بايثون', 'Python'], ['java', 'جافا', 'Java'], ['php-laravel', 'PHP ولارافيل', 'PHP & Laravel'],
            ['javascript', 'جافاسكربت', 'JavaScript'], ['databases', 'قواعد البيانات', 'Databases'],
        ]],
        ['data-ai', 'البيانات والذكاء الاصطناعي', 'Data & AI', 'fa-solid fa-brain', [
            ['machine-learning', 'تعلّم الآلة', 'Machine Learning'], ['data-analysis', 'تحليل البيانات', 'Data Analysis'],
            ['deep-learning', 'التعلّم العميق', 'Deep Learning'], ['data-visualization', 'تصوير البيانات', 'Data Visualization'],
        ]],
        ['cybersecurity', 'الأمن السيبراني', 'Cybersecurity', 'fa-solid fa-shield-halved', [
            ['ethical-hacking', 'الاختراق الأخلاقي', 'Ethical Hacking'], ['network-security', 'أمن الشبكات', 'Network Security'],
            ['cryptography', 'التشفير', 'Cryptography'], ['digital-forensics', 'التحقيق الرقمي', 'Digital Forensics'],
        ]],
        ['networks-cloud', 'الشبكات والحوسبة السحابية', 'Networks & Cloud', 'fa-solid fa-network-wired', [
            ['networking', 'الشبكات', 'Networking'], ['cloud-computing', 'الحوسبة السحابية', 'Cloud Computing'],
            ['devops', 'ديف أوبس', 'DevOps'], ['linux', 'لينكس', 'Linux'],
        ]],
        ['engineering', 'الهندسة', 'Engineering', 'fa-solid fa-gears', [
            ['computer-engineering', 'هندسة الحاسوب', 'Computer Engineering'], ['electrical-engineering', 'الهندسة الكهربائية', 'Electrical Engineering'],
            ['civil-engineering', 'الهندسة المدنية', 'Civil Engineering'], ['mechanical-engineering', 'الهندسة الميكانيكية', 'Mechanical Engineering'],
            ['embedded-iot', 'الأنظمة المدمجة وإنترنت الأشياء', 'Embedded & IoT'],
        ]],
        ['design', 'التصميم', 'Design', 'fa-solid fa-palette', [
            ['graphic-design', 'التصميم الجرافيكي', 'Graphic Design'], ['ui-ux', 'تصميم الواجهات وتجربة المستخدم', 'UI/UX Design'],
            ['video-editing', 'المونتاج', 'Video Editing'], ['3d-animation', 'ثلاثي الأبعاد والرسوم المتحركة', '3D & Animation'],
        ]],
        ['photography-media', 'التصوير والإعلام', 'Photography & Media', 'fa-solid fa-camera', [
            ['photography', 'التصوير الفوتوغرافي', 'Photography'], ['filmmaking', 'صناعة الأفلام', 'Filmmaking'],
            ['podcasting', 'البودكاست', 'Podcasting'], ['journalism', 'الصحافة', 'Journalism'],
        ]],
        ['business', 'الأعمال وريادة الأعمال', 'Business & Entrepreneurship', 'fa-solid fa-briefcase', [
            ['entrepreneurship', 'ريادة الأعمال', 'Entrepreneurship'], ['management', 'الإدارة', 'Management'],
            ['project-management', 'إدارة المشاريع', 'Project Management'], ['e-commerce', 'التجارة الإلكترونية', 'E-Commerce'],
        ]],
        ['marketing', 'التسويق', 'Marketing', 'fa-solid fa-bullhorn', [
            ['digital-marketing', 'التسويق الرقمي', 'Digital Marketing'], ['seo', 'تحسين محركات البحث', 'SEO'],
            ['social-media', 'وسائل التواصل الاجتماعي', 'Social Media'], ['content-writing', 'كتابة المحتوى', 'Content Writing'],
        ]],
        ['finance-accounting', 'المالية والمحاسبة', 'Finance & Accounting', 'fa-solid fa-chart-line', [
            ['accounting', 'المحاسبة', 'Accounting'], ['investing', 'الاستثمار', 'Investing'],
            ['banking', 'العمل المصرفي', 'Banking'], ['excel', 'إكسل للأعمال', 'Excel for Business'],
        ]],
        ['languages', 'اللغات', 'Languages', 'fa-solid fa-language', [
            ['english', 'اللغة الإنجليزية', 'English'], ['arabic', 'اللغة العربية', 'Arabic'],
            ['german', 'اللغة الألمانية', 'German'], ['turkish', 'اللغة التركية', 'Turkish'], ['french', 'اللغة الفرنسية', 'French'],
        ]],
        ['science', 'العلوم', 'Science', 'fa-solid fa-flask', [
            ['physics', 'الفيزياء', 'Physics'], ['chemistry', 'الكيمياء', 'Chemistry'],
            ['biology', 'الأحياء', 'Biology'], ['astronomy', 'الفلك', 'Astronomy'],
        ]],
        ['mathematics', 'الرياضيات', 'Mathematics', 'fa-solid fa-square-root-variable', [
            ['algebra', 'الجبر', 'Algebra'], ['calculus', 'التفاضل والتكامل', 'Calculus'],
            ['statistics', 'الإحصاء', 'Statistics'], ['discrete-math', 'الرياضيات المتقطعة', 'Discrete Mathematics'],
        ]],
        ['health-medicine', 'الصحة والطب', 'Health & Medicine', 'fa-solid fa-heart-pulse', [
            ['medicine', 'الطب', 'Medicine'], ['nursing', 'التمريض', 'Nursing'], ['pharmacy', 'الصيدلة', 'Pharmacy'],
            ['first-aid', 'الإسعافات الأولية', 'First Aid'], ['nutrition', 'التغذية', 'Nutrition'],
        ]],
        ['personal-development', 'التطوير الذاتي', 'Personal Development', 'fa-solid fa-lightbulb', [
            ['soft-skills', 'المهارات الشخصية', 'Soft Skills'], ['time-management', 'إدارة الوقت', 'Time Management'],
            ['public-speaking', 'الإلقاء والتحدث', 'Public Speaking'], ['study-skills', 'مهارات الدراسة', 'Study Skills'],
        ]],
        ['education-teaching', 'التعليم والتدريس', 'Education & Teaching', 'fa-solid fa-person-chalkboard', [
            ['teaching-methods', 'طرائق التدريس', 'Teaching Methods'], ['e-learning', 'التعلّم الإلكتروني', 'E-Learning'],
            ['early-childhood', 'الطفولة المبكرة', 'Early Childhood'], ['exam-prep', 'التحضير للاختبارات', 'Exam Preparation'],
        ]],
        ['law-humanities', 'القانون والعلوم الإنسانية', 'Law & Humanities', 'fa-solid fa-scale-balanced', [
            ['law', 'القانون', 'Law'], ['psychology', 'علم النفس', 'Psychology'], ['history', 'التاريخ', 'History'],
            ['philosophy', 'الفلسفة', 'Philosophy'],
        ]],
        ['religion', 'العلوم الشرعية', 'Islamic Studies', 'fa-solid fa-mosque', [
            ['quran', 'القرآن وعلومه', 'Quran Studies'], ['fiqh', 'الفقه', 'Fiqh'], ['hadith', 'الحديث الشريف', 'Hadith'], ['seerah', 'السيرة النبوية', 'Seerah'],
        ]],
        ['arts-hobbies', 'الفنون والهوايات', 'Arts & Hobbies', 'fa-solid fa-music', [
            ['music', 'الموسيقى', 'Music'], ['drawing', 'الرسم', 'Drawing'], ['cooking', 'الطبخ', 'Cooking'],
            ['crafts', 'الأعمال اليدوية', 'Crafts'], ['calligraphy', 'الخط العربي', 'Arabic Calligraphy'],
        ]],
        ['technical-vocational', 'المهن والحِرف التقنية', 'Technical & Vocational', 'fa-solid fa-screwdriver-wrench', [
            ['electronics-repair', 'صيانة الإلكترونيات', 'Electronics Repair'], ['automotive', 'ميكانيكا السيارات', 'Automotive'],
            ['renewable-energy', 'الطاقة المتجددة', 'Renewable Energy'], ['freelancing', 'العمل الحر', 'Freelancing'],
        ]],
    ];

    public function run(): void
    {
        foreach (self::TREE as [$slug, $ar, $en, $icon, $children]) {
            $root = $this->upsert($slug, $ar, $en, $icon, null);

            foreach ($children as [$cSlug, $cAr, $cEn]) {
                $this->upsert($cSlug, $cAr, $cEn, null, $root->id);
            }
        }
    }

    private function upsert(string $slug, string $ar, string $en, ?string $icon, ?int $parentId): Category
    {
        $category = Category::withTrashed()->where('slug', $slug)->first();

        if ($category) {
            if ($category->trashed()) {
                $category->restore();
            }

            return $category;
        }

        return Category::create(['slug' => $slug, 'name_ar' => $ar, 'name_en' => $en, 'icon' => $icon, 'parent_id' => $parentId]);
    }
}
