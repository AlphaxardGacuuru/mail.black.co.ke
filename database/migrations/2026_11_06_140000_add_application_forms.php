<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Applying on an employer's own form: where the form is, the questions
     * read from it with their answers, and answers the user gave before,
     * which fill in the same questions on the next form.
     */
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('apply_url', 2048)->nullable()->after('url');
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->string('form_status')->nullable()->after('status');
            $table->json('form')->nullable()->after('cover_letter');
            $table->string('form_error', 1000)->nullable()->after('form');
            $table->timestamp('form_prepared_at')->nullable()->after('form_error');
            $table->timestamp('form_submitted_at')->nullable()->after('form_prepared_at');
        });

        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->json('form_answers')->nullable()->after('preferences');
        });
    }

    public function down(): void
    {
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropColumn('form_answers');
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn(['form_status', 'form', 'form_error', 'form_prepared_at', 'form_submitted_at']);
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('apply_url');
        });
    }
};
