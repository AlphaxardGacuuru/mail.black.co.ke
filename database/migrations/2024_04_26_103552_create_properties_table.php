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
        Schema::create('properties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid("user_id")
                ->constrained()
                ->onUpdate("cascade")
                ->onDelete("cascade");
            $table->string("name");
            $table->string("location");
            $table->string("deposit_formula");
            $table->jsonb("service_charge");
            $table->jsonb("water_bill_rate");
            $table->integer("unit_count")->default(0);
            $table->integer("invoice_date")->default(1);
            $table->integer("invoice_reminder_duration")->default(10);
            $table->longText('contract_terms')->nullable();
            $table->boolean("email")->default(1);
            $table->boolean("sms")->default(1);
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
        Schema::dropIfExists('properties');
    }
};
