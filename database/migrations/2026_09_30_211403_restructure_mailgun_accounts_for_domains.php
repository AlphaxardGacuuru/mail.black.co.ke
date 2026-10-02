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
        Schema::table('mailgun_accounts', function (Blueprint $table) {
            $table->foreignUuid('mailgun_domain_id')->nullable()->after('user_id')
                ->constrained('mailgun_domains')->cascadeOnDelete();
            $table->text('mailgun_smtp_password')->nullable()->after('mailgun_domain_id');
        });

        // Every existing account bundled its own copy of a domain + API key.
        // Fold each user's distinct domains into one mailgun_domains row and
        // point their accounts at it.
        $domainIds = [];

        DB::table('mailgun_accounts')->orderBy('id')->get()->each(function ($account) use (&$domainIds) {
            $key = $account->user_id.'|'.$account->mailgun_domain;

            if (! isset($domainIds[$key])) {
                $domainIds[$key] = (string) Str::uuid();

                DB::table('mailgun_domains')->insert([
                    'id' => $domainIds[$key],
                    'user_id' => $account->user_id,
                    'domain' => $account->mailgun_domain,
                    'api_key' => $account->mailgun_api_key,
                    'endpoint' => $account->mailgun_endpoint,
                    'created_at' => $account->created_at,
                    'updated_at' => $account->updated_at,
                ]);
            }

            DB::table('mailgun_accounts')
                ->where('id', $account->id)
                ->update(['mailgun_domain_id' => $domainIds[$key]]);
        });

        Schema::table('mailgun_accounts', function (Blueprint $table) {
            $table->dropColumn(['mailgun_domain', 'mailgun_api_key', 'mailgun_endpoint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mailgun_accounts', function (Blueprint $table) {
            $table->string('mailgun_domain')->nullable()->after('mail_from_name');
            $table->text('mailgun_api_key')->nullable()->after('mailgun_domain');
            $table->string('mailgun_endpoint')->default('api.mailgun.net')->after('mailgun_api_key');
        });

        DB::table('mailgun_accounts')
            ->join('mailgun_domains', 'mailgun_domains.id', '=', 'mailgun_accounts.mailgun_domain_id')
            ->update([
                'mailgun_accounts.mailgun_domain' => DB::raw('mailgun_domains.domain'),
                'mailgun_accounts.mailgun_api_key' => DB::raw('mailgun_domains.api_key'),
                'mailgun_accounts.mailgun_endpoint' => DB::raw('mailgun_domains.endpoint'),
            ]);

        Schema::table('mailgun_accounts', function (Blueprint $table) {
            // Left nullable on rollback (doctrine/dbal isn't installed, so
            // Blueprint::change() isn't available to restore the original
            // NOT NULL constraint) — the backfill above still populates
            // every existing row.
            $table->dropForeign(['mailgun_domain_id']);
            $table->dropColumn(['mailgun_domain_id', 'mailgun_smtp_password']);
        });
    }
};
