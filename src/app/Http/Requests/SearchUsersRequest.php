<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query'    => ['required_without:nickname', 'string', 'min:2'],
            'nickname' => ['required_without:query', 'string', 'min:2'],
            'limit'    => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
