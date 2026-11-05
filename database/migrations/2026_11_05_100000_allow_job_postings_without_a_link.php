<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A job post shared from WhatsApp is often just a poster, with an email
     * or office to apply to and no link.
     */
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('url', 2048)
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('url', 2048)
                ->nullable(false)
                ->change();
        });
    }
};
