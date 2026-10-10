<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class CreateSettlementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Supplier\Settlement::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'period_start' => [
                'required',
                'date',
                'before_or_equal:period_end',
            ],
            'period_end' => [
                'required',
                'date',
                'after_or_equal:period_start',
                'before_or_equal:today',
            ],
            'include_pending' => [
                'nullable',
                'boolean',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'period_start.before_or_equal' => 'Period start date must be before or equal to the end date.',
            'period_end.after_or_equal' => 'Period end date must be after or equal to the start date.',
            'period_end.before_or_equal' => 'Settlement period cannot extend into the future.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('include_pending')) {
            $this->merge([
                'include_pending' => filter_var($this->include_pending, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
