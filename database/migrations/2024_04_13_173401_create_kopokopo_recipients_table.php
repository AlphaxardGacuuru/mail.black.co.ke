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
        Schema::create('kopokopo_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid("user_id")->constrained();
            $table->string('destination_reference')->nullable();
            $table->string("type")->nullable();
            $table->string("first_name")->nullable();
            $table->string("last_name")->nullable();
            $table->string("email")->nullable();
            $table->string("phone_number")->nullable();
            $table->string("account_name")->nullable();
            $table->string("account_number")->nullable();
            $table->string("till_name")->nullable();
            $table->string("till_number")->nullable();
            $table->string("paybill_name")->nullable();
            $table->string("paybill_number")->nullable();
            $table->string("paybill_account_number")->nullable();
            $table->string("description")->nullable();
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
        Schema::dropIfExists('kopokopo_recipients');
    }
};
