<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'api_key' => [
                'required',
                'string',
                'min:1',
                'max:1000',
            ],
        ];
    }
}
