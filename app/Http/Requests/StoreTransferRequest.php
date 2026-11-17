<?php

namespace App\Http\Requests;

use App\Models\TransferRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transport_type' => ['required', Rule::in(array_keys(TransferRecord::TRANSPORT_LABELS))],
            'vehicle_reference' => ['nullable', 'string', 'max:100'],
            'escort_contact' => ['nullable', 'string', 'max:100'],
            'departure_at' => ['nullable', 'date'],
            'estimated_arrival_at' => ['nullable', 'date', 'after:departure_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
