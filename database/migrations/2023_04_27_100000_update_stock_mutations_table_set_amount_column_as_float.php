<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatedStockMutationsTableSetAmountColumnAsFloat extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(config('stock.table'), function (Blueprint $table) {
            $table->float('amount')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(config('stock.table'), function (Blueprint $table) {
            $table->integer('amount')->change();
        });
    }
}
