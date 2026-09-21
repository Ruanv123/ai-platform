<?php

namespace App\Http\Requests\Flows;

use App\Models\Flow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FlowAiRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:5000'],
            'history' => ['sometimes', 'array', 'max:50'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:5000'],
        ];
    }
}
