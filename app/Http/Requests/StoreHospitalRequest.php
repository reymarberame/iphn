<?php

namespace App\Http\Requests;

use App\Models\Hospital;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHospitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'municipality_id' => ['required', 'integer', 'exists:municipalities,id'],
            'hospital_code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('hospitals', 'hospital_code')],
            'name' => ['required', 'string', 'max:180'],
            'short_name' => ['nullable', 'string', 'max:80'],
            'facility_type' => ['required', 'string', 'max:50'],
            'ownership_type' => ['required', Rule::in(array_keys(Hospital::OWNERSHIP_LABELS))],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'bed_capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(array_keys(Hospital::STATUS_LABELS))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'services' => ['sometimes', 'array'],
            'services.*' => ['integer', 'exists:services,id'],
        ];
    }
}
