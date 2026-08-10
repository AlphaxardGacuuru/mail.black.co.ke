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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('number')->unique();
            $table->foreignUuid('user_unit_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('type');
            $table->float('amount');
            $table->float('paid')->default(0);
            $table->float('balance');
            $table->string('status')->default('not_paid');
            $table->integer('month');
            $table->integer('year');
            $table->integer('emails_sent')->default(0);
            $table->integer('smses_sent')->default(0);
            $table->integer('reminders_sent')->default(0);
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(["user_unit_id", "type", "month", "year"]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
