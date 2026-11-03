<?php

namespace App\Support;

/** يختار النص العربي أو الإنجليزي بحسب لغة الصفحة، ويرجع للآخر إن كان الأول فارغًا */
final class Loc
{
    public static function pick(?string $ar, ?string $en): string
    {
        $ar = (string) $ar;
        $en = (string) $en;

        return app()->isLocale('ar') ? ($ar !== '' ? $ar : $en) : ($en !== '' ? $en : $ar);
    }
}
