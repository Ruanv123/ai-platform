<?php

namespace App\Http\Requests\Teams;

use App\Http\Requests\Teams\Concerns\HasBusinessHoursValidation;
use App\Http\Requests\Teams\Concerns\HasCompanyValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveCompanyRequest extends FormRequest
{
    use HasBusinessHoursValidation, HasCompanyValidation;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->company() === null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->companyRules('required'),
            ...$this->businessHoursRules(),
        ];
    }

    /**
     * Get custom validation messages in Brazilian Portuguese.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->companyMessages(),
            ...$this->businessHoursMessages(),
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->companyAttributes(),
            ...$this->businessHoursAttributes(),
        ];
    }
}
