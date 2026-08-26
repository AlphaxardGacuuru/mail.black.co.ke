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

        DB::table('users')
            ->whereNotNull('mailbox_address')
            ->whereNotNull('mailgun_domain')
            ->whereNotNull('mailgun_api_key')
            ->orderBy('id')
            ->eachById(function (object $user): void {
                $accountId = (string) Str::uuid();

                DB::table('mailgun_accounts')->insert([
                    'id' => $accountId,
                    'user_id' => $user->id,
                    'mailbox_address' => $user->mailbox_address,
                    'mailgun_domain' => $user->mailgun_domain,
                    'mailgun_api_key' => $user->mailgun_api_key,
                    'mailgun_endpoint' => $user->mailgun_endpoint ?: 'api.mailgun.net',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('users')->where('id', $user->id)->update([
                    'active_mailgun_account_id' => $accountId,
                ]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'mailbox_address',
                'mailgun_domain',
                'mailgun_api_key',
                'mailgun_endpoint',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('mailbox_address')->nullable();
            $table->string('mailgun_domain')->nullable();
            $table->text('mailgun_api_key')->nullable();
            $table->string('mailgun_endpoint')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('active_mailgun_account_id');
        });

        Schema::dropIfExists('mailgun_accounts');
    }
};
