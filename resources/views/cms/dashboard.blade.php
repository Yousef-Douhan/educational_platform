@extends('cms.parent')

@section('title', 'Dashboard')

@section('main-content')
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-500">Total Users</span>
                    <span class="rounded-xl bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-700">+12.5%</span>
                </div>
                <p class="mt-4 text-3xl font-bold text-slate-900">1,284</p>
                <p class="mt-2 text-sm text-slate-500">Compared to last month</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-500">Active Sessions</span>
                    <span class="rounded-xl bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">+8.1%</span>
                </div>
                <p class="mt-4 text-3xl font-bold text-slate-900">742</p>
                <p class="mt-2 text-sm text-slate-500">Live platform traffic</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-500">Conversion</span>
                    <span class="rounded-xl bg-violet-100 px-2 py-1 text-xs font-semibold text-violet-700">+3.8%</span>
                </div>
                <p class="mt-4 text-3xl font-bold text-slate-900">6.4%</p>
                <p class="mt-2 text-sm text-slate-500">This quarter overview</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.7fr_1fr]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">Performance Trend</h3>
                    <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600">This year</button>
                </div>
                <div class="flex h-64 items-end gap-3">
                    @foreach ([45, 58, 62, 54, 78, 74, 90, 82, 96, 88, 110, 106] as $bar)
                        <div class="flex-1 rounded-t-2xl bg-gradient-to-t from-sky-500 to-cyan-300" style="height: {{ $bar }}%"></div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Quick Actions</h3>
                <div class="mt-5 space-y-3">
                    <a href="{{ route('cms.admin.users.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm font-medium text-slate-700 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700">
                        <span>Manage users</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <a href="#" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm font-medium text-slate-700 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700">
                        <span>View analytics</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <a href="#" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm font-medium text-slate-700 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700">
                        <span>Reports center</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
