<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['password' => 'hashed', 'active' => 'boolean'];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Signs the user out of every device (database sessions only), optionally keeping one session alive.
    public function endSessions(?string $except = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }
        DB::table(config('session.table', 'sessions'))->where('user_id', $this->id)
            ->when($except, fn ($query) => $query->where('id', '!=', $except))->delete();
    }
}
