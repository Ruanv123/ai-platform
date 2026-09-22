<?php

namespace App\Http\Requests\Flows;

use App\Models\Flow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PlaygroundFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $flow = $this->route('flow');

        return $flow instanceof Flow && Gate::allows('view', $flow);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:5000'],
            'variables' => ['sometimes', 'array'],
            'payload' => ['sometimes', 'array'],
        ];
    }
}
