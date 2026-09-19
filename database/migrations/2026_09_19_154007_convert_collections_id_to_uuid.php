<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex(['collection_id', 'status']);
            $table->dropForeign(['collection_id']);
        });

        Schema::table('chunks', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'collection_id']);
            $table->dropForeign(['collection_id']);
        });

        Schema::table('agent_collection', function (Blueprint $table): void {
            $table->dropForeign(['collection_id']);
            $table->dropPrimary('agent_collection_pkey');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('collection_id');
        });

        Schema::table('chunks', function (Blueprint $table): void {
            $table->dropColumn('collection_id');
        });

        Schema::table('agent_collection', function (Blueprint $table): void {
            $table->dropColumn('collection_id');
        });

        // No document, chunk, or agent_collection row can reference a
        // collection at this point, so the existing rows can be cleared
        // before the primary key is rebuilt as a UUID.
        DB::table('collections')->delete();

        Schema::table('collections', function (Blueprint $table): void {
            $table->dropPrimary('collections_pkey');
            $table->dropColumn('id');
        });

        Schema::table('collections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });

        Schema::table('agent_collection', function (Blueprint $table): void {
            $table->foreignUuid('collection_id')->constrained()->cascadeOnDelete();
            $table->primary(['agent_id', 'collection_id']);
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignUuid('collection_id')->after('team_id')->constrained()->cascadeOnDelete();
            $table->index(['collection_id', 'status']);
        });

        Schema::table('chunks', function (Blueprint $table): void {
            $table->foreignUuid('collection_id')->after('team_id')->constrained()->cascadeOnDelete();
            $table->index(['team_id', 'collection_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex(['collection_id', 'status']);
            $table->dropForeign(['collection_id']);
            $table->dropColumn('collection_id');
        });

        Schema::table('chunks', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'collection_id']);
            $table->dropForeign(['collection_id']);
            $table->dropColumn('collection_id');
        });

        Schema::table('agent_collection', function (Blueprint $table): void {
            $table->dropForeign(['collection_id']);
            $table->dropPrimary('agent_collection_pkey');
            $table->dropColumn('collection_id');
        });

        DB::table('collections')->delete();

        Schema::table('collections', function (Blueprint $table): void {
            $table->dropPrimary('collections_pkey');
            $table->dropColumn('id');
        });

        Schema::table('collections', function (Blueprint $table): void {
            $table->id();
        });

        Schema::table('agent_collection', function (Blueprint $table): void {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->primary(['agent_id', 'collection_id']);
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('collection_id')->after('team_id')->constrained()->cascadeOnDelete();
            $table->index(['collection_id', 'status']);
        });

        Schema::table('chunks', function (Blueprint $table): void {
            $table->foreignId('collection_id')->after('team_id')->constrained()->cascadeOnDelete();
            $table->index(['team_id', 'collection_id']);
        });
    }
};
