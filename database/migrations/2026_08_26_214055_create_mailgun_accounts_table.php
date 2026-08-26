<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mailgun_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('mailbox_address')->unique();
            $table->string('mailgun_domain');
            $table->text('mailgun_api_key');
            $table->string('mailgun_endpoint')->default('api.mailgun.net');
            $table->text('signature')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('active_mailgun_account_id')->nullable()->after('mailgun_endpoint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('active_mailgun_account_id');
        });

        Schema::dropIfExists('mailgun_accounts');
    }
};
