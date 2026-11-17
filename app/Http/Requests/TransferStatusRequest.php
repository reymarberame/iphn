<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'departure_at' => ['nullable', 'date'],
            'arrival_at' => ['nullable', 'date'],
            'vehicle_reference' => ['nullable', 'string', 'max:100'],
            'escort_contact' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
