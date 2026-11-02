<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            // The user's own cover letter, as text.
            $table->text('cover_letter')->nullable()->after('profile');
            // "tailor": the AI adapts it to each job; "as_is": sent as
            // written with {company} and {role} filled in.
            $table->string('cover_letter_mode')->default('tailor')->after('cover_letter');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropColumn(['cover_letter', 'cover_letter_mode']);
        });
    }
};
