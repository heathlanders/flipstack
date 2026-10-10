<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommissionStructureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', \App\Models\Supplier::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'commission_structure_id' => [
                'nullable',
                'uuid',
                Rule::exists('commission_structures', 'id')->where(function ($query) {
                    $query->where('organization_id', $this->user()->current_team_id);
                }),
            ],
            'custom_rate' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'commission_structure_id.exists' => 'The selected commission structure does not exist or does not belong to your organization.',
            'custom_rate.min' => 'Custom commission rate cannot be negative.',
            'custom_rate.max' => 'Custom commission rate cannot exceed 100%.',
        ];
    }
}
