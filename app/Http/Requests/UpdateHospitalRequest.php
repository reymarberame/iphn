<?php

namespace App\Http\Requests;

use App\Models\Hospital;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHospitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hospital = $this->route('hospital');

        return [
            'municipality_id' => ['sometimes', 'integer', 'exists:municipalities,id'],
            'hospital_code' => ['sometimes', 'string', 'max:30', 'alpha_dash', Rule::unique('hospitals', 'hospital_code')->ignore($hospital?->id)],
            'name' => ['sometimes', 'string', 'max:180'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'facility_type' => ['sometimes', 'string', 'max:50'],
            'ownership_type' => ['sometimes', Rule::in(array_keys(Hospital::OWNERSHIP_LABELS))],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:120'],
            'bed_capacity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['sometimes', Rule::in(array_keys(Hospital::STATUS_LABELS))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'services' => ['sometimes', 'array'],
            'services.*' => ['integer', 'exists:services,id'],
        ];
    }
}
