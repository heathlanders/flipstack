<?php

namespace App\Http\Requests\Admin;

use App\Models\AI\AiCategoryPrompt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiPromptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->hasPermission('ai_prompts.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'category_slug' => [
                'required',
                'string',
                'max:100',
            ],
            'prompt_type' => [
                'required',
                'string',
                Rule::in(array_keys(AiCategoryPrompt::PROMPT_TYPES)),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'prompt_content' => [
                'required',
                'string',
                'min:50',
            ],
            'variable_definitions' => [
                'nullable',
                'array',
            ],
            'variable_definitions.*' => [
                'string',
                'max:500',
            ],
            'is_active' => [
                'boolean',
            ],
            'priority' => [
                'nullable',
                'integer',
                'min:0',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_slug.required' => 'Please select a category.',
            'prompt_type.required' => 'Please select a prompt type.',
            'prompt_type.in' => 'Invalid prompt type selected.',
            'name.required' => 'Prompt name is required.',
            'name.max' => 'Prompt name cannot exceed 100 characters.',
            'prompt_content.required' => 'Prompt content is required.',
            'prompt_content.min' => 'Prompt content must be at least 50 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->is_active ?? true,
            'priority' => $this->priority ?? 0,
        ]);
    }
}
