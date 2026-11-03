@php
    use App\Support\Loc;
    $hasDiscount = $course->discount_price !== null && (float) $course->discount_price < (float) $course->price;
    $currency = config('app.currency', 'USD');
@endphp
<a href="{{ route('student.courses.show', $course->slug) }}"
   class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-xl">
    <div class="relative aspect-video overflow-hidden bg-gradient-to-br from-indigo-100 to-sky-100">
        @if ($course->image_src)
            <img src="{{ $course->image_src }}" alt="" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="flex h-full items-center justify-center text-4xl text-indigo-300"><i class="fa-solid fa-book-open"></i></div>
        @endif
        @if ($course->category)
            <span class="absolute start-3 top-3 rounded-full bg-white/95 px-3 py-1 text-xs font-bold text-indigo-700 shadow">{{ Loc::pick($course->category->name_ar, $course->category->name_en) }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-2 p-4">
        <h3 class="line-clamp-2 min-h-[3rem] font-bold leading-6 text-slate-900 group-hover:text-indigo-700">{{ Loc::pick($course->title_ar, $course->title_en) }}</h3>
        <p class="flex items-center gap-1.5 text-xs text-slate-500"><i class="fa-regular fa-user"></i> {{ $course->instructor?->name }}</p>
        <div class="flex items-center gap-3 text-xs text-slate-500">
            <span><i class="fa-solid fa-signal me-1"></i>{{ __('student.levels.' . $course->level) }}</span>
            @isset($course->lessons_count)
                <span><i class="fa-regular fa-circle-play me-1"></i>{{ trans_choice('student.lessons_count', $course->lessons_count, ['count' => $course->lessons_count]) }}</span>
            @endisset
        </div>
        <div class="mt-auto flex items-center justify-between border-t border-slate-100 pt-3">
            <div class="font-extrabold text-slate-900">
                @if ($course->is_free)
                    <span class="text-emerald-600">{{ __('student.free') }}</span>
                @else
                    {{ number_format((float) $course->final_price, 2) }} <span class="text-xs font-semibold">{{ $currency }}</span>
                    @if ($hasDiscount)
                        <span class="ms-1 text-xs font-normal text-slate-400 line-through">{{ number_format((float) $course->price, 2) }}</span>
                    @endif
                @endif
            </div>
            <span class="text-xs font-bold text-indigo-700">{{ __('student.course.details') }} <i class="fa-solid fa-arrow-left rtl:inline ltr:hidden"></i><i class="fa-solid fa-arrow-right ltr:inline rtl:hidden"></i></span>
        </div>
    </div>
</a>
