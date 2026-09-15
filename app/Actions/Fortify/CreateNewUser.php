<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\AcceptTeamInvitation;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\TeamInvitation;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private AcceptTeamInvitation $acceptTeamInvitation)
    {
        //
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'invitation' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($input): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $invitation = TeamInvitation::query()
                        ->where('code', $value)
                        ->pending()
                        ->first();

                    if (! $invitation || strtolower($invitation->email) !== strtolower((string) ($input['email'] ?? ''))) {
                        $fail(__('Este convite não é válido para este endereço de e-mail.'));
                    }
                },
            ],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            if (is_string($input['invitation'] ?? null)) {
                $invitation = TeamInvitation::query()
                    ->where('code', $input['invitation'])
                    ->firstOrFail();

                $this->acceptTeamInvitation->handle($user, $invitation);
            }

            return $user;
        });
    }
}
