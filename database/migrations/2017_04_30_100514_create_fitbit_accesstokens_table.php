<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFitbitAccesstokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fitbit_accesstokens', function (Blueprint $table) {
            $table->increments('id');

            $table->string('access_token');
            $table->string('refresh_token');
            $table->string('resource_owner_id');
            $table->integer('expires');

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
        Schema::dropIfExists('fitbit_accesstokens');
    }
}
