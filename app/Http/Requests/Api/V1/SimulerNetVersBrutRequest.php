<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\PayrollValidation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SimulerNetVersBrutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PayrollValidation::netVersBrutRules();
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        $firstMessage = $errors->first();

        throw new HttpResponseException(response()->json([
            'type' => 'about:blank',
            'title' => 'Unprocessable Content',
            'status' => 422,
            'detail' => $firstMessage,
            'errors' => $errors->toArray(),
        ], 422));
    }
}
