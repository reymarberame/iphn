<?php

namespace App\Http\Requests;

use App\Models\Referral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referring_hospital_id' => ['required', 'integer', 'exists:hospitals,id'],
            'receiving_hospital_id' => ['required', 'integer', 'different:referring_hospital_id', 'exists:hospitals,id'],
            'patient_reference' => ['required', 'string', 'max:60'],
            'patient_name' => ['required', 'string', 'max:120'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'patient_sex' => ['nullable', Rule::in(['female', 'male', 'other', 'undisclosed'])],
            'urgency' => ['required', Rule::in(array_keys(Referral::URGENCY_LABELS))],
            'requested_service_id' => ['nullable', 'integer', 'exists:services,id'],
            'referral_reason' => ['required', 'string', 'min:10', 'max:2000'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'submit' => ['sometimes', 'boolean'],
            'documents' => ['sometimes', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'receiving_hospital_id.different' => 'Select a receiving hospital other than your own.',
            'referral_reason.min' => 'Provide enough clinical context to coordinate the transfer.',
        ];
    }
}
