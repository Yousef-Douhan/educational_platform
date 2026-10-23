<section class="stats-grid">
    @foreach ($cards as $card)
        <article class="stat-card stat-card--{{ $card['tone'] }}">
            <div class="stat-card__icon"><i class="fa-solid {{ $card['icon'] }}"></i></div>
            <div>
                <div class="stat-card__value" data-stat="{{ $card['key'] }}">{{ $stats[$card['key']] }}</div>
                <div class="stat-card__label">{{ $card['label'] }}</div>
            </div>
        </article>
    @endforeach
</section>