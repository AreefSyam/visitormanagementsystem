<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hostId = $this->route('host')->id;

        return [
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', Rule::unique('hosts', 'email')->ignore($hostId)],
            'phone'      => ['nullable', 'string', 'max:20'],
            'department' => ['required', 'string', 'max:255'],
            'position'   => ['nullable', 'string', 'max:255'],
            'is_active'  => ['boolean'],
        ];
    }
}
