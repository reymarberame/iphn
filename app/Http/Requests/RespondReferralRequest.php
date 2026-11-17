<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RespondReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response_note' => ['nullable', 'string', 'max:1000'],
            'coordinator_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
