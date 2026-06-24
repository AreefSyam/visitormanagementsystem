<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['nullable', 'email', 'max:255'],
            'phone'     => ['required', 'string', 'max:20'],
            'company'   => ['nullable', 'string', 'max:255'],
            'id_type'   => ['required', Rule::in(['ic', 'passport', 'driving_license', 'other'])],
            'id_number' => ['required', 'string', 'max:50'],
            'photo'     => ['nullable', 'image', 'max:2048'],
        ];
    }
}
