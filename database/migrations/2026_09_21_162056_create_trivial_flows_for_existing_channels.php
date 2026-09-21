<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $channels = DB::table('channels')
            ->whereNotNull('agent_id')
            ->get(['id', 'team_id', 'agent_id']);

        foreach ($channels as $channel) {
            $flowId = (string) Str::uuid7();
            $versionId = (string) Str::uuid7();
            $dsl = [
                'version' => 1,
                'nodes' => [[
                    'id' => 'agent',
                    'type' => 'deliver_agent',
                    'config' => [
                        'agent_id' => $channel->agent_id,
                    ],
                ]],
                'edges' => [],
            ];

            DB::table('flows')->insert([
                'id' => $flowId,
                'team_id' => $channel->team_id,
                'channel_id' => $channel->id,
                'name' => 'Atendimento padrão',
                'status' => 'published',
                'draft_dsl' => json_encode($dsl, JSON_THROW_ON_ERROR),
                'trigger_type' => 'message',
                'trigger_event' => null,
                'trigger_token_hash' => null,
                'collision_policy' => 'ignore',
                'allow_unknown_contact' => false,
                'is_trivial' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('flow_versions')->insert([
                'id' => $versionId,
                'team_id' => $channel->team_id,
                'flow_id' => $flowId,
                'number' => 1,
                'dsl' => json_encode($dsl, JSON_THROW_ON_ERROR),
                'category' => 'conversation',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('flows')->where('is_trivial', true)->pluck('id');
        DB::table('flows')->whereIn('id', $ids)->delete();
    }
};
