<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** لوحة التحكم للموظفين فقط (من يملك صلاحية). الطالب يُحوَّل لواجهته */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isStaff()) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 403);

        return redirect()->route('student.courses.mine');
    }
}
