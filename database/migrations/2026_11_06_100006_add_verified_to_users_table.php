<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A manually-granted "verified" badge (admin-toggled), distinct
            // from email_verified_at which only tracks email ownership.
            $table->boolean('verified')->default(false)->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('verified');
        });
    }
};
