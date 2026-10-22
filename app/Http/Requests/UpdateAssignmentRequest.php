<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('assignment')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'deadline' => ['required', 'date'],
            'max_score' => ['required', 'integer', 'min:1', 'max:10000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,docx,xlsx,png,jpg,jpeg,zip', 'max:10240'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachment.max' => 'Ukuran file lampiran tugas maksimal 10MB (10.240 KB).',
            'attachment.mimes' => 'Format file lampiran harus berupa: pdf, docx, xlsx, png, jpg, jpeg, atau zip.',
            'attachment.file' => 'Lampiran harus berupa file yang valid.',
        ];
    }
}
