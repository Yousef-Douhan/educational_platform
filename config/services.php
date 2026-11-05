<?php

return [

    /*
    | تسجيل الدخول بـGoogle (OAuth 2.0). اترك المعرّف فارغًا لإخفاء الزر.
    | redirect: اتركه فارغًا ليُستنتج من المسار، أو ضع العنوان نفسه المسجّل في Google Cloud Console.
    */
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

];
