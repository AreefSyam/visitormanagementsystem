<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:hosts,email'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'department' => ['required', 'string', 'max:255'],
            'position'   => ['nullable', 'string', 'max:255'],
            'is_active'  => ['boolean'],
        ];
    }
}
