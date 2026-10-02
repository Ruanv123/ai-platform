<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('website');
            $table->text('resolved_website')->nullable();
            $table->string('status')->default('in_progress');
            $table->unsignedInteger('found_pages_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'started_at']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('site_read_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_read_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('title');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['site_read_id', 'url']);
            $table->index(['site_read_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_read_pages');
        Schema::dropIfExists('site_reads');
    }
};
