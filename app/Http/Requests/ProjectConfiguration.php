<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectConfiguration extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'photographer';
    }

    public function rules(): array
    {
        $rules = ['name' => 'required|string|max:255', 'contact' => 'nullable|string|max:255',
            'allowance' => 'required|integer|min:1', 'version' => $this->isMethod('post') ? 'sometimes|integer|min:1' : 'required|integer|min:1'];
        foreach (['selection', 'delivery'] as $type) {
            $rules[$type.'_source'] = ($type === 'selection' ? 'required' : 'nullable').'|string|max:2048';
            $rules[$type.'_password'] = ['nullable', 'string', 'max:72', function ($attribute, $value, $fail) {
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('Kata sandi galeri maksimal 72 byte dan tidak boleh mengandung karakter NUL.');
                }
            }];
            $rules[$type.'_remove_password'] = 'sometimes|boolean';
            // Require an explicit offset; never infer a browser/server timezone.
            $rules[$type.'_deadline'] = ['nullable', 'date', 'regex:/T.*(?:Z|[+-]\d{2}:\d{2})$/'];
        }

        return $rules;
    }
}
