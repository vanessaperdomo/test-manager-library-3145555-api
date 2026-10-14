<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

class Loan extends Model
{
    use HasUuids;

    protected $table = 'library.loan';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'member_id',
        'loaned_by_user_id',
        'loaned_at',
    ];

    protected $casts = [
        'loaned_at' => 'datetime',
    ];

    protected $appends = ['status'];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'loaned_by_user_id');
    }

    public function items()
    {
        return $this->hasMany(LoanItem::class, 'loan_id');
    }

    /**
     * Calculated loan status according to spec.md & api-contract.md:
     * - 'closed'  : all items returned
     * - 'overdue' : any unreturned item with due_at < now
     * - 'open'    : has unreturned items and none overdue
     */
    public function getStatusAttribute(): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        if ($items->isEmpty()) {
            return 'closed';
        }

        $allReturned = $items->every(fn($item) => $item->returned_at !== null);
        if ($allReturned) {
            return 'closed';
        }

        $hasOverdue = $items->contains(function ($item) {
            return $item->returned_at === null && Carbon::parse($item->due_at)->isPast();
        });

        if ($hasOverdue) {
            return 'overdue';
        }

        return 'open';
    }
}
