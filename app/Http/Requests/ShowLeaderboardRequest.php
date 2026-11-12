<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ShowLeaderboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
