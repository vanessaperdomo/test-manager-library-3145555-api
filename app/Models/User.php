<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasUuids;

    protected $table = 'library.user';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'username',
        'password_hash',
        'role',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the password for authentication.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'loaned_by_user_id');
    }
}
