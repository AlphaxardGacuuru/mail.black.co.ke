<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform's own domain (the one job search addresses are created
     * on) belongs to no user, so no one sees it among their domains.
     */
    public function up(): void
    {
        Schema::table('mailgun_domains', function (Blueprint $table) {
            $table->uuid('user_id')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('mailgun_domains', function (Blueprint $table) {
            $table->uuid('user_id')
                ->nullable(false)
                ->change();
        });
    }
};
