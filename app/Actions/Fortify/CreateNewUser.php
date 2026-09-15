<?php

namespace App\Actions\Fortify;

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
                        ->whereNull('accepted_at')
                        ->where(fn ($query) => $query
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>=', now()))
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
                    ->with('team')
                    ->where('code', $input['invitation'])
                    ->whereNull('accepted_at')
                    ->lockForUpdate()
                    ->firstOrFail();

                $invitation->team->memberships()->create([
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                ]);
                $invitation->update(['accepted_at' => now()]);
                $user->switchTeam($invitation->team);
            }

            return $user;
        });
    }
}
