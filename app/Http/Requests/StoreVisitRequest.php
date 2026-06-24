<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitor_id'   => ['required', 'exists:visitors,id'],
            'host_id'      => ['required', 'exists:hosts,id'],
            'purpose'      => ['required', 'string', 'max:255'],
            'badge_number' => ['nullable', 'string', 'max:50'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ];
    }
}
