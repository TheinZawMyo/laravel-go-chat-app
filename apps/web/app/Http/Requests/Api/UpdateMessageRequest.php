<?php

namespace App\Http\Requests\Api;

use App\Services\MessageService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.MessageService::MAX_BODY_LENGTH],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge([
                'body' => trim($this->input('body')),
            ]);
        }
    }
}
