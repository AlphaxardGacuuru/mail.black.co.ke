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
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('number')->unique();
            $table->foreignUuid('user_unit_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('type')->default('fixed_term'); // fixed_term, month_to_month, renewal
            $table->date('start_date');
            $table->date('end_date')->nullable(); // Null for month-to-month
            $table->integer('rent_amount');
            $table->integer('deposit_amount')->default(0);
            $table->string('payment_frequency')->default('monthly'); // monthly, quarterly, annually
            $table->longText('terms')->nullable();
            $table->string('document')->nullable(); // Path to uploaded PDF
            $table->string('status')->default('active'); // active, expired, terminated, pending
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->integer('notice_period_days')->default(30);
            $table->foreignUuid('created_by')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
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
        Schema::dropIfExists('contracts');
    }
};
