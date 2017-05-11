<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePodioAuthTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('podio_api_credentials', function (Blueprint $table) {
            $table->increments('id');
            $table->text('client_id');
            $table->text('client_secret');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->integer('expires_in');
            $table->boolean('is_in_use');
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
        Schema::drop('podio_api_credentials');
    }
}
