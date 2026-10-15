<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'email',
        'password',
        'name',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    public function getHomeRouteAttribute()
    {
        switch ($this->role) {
            case 'admin':
                return route('admin.dashboard');
            case 'karyawan':
                return route('kasir.index');
            default:
                return route('login');
        }
    }
}
