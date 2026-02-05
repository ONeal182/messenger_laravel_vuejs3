<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkChatReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message_id' => ['nullable', 'integer'],
        ];
    }
}
