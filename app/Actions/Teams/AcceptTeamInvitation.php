<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptTeamInvitation
{
    /**
     * Accept an invitation and attach the user to its Empresa.
     */
    public function handle(User $user, TeamInvitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation): void {
            $lockedInvitation = TeamInvitation::query()
                ->with('team')
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedInvitation->isPending()) {
                throw ValidationException::withMessages([
                    'invitation' => 'Este convite não é mais válido.',
                ]);
            }

            if ($user->company() !== null) {
                throw ValidationException::withMessages([
                    'invitation' => 'Esta pessoa já pertence a uma Empresa.',
                ]);
            }

            if (mb_strtolower($lockedInvitation->email) !== mb_strtolower($user->email)) {
                throw ValidationException::withMessages([
                    'invitation' => 'Este convite foi enviado para outro endereço de e-mail.',
                ]);
            }

            $lockedInvitation->team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => TeamRole::from($lockedInvitation->role->value)],
            );

            $lockedInvitation->update(['accepted_at' => now()]);
            $user->switchTeam($lockedInvitation->team);
        });
    }
}
