<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CVs revamped by the AI: the user's revamp, reviewed section by
     * section, a CV tailored to each job when they ask for one, and the
     * Google searches an AI request ran, which are billed on their own.
     */
    public function up(): void
    {
        Schema::create('cv_revamps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            // The uploaded CV it was made from.
            $table->char('cv_hash', 64);
            $table->string('status');
            $table->json('document')->nullable();
            $table->json('questions')->nullable();
            $table->string('error', 1000)->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('docx_path')->nullable();
            // When the user chose to send it instead of their own CV.
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->boolean('tailor_cv')->default(false)->after('cover_letter_mode');
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->string('cv_status')->nullable()->after('form_submitted_at');
            $table->json('cv')->nullable()->after('cv_status');
            $table->string('cv_path')->nullable()->after('cv');
        });

        Schema::table('ai_usages', function (Blueprint $table) {
            $table->unsignedInteger('searches')->default(0)->after('reasoning_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usages', function (Blueprint $table) {
            $table->dropColumn('searches');
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn(['cv_status', 'cv', 'cv_path']);
        });

        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropColumn('tailor_cv');
        });

        Schema::dropIfExists('cv_revamps');
    }
};
