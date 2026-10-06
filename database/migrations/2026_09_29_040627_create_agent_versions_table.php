<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('objective', 32);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'number']);
            $table->index(['agent_id', 'published_at']);
        });

        DB::table('agents')
            ->select(['id', 'team_id', 'objective'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $agents): void {
                $timestamp = now();
                $versions = $agents->map(fn (object $agent): array => [
                    'id' => (string) Str::uuid(),
                    'team_id' => $agent->team_id,
                    'agent_id' => $agent->id,
                    'number' => 1,
                    'objective' => $agent->objective,
                    'published_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all();

                if ($versions !== []) {
                    DB::table('agent_versions')->insert($versions);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_versions');
    }
};
