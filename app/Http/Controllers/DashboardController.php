<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * لوحة التحكم: أرقام حقيقية فقط، وكل قسم يظهر لمن يملك صلاحية عرض بياناته.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $can  = fn (string $p) => $user->can($p);
        $ar   = app()->isLocale('ar');

        $months = collect(range(11, 0))->map(fn ($i) => Carbon::now()->startOfMonth()->subMonths($i));
        $keys   = $months->map->format('Y-m');
        $from   = $months->first();

        $data = [
            'months' => $months->map(fn (Carbon $m) => [
                'key'   => $m->format('Y-m'),
                'label' => $m->locale($ar ? 'ar' : 'en')->translatedFormat('M'),
                'full'  => $m->locale($ar ? 'ar' : 'en')->translatedFormat('F Y'),
            ])->all(),
            'can' => [
                'users' => $can('users.view'), 'courses' => $can('courses.view'), 'enrollments' => $can('enrollments.view'),
                'orders' => $can('orders.view'), 'receipts' => $can('receipts.view'), 'live' => $can('live-sessions.view'),
            ],
        ];

        $kpi = [];

        /* ---------- المستخدمون ---------- */
        if ($data['can']['users']) {
            $kpi['users'] = [
                'value' => User::count(),
                'delta' => $this->delta(
                    User::where('created_at', '>=', now()->startOfMonth())->count(),
                    User::whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count()
                ),
            ];
        }

        /* ---------- الدورات والتسجيلات ---------- */
        if ($data['can']['courses']) {
            $kpi['courses'] = [
                'value' => Course::where('status', 'published')->count(),
                'sub'   => Course::count(),
            ];
        }

        if ($data['can']['enrollments']) {
            $kpi['enrollments'] = [
                'value' => Enrollment::whereIn('status', Enrollment::ACCESS_STATUSES)->count(),
                'delta' => $this->delta(
                    Enrollment::where('enrolled_at', '>=', now()->startOfMonth())->count(),
                    Enrollment::whereBetween('enrolled_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count()
                ),
            ];

            $series = $this->monthly(Enrollment::query(), 'enrolled_at', $from)->all();
            $data['enrollSeries'] = $keys->map(fn ($k) => (int) ($series[$k] ?? 0))->all();

            $byStatus = Enrollment::select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
            $data['enrollStatus'] = collect(Enrollment::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($byStatus[$s] ?? 0)])->all();

            $data['topCourses'] = Enrollment::query()
                ->select('course_id', DB::raw('count(*) as c'))
                ->whereIn('status', Enrollment::ACCESS_STATUSES)
                ->groupBy('course_id')->orderByDesc('c')->limit(5)
                ->with(['course' => fn ($q) => $q->withTrashed()->select('id', 'title_ar', 'title_en')])
                ->get()
                ->map(fn ($r) => [
                    'id'    => $r->course_id,
                    'title' => $r->course ? ($ar ? $r->course->title_ar : $r->course->title_en) : '—',
                    'count' => (int) $r->c,
                ])->all();
        }

        /* ---------- المبيعات ---------- */
        if ($data['can']['orders']) {
            $revenue = Order::where('status', 'paid')->select('currency', DB::raw('sum(total_amount) as t'))
                ->groupBy('currency')->orderByDesc('t')->pluck('t', 'currency');
            $primary = $revenue->keys()->first() ?? 'USD';

            $thisMonth = (float) Order::where('status', 'paid')->where('currency', $primary)->where('paid_at', '>=', now()->startOfMonth())->sum('total_amount');
            $lastMonth = (float) Order::where('status', 'paid')->where('currency', $primary)
                ->whereBetween('paid_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->sum('total_amount');

            $kpi['revenue'] = [
                'value'    => (float) ($revenue[$primary] ?? 0),
                'currency' => $primary,
                'delta'    => $this->delta($thisMonth, $lastMonth),
                'others'   => $revenue->except($primary)->map(fn ($t, $c) => number_format((float) $t, 2).' '.$c)->values()->all(),
            ];
            $kpi['pendingOrders'] = ['value' => Order::where('status', 'pending')->count()];

            $series = $this->monthly(Order::query()->where('status', 'paid')->where('currency', $primary), 'paid_at', $from, 'total_amount')->all();
            $data['revenueSeries'] = $keys->map(fn ($k) => round((float) ($series[$k] ?? 0), 2))->all();
            $data['currency']      = $primary;

            $byStatus = Order::select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
            $data['orderStatus'] = collect(Order::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($byStatus[$s] ?? 0)])->all();

            $data['recentOrders'] = Order::with(['user' => fn ($q) => $q->withTrashed()->select('id', 'name')])
                ->latest('created_at')->latest('id')->limit(6)->get();
        }

        /* ---------- إشعارات التحويل بانتظار المراجعة ---------- */
        if ($data['can']['receipts']) {
            $kpi['pendingReceipts'] = ['value' => PaymentReceipt::where('status', 'pending')->count()];
            $data['pendingReceipts'] = PaymentReceipt::with(['order:id,order_number,total_amount,currency', 'user' => fn ($q) => $q->withTrashed()->select('id', 'name')])
                ->where('status', 'pending')->oldest()->limit(5)->get();
        }

        /* ---------- الجلسات المباشرة القادمة ---------- */
        if ($data['can']['live']) {
            $data['upcomingSessions'] = LiveSession::with('course:id,title_ar,title_en')
                ->where('status', 'scheduled')->where('ends_at', '>', now())
                ->orderBy('scheduled_at')->limit(5)->get();
            $kpi['live'] = ['value' => LiveSession::where('status', 'scheduled')->where('ends_at', '>', now())->count()];
        }

        $data['kpi'] = $kpi;

        return view('cms.dashboard', $data);
    }

    /** نسبة التغيّر عن الفترة السابقة؛ null إن لم توجد قاعدة للمقارنة. */
    private function delta(float|int $now, float|int $prev): ?float
    {
        if ($prev <= 0) {
            return $now > 0 ? 100.0 : null;
        }

        return round((($now - $prev) / $prev) * 100, 1);
    }

    /** مجموع/عدد شهري (مفتاحه Y-m) بصيغة تعمل على MySQL وSQLite. */
    private function monthly($query, string $column, Carbon $from, ?string $sum = null)
    {
        $driver = DB::connection()->getDriverName();
        $expr   = $driver === 'sqlite' ? "strftime('%Y-%m', {$column})" : "DATE_FORMAT({$column}, '%Y-%m')";
        $agg    = $sum ? "sum({$sum})" : 'count(*)';

        return $query->where($column, '>=', $from)
            ->selectRaw("{$expr} as ym, {$agg} as v")
            ->groupBy('ym')->pluck('v', 'ym');
    }
}
