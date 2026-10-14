<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'library.book';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    const DELETED_AT = 'deleted_at';

    protected $fillable = [
        'id',
        'title',
        'author',
        'isbn',
        'category_id',
        'total_copies',
        'available_copies',
        'cover_key',
        'deleted_at',
    ];

    protected $casts = [
        'total_copies' => 'integer',
        'available_copies' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function loanItems()
    {
        return $this->hasMany(LoanItem::class, 'book_id');
    }
}
