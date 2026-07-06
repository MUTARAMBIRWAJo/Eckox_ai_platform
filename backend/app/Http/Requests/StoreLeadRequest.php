<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strip_tags((string)$this->name),
            'email' => filter_var((string)$this->email, FILTER_SANITIZE_EMAIL),
            'phone' => $this->phone ? strip_tags((string)$this->phone) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'string', 'email', 'max:255', 'unique:leads,email'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'status'         => ['nullable', 'string', 'in:new,contacted,qualified,lost'],
            'source_channel' => ['nullable', 'string', 'max:50'],
            'region'         => ['nullable', 'string', 'in:africa,europe,middle_east,americas,asia'],
            'language'       => ['nullable', 'string', 'max:10'],
            'assigned_to'    => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A lead with this email address already exists.',
        ];
    }
}

