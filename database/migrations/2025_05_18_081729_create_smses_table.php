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
        Schema::create('smses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_unit_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignUuid('invoice_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->char('response_message')->nullable();
            $table->char('message_id')->nullable();
            $table->char('number')->nullable();
            $table->char('text')->nullable();
            $table->char('status')->nullable();
            $table->char('status_code')->nullable();
            $table->char('cost')->nullable();
            $table->char('delivery_status')->nullable();
            $table->char('network_code')->nullable();
            $table->char('failure_reason')->nullable();
            $table->char('retry_count')->nullable();
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
        Schema::dropIfExists('smses');
    }
};
