<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if ($this->has('name')) {
            $data['name'] = strip_tags((string)$this->name);
        }
        if ($this->has('email')) {
            $data['email'] = filter_var((string)$this->email, FILTER_SANITIZE_EMAIL);
        }
        if ($this->has('phone')) {
            $data['phone'] = $this->phone ? strip_tags((string)$this->phone) : null;
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'in:new,contacted,qualified,lost'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
