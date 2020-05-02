<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('Number')->nullable(false);
            $table->string('MangaName')->nullable(false);
            $table->integer('ChapterNumber')->nullable(false);
            $table->string('PageLink')->nullable(false);
            $table->index(['Number','ChapterNumber','MangaName'])->unique('page_id');
            $table->foreign('MangaName')->references('Name')->on('mangas')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('pages');
    }
}
