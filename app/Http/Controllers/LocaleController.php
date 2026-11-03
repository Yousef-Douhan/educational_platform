<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);

        Cookie::queue(Cookie::make(SetLocale::COOKIE, $locale, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'Lax'));

        // نعود للصفحة السابقة فقط إن كانت من نفس الموقع، منعًا لإعادة التوجيه المفتوحة
        $previous = url()->previous();
        $target   = parse_url($previous, PHP_URL_HOST) === $request->getHost() ? $previous : route('student.home');

        return redirect()->to($target);
    }
}
