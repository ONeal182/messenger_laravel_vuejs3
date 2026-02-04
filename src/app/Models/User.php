<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens; // 🔐 Sanctum
    use HasFactory;
    use Notifiable;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'nickname',
        'name',
        'last_name',
        'middle_name',
        'birth_date',
        'avatar_path',
        'avatar_thumb_path',
        'email',
        'password',
        'last_seen_at',
    ];

    /**
     * Hidden attributes for JSON
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting (Laravel 12 way)
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at'      => 'datetime',
            'birth_date'        => 'date',
            'password'          => 'hashed',
        ];
    }

    /* =========================
     |        RELATIONS
     |=========================*/

    /**
     * Chats where user is a participant
     */
    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(Chat::class)
            ->withPivot('role', 'joined_at', 'last_read_message_id', 'last_seen_at')->withTimestamps();
    }
    

    /**
     * Messages sent by user
     */

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
    


}
