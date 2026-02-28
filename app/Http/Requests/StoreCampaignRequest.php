<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    /**
     * Authorize request
     */
    public function authorize(): bool
    {
        // Only authenticated users with client account can create campaigns
        return auth()->check() && auth()->user()->client !== null;
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'objective'  => 'nullable|string|max:255',
            'budget'     => 'required|numeric|min:0',
            'started_at' => 'nullable|date',
            'ended_at'   => 'nullable|date|after_or_equal:started_at',
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.required'  => 'Campaign name is required.',
            'budget.required'=> 'Campaign budget is required.',
            'budget.numeric' => 'Budget must be a valid number.',
            'ended_at.after_or_equal' => 'End date must be after start date.',
        ];
    }
}