<?php

namespace App\Http\Requests\Api;

use App\Enums\MessageType;
use App\Services\MessageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_message_id' => ['required', 'uuid'],
            'type' => ['sometimes', 'string', Rule::in([MessageType::Text->value])],
            'body' => ['required', 'string', 'max:'.MessageService::MAX_BODY_LENGTH],
            'reply_to_message_id' => ['nullable', 'uuid', 'exists:messages,id'],
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
