<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answer' => [
                'required',
                'string',
                'min:' . config('evaluation.min_answer_length'),
                'max:' . config('evaluation.max_answer_length'),
            ],
        ];
    }
}
