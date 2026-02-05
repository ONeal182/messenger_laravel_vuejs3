<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
