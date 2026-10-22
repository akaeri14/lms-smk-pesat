<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' || $this->user()?->role === 'guru';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachment.max' => 'Ukuran file lampiran tugas maksimal 10MB (10.240 KB).',
            'attachment.mimes' => 'Format file lampiran harus berupa: pdf, docx, xlsx, png, jpg, jpeg, atau zip.',
            'attachment.file' => 'Lampiran harus berupa file yang valid.',
            'deadline.after' => 'Batas waktu pengumpulan harus lebih besar dari waktu sekarang.',
        ];
    }
}
