<?php

return [
    /*
    | إشعارات البوابات (webhooks). لا يُقبل أي إشعار بلا توقيع صحيح، ولا يُقبل أي إشعار
    | إذا لم يُضبط سرّ البوابة في .env (يُرفض بـ 503 بدل القبول بلا تحقق).
    */
    'webhooks' => [
        // أقصى فارق بين وقت التوقيع والوقت الحالي (ثوانٍ): يمنع إعادة إرسال إشعار قديم مسروق
        'tolerance' => 300,
        // أكبر جسم مقبول (بايت)
        'max_bytes' => 65536,

        'gateways' => [
            'stripe' => [
                'secret' => env('STRIPE_WEBHOOK_SECRET'),
                'header' => 'Stripe-Signature',
            ],
        ],
    ],

    /*
    | Stripe Checkout (صفحة دفع مستضافة): بيانات البطاقة لا تمرّ بخادمنا أبدًا.
    | السرّ من لوحة Stripe (sk_...) ويوضع في .env فقط. بلا سرّ لا يظهر زر الدفع.
    */
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'api'    => 'https://api.stripe.com/v1',
    ],

    /*
    | الدفع بالتحويل مع رفع صورة الإشعار. الطريقة لا تظهر للطالب إلا إذا وُضع رقم حسابها في .env.
    | label_*: الاسم الظاهر، account: رقم المحفظة/الحساب، holder: اسم صاحب الحساب.
    */
    'manual' => [
        'methods' => [
            'palpay' => [
                'label_ar' => 'بال باي', 'label_en' => 'PalPay', 'icon' => 'fa-wallet',
                'account' => env('PALPAY_ACCOUNT'), 'holder' => env('PALPAY_HOLDER'),
            ],
            'jawwal_pay' => [
                'label_ar' => 'جوال بي', 'label_en' => 'Jawwal Pay', 'icon' => 'fa-mobile-screen',
                'account' => env('JAWWAL_PAY_ACCOUNT'), 'holder' => env('JAWWAL_PAY_HOLDER'),
            ],
            'bank_of_palestine' => [
                'label_ar' => 'بنك فلسطين', 'label_en' => 'Bank of Palestine', 'icon' => 'fa-building-columns',
                'account' => env('BOP_ACCOUNT'), 'iban' => env('BOP_IBAN'), 'holder' => env('BOP_HOLDER'),
            ],
        ],
    ],
];
