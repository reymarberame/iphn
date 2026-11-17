<?php

namespace App\Http\Requests;

use App\Models\Bed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkBedStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hospital_id' => ['required', 'integer', 'exists:hospitals,id'],
            'bed_ids' => ['required', 'array', 'min:1', 'max:100'],
            'bed_ids.*' => ['integer', 'distinct', 'exists:beds,id'],
            'status' => ['required', Rule::in(Bed::STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
