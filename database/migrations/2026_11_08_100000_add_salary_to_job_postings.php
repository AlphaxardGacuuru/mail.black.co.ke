<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pay a posting states, or the AI's estimate of it, shown with the
     * whole job when the user opens it.
     */
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            // {min, max, currency, period, stated, basis}
            $table->json('salary')
                ->nullable()
                ->after('description');
            // When the AI last looked, so a job it couldn't price isn't
            // asked about again on every open.
            $table->timestamp('salary_checked_at')
                ->nullable()
                ->after('salary');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn(['salary', 'salary_checked_at']);
        });
    }
};
