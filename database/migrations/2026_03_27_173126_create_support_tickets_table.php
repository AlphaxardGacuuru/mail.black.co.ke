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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('number')->unique();
            $table->foreignUuid('user_unit_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignUuid('complaint_to_id')
                ->nullable()
                ->constrained('user_units')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('category');
            $table->string('subject');
            $table->string('priority')->nullable();
            $table->longText('description')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->string('status')->default('open'); // open, in-progress, resolved
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
        Schema::dropIfExists('support_tickets');
    }
};
