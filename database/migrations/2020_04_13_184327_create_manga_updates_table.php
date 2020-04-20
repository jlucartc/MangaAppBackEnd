<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMangaUpdatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('manga_updates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('ChapterCount')->nullable(false);
            $table->datetimeTz('UpdatedAt')->nullable(false);
            $table->string('MangaName')->nullable(false);
            $table->foreign('MangaName')->references('Name')->on('mangas');
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
        Schema::dropIfExists('manga_updates');
    }
}
