<?php

namespace Database\Factories;

use App\Enums\SiteReadProposalStatus;
use App\Models\SiteRead;
use App\Models\SiteReadProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteReadProposalFactory extends Factory
{
    protected $model = SiteReadProposal::class;

    protected function configure(): static
    {
        return $this->afterMaking(function (SiteReadProposal $proposal): void {
            $proposal->team_id = SiteRead::withoutGlobalScopes()
                ->findOrFail($proposal->site_read_id)
                ->team_id;
        });
    }

    public function definition(): array
    {
        return [
            'site_read_id' => SiteRead::factory(),
            'proposed_fields' => [],
            'status' => SiteReadProposalStatus::Pending,
        ];
    }
}
