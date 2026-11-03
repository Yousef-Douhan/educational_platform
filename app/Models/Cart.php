<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    public const MAX_ITEMS             = 50;
    public const ABANDONED_AFTER_DAYS  = 7;
    public const STATES                = ['empty', 'active', 'abandoned'];

    protected $fillable = ['user_id'];

    protected $casts = ['created_at' => 'datetime', 'updated_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /** فارغة، أو مهجورة (فيها عناصر ولم تتحرك منذ أيام)، أو نشطة */
    public function state(): string
    {
        $count = $this->items_count ?? $this->items->count();

        return match (true) {
            $count === 0                                                          => 'empty',
            $this->updated_at->lte(now()->subDays(self::ABANDONED_AFTER_DAYS))    => 'abandoned',
            default                                                               => 'active',
        };
    }

    /* ---------- نطاقات الاستعلام ---------- */

    /** عدد العناصر والمجموع الحالي بسعر الدورة وقت الاستعلام */
    public function scopeWithSummary(Builder $query): Builder
    {
        $total = CartItem::query()
            ->join('courses', 'courses.id', '=', 'cart_items.course_id')
            ->whereColumn('cart_items.cart_id', 'carts.id')
            ->selectRaw('COALESCE(SUM(CASE WHEN courses.discount_price IS NOT NULL AND courses.discount_price < courses.price
                                            THEN courses.discount_price ELSE courses.price END), 0)');

        return $query->withCount('items')->addSelect(['total' => $total]);
    }

    public function scopeInState(Builder $query, ?string $state): Builder
    {
        if (! in_array($state, self::STATES, true)) {
            return $query;
        }

        $cutoff = now()->subDays(self::ABANDONED_AFTER_DAYS);

        return match ($state) {
            'empty'     => $query->whereDoesntHave('items'),
            'abandoned' => $query->whereHas('items')->where('carts.updated_at', '<=', $cutoff),
            'active'    => $query->whereHas('items')->where('carts.updated_at', '>', $cutoff),
        };
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->whereHas('user', fn (Builder $u) => $u->withTrashed()
            ->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)));
    }
}
