<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Shop;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PurchaseAttributionFactory extends Factory
{
    public function definition(): array
    {
        $team = Team::factory();
        $agent = Agent::factory()->for($team);

        return [
            'team_id' => $team,
            'shop_id' => Shop::factory()->for($team),
            'contact_id' => Contact::factory()->for($team),
            'agent_id' => $agent,
            'channel_id' => Channel::factory()->for($team)->for($agent),
            'token_hash' => hash('sha256', Str::random(64)),
        ];
    }
}
