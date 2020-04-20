<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChaptersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('Number')->nullable(false);
            $table->string("MangaName")->nullable(false);
            $table->string("Name",60)->nullable(false)->unique();
            $table->integer("PageCount")->nullable(false);
            $table->datetimeTz("PublishedAt")->nullable(false);
            $table->index(['MangaName','Number','Name'])->unique('ChapterId');
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
        Schema::dropIfExists('chapters');
    }
}
