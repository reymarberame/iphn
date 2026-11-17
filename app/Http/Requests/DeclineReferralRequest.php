<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeclineReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decline_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
