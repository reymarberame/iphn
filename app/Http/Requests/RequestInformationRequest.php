<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
