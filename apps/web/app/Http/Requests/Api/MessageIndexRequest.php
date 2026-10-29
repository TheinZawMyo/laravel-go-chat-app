<?php

namespace App\Http\Requests\Api;

use App\Services\MessageService;
use Illuminate\Foundation\Http\FormRequest;

class MessageIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'before_seq' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.MessageService::MAX_LIMIT],
        ];
    }
}
