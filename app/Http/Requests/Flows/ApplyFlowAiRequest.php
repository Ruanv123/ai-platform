<?php

namespace App\Http\Requests\Flows;

use App\Models\Flow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApplyFlowAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $flow = $this->route('flow');

        return $flow instanceof Flow && Gate::allows('update', $flow);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'dsl' => ['required', 'array'],
        ];
    }
}
