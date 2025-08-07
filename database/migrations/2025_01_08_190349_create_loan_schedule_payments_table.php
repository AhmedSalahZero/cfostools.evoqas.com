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
        Schema::create('loan_schedule_payments', function (Blueprint $table) {
            $table->id();
			$table->json('totals');
			$table->json('beginning');
			$table->json('interestAmount');
			$table->json('schedulePayment');
			$table->json('principleAmount');
			$table->json('endBalance');
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
        Schema::dropIfExists('loan_schedule_payments');
    }
};
