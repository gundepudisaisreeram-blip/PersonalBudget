<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 (PHASE_7_DECISION_PACKAGE.md v1.5.3, section 12) updates the
 * dormant `statement_imports` table created in Phase 1. The table was
 * never policed by application code (no writes, no reads, no foreign key
 * from any certified service) before this migration, so it is altered in
 * place rather than superseded by a parallel table -- consistent with this
 * repository's precedent of never editing an already-run migration file,
 * this is a new, additive migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statement_imports', function (Blueprint $table) {
            $table->renameColumn('bank', 'parser_profile');
            $table->json('error_payload')->nullable()->after('error_message');
        });

        // Enum redefinition and the nullability change below both require
        // raw SQL: this repository has no doctrine/dbal dependency, so
        // Blueprint::change() is unavailable.
        DB::statement(
            'ALTER TABLE statement_imports MODIFY status ENUM('
            ."'UPLOADED','QUEUED','PARSING','VALIDATING','PREVIEW_READY','CONFIRMED','COMPLETED','FAILED'"
            .") NOT NULL DEFAULT 'UPLOADED'"
        );

        // parser_profile is null while an ambiguous-content import awaits
        // the user's explicit selection (section 5, "Ambiguous Match") --
        // it is UPLOADED but not yet QUEUED, so no parser has been chosen.
        DB::statement('ALTER TABLE statement_imports MODIFY parser_profile VARCHAR(255) NULL');

        Schema::table('statement_imports', function (Blueprint $table) {
            // Section 12: (account_id, file_hash) -> UNIQUE, replacing the
            // original (user_id, file_hash) unique constraint -- exact file
            // replay is rejected per-account, per the frozen contract.
            $table->dropUnique(['user_id', 'file_hash']);
            $table->unique(['account_id', 'file_hash']);
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE statement_imports SET parser_profile = '' WHERE parser_profile IS NULL");
        DB::statement('ALTER TABLE statement_imports MODIFY parser_profile VARCHAR(255) NOT NULL');

        Schema::table('statement_imports', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'file_hash']);
            $table->unique(['user_id', 'file_hash']);
            $table->dropColumn('error_payload');
            $table->renameColumn('parser_profile', 'bank');
        });

        DB::statement(
            'ALTER TABLE statement_imports MODIFY status ENUM('
            ."'UPLOADING','PARSING','VALIDATING','CATEGORIZING','MATCHING','READY_FOR_REVIEW','FAILED','COMPLETED'"
            .') NOT NULL'
        );
    }
};
