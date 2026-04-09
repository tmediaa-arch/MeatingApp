<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        // policy check در controller
        return true;
    }

    public function rules(): array
    {
        return [
            'progress_percent' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'progress_percent.required' => 'درصد پیشرفت الزامی است.',
            'progress_percent.min' => 'درصد پیشرفت نمی‌تواند منفی باشد.',
            'progress_percent.max' => 'درصد پیشرفت نمی‌تواند بیشتر از ۱۰۰ باشد.',
        ];
    }
}
