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
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('service'); // e.g., 'kopokopo'
            $table->string('event_type'); // e.g., 'buygoods_transaction_received'
            $table->string('external_id'); // The ID returned by Kopo Kopo
            $table->string('url'); // The webhook URL used for this specific subscription
            $table->string('status')->default('active');
            $table->json('meta')->nullable(); // To store the full response if needed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('integrations');
    }
};
