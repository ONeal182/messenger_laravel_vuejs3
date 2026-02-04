<?php

namespace App\Models;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'user_id',
        'body',
        'forward_from_message_id',
        'forward_from_user_id',
        'forward_from_chat_id',
    ];

    /**
     * Decrypt body when reading.
     */
    public function getBodyAttribute($value)
    {
        if (! $value) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // fallback for legacy/plaintext values
            return $value;
        }
    }

    /**
     * Encrypt body when setting.
     */
    public function setBodyAttribute($value): void
    {
        $this->attributes['body'] = $value
            ? Crypt::encryptString($value)
            : $value;
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function forwardFromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forward_from_user_id');
    }

    public function forwardFromChat(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'forward_from_chat_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
}
