<?php

namespace App\Http\Requests;

use App\Models\Bed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BedStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Bed::STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
            'expected_updated_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Choose a valid bed status.',
            'reason.max' => 'Keep the reason under 255 characters.',
        ];
    }
}
