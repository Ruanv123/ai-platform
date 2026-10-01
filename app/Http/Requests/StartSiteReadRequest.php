<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StartSiteReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->company();

        return $user !== null && $team instanceof Team && $user->can('update', $team);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['website' => $this->user()?->company()?->website]);
    }

    public function rules(): array
    {
        return [
            'website' => ['required', 'string', 'url', 'max:2048'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $website = $this->input('website');

            if ($validator->errors()->has('website') || ! is_string($website)) {
                return;
            }

            $parts = parse_url($website);
            $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
            $host = is_array($parts) ? strtolower(rtrim((string) ($parts['host'] ?? ''), '.')) : '';

            if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
                $validator->errors()->add('website', 'Informe um site válido começando por http:// ou https://.');

                return;
            }

            $host = preg_replace('/^www\./', '', $host) ?? $host;

            foreach (['instagram.com', 'wa.me', 'api.whatsapp.com', 'whatsapp.com'] as $blockedHost) {
                if ($host === $blockedHost || str_ends_with($host, '.'.$blockedHost)) {
                    $validator->errors()->add(
                        'website',
                        'Instagram e endereços de WhatsApp não podem ser lidos. Informe o site da Empresa ou preencha os dados manualmente.',
                    );

                    return;
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'website.required' => 'Informe o site da Empresa antes de iniciar a Leitura.',
            'website.url' => 'Informe um site válido começando por http:// ou https://.',
            'website.max' => 'O endereço do site deve ter no máximo 2.048 caracteres.',
        ];
    }
}
