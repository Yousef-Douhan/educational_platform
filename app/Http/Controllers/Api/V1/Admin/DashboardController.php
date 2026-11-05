<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ملخص لوحة الإدارة. كل قسم يظهر فقط لمن يملك صلاحية عرضه، وأرقام الدورات/التسجيلات/الشهادات
 * مقيّدة بنطاق visibleTo (المدرّس يرى دوراته فقط).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $u = $request->user();
        $out = [];

        if ($u->can('courses.view')) {
            $out['courses'] = [
                'total'     => Course::query()->visibleTo($u)->count(),
                'published' => Course::query()->visibleTo($u)->where('status', 'published')->count(),
            ];
        }

        if ($u->can('enrollments.view')) {
            $out['enrollments'] = [
                'total'      => Enrollment::query()->visibleTo($u)->count(),
                'active'     => Enrollment::query()->visibleTo($u)->where('status', 'active')->count(),
                'this_month' => Enrollment::query()->visibleTo($u)->where('enrolled_at', '>=', now()->startOfMonth())->count(),
            ];
        }

        if ($u->can('certificates.view')) {
            $out['certificates'] = [
                'total'      => Certificate::query()->visibleTo($u)->count(),
                'this_month' => Certificate::query()->visibleTo($u)->where('issued_at', '>=', now()->startOfMonth())->count(),
            ];
        }

        if ($u->can('users.view')) {
            $out['users'] = [
                'total'      => User::count(),
                'this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            ];
        }

        if ($u->can('orders.view')) {
            $out['orders'] = [
                'total'         => Order::count(),
                'pending'       => Order::where('status', 'pending')->count(),
                'paid'          => Order::where('status', 'paid')->count(),
                'revenue'       => number_format((float) Order::where('status', 'paid')->sum('total_amount'), 2, '.', ''),
                'revenue_month' => number_format((float) Order::where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('total_amount'), 2, '.', ''),
            ];
        }

        if ($u->can('receipts.view')) {
            $out['receipts'] = ['pending' => PaymentReceipt::where('status', 'pending')->count()];
        }

        return response()->json(['data' => $out]);
    }
}
