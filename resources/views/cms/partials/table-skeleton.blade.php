<div class="table-skeleton" aria-hidden="true">
    @for ($i = 0; $i < ($rows ?? 5); $i++)
        <div class="skeleton-row">
            <span class="skeleton sk-circle"></span>
            <span class="skeleton sk-line sk-w-30"></span>
            <span class="skeleton sk-line sk-w-20"></span>
            <span class="skeleton sk-line sk-w-20"></span>
        </div>
    @endfor
</div>