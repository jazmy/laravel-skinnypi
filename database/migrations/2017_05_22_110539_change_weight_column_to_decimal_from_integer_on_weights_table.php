<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeWeightColumnToDecimalFromIntegerOnWeightsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('weight_logs', function (Blueprint $table) {
            $table->decimal( 'weight', 10, 2 )->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('weight_logs', function (Blueprint $table) {
            $table->integer( 'weight' )->change();
        });
    }
}
