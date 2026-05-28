<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Stamp;
use App\Models\StampCorrectionRequest;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /* ユーザーが持つ勤怠一覧 */
    public function stamps()
    {
        return $this->hasMany(Stamp::class);
    }

    /* ユーザーが申請した修正申請一覧 */
    public function correctionRequests()
    {
        return $this->hasMany(StampCorrectionRequest::class);
    }

    /* ユーザーが承認した修正申請一覧（管理者用） */
    public function approvedRequests()
    {
        return $this->hasMany(StampCorrectionRequest::class, 'approved_by');
    }
}
