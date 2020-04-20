<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMangasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mangas', function (Blueprint $table) {
            $table->bigIncrements("id");
            $table->string("CoverLink",100)->nullable(false);
            $table->string("Name",60)->nullable(false)->unique();
            $table->string("Author",60)->nullable(false);
            $table->string("Description",800)->nullable(false);
            $table->datetimeTz("UpdatedAt")->nullable(true);
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
        Schema::dropIfExists('mangas');
    }
}
