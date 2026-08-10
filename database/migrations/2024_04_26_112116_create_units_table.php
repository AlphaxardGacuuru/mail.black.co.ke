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
        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid("property_id")
                ->constrained()
                ->onUpdate("cascade")
                ->onDelete("cascade");
            $table->string("name");
            $table->integer("rent");
            $table->integer("deposit");
            $table->jsonb("service_charge")->nullable();
            $table->integer("bedrooms")->nullable();
            $table->jsonb("size")->nullable();
            $table->string("type")->default("apartment");
            $table->integer("ensuite")->default(0);
            $table->boolean("dsq")->default(false);
            $table->string("status")->default("vacant");
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
        Schema::dropIfExists('units');
    }
};
