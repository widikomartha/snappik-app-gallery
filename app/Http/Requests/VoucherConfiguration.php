<?php

namespace App\Http\Requests;

use App\Models\Voucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherConfiguration extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Voucher::normalize($this->input('code'))]);
        }
    }

    public function rules(): array
    {
        $editing = $this->route('voucher') !== null;

        return [
            'code' => $editing ? ['prohibited'] : ['required', 'string', 'max:64', 'regex:/\A[^\p{C}]+\z/u', Rule::unique('vouchers', 'code')],
            'expected_version' => $editing ? ['required', 'integer', 'min:1'] : ['prohibited'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'integer', 'min:1', 'max:'.($this->input('discount_type') === 'percentage' ? '99' : '9007199254740991')],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:starts_at'],
            'total_limit' => ['nullable', 'integer', 'min:1', 'max:9007199254740991'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:9007199254740991'],
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        // SecurityHeaders intentionally suppresses Referer. Inertia GETs do not
        // update Laravel's previous-URL session value; use the actual form route.
        return $this->route('voucher')
            ? route('admin.vouchers.show', $this->route('voucher'))
            : route('admin.vouchers.create');
    }
}
