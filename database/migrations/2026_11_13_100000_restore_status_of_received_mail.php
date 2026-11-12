<?php

use App\Enums\MailStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mailgun's events for mail coming in ("accepted") were applied to it,
     * giving received mail a sent tick: it's received again.
     */
    public function up(): void
    {
        DB::table('mail_messages')
            ->where('direction', 'inbound')
            ->where('status', '!=', MailStatus::RECEIVED->value)
            ->update(['status' => MailStatus::RECEIVED->value]);
    }

    public function down(): void
    {
        //
    }
};
