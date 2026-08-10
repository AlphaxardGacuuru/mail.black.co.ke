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
        Schema::create('card_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid("user_id")->constrained()->nullable();
            $table->string("currency")->nullable();
            $table->string("amount")->nullable();
            $table->string("charged_amount")->nullable();
            $table->string("charge_response_code")->nullable();
            $table->string("charge_response_message")->nullable();
            $table->string("flw_ref")->nullable();
            $table->string("tx_ref")->nullable();
            $table->string("status")->nullable();
            $table->string("transaction_id")->nullable();
            $table->string("transaction_created_at")->nullable();
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
        Schema::dropIfExists('card_transactions');
    }
};
