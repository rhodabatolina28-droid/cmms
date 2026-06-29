<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('csm_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
            
            // Demographics
            $table->integer('age');
            $table->string('sex', 20);
            
            // Citizen's Charter
            $table->string('cc1', 50);
            $table->string('cc2', 50);
            $table->string('cc3', 50);
            
            // Service Quality Dimensions (SQD)
            $table->string('sqd1', 50);
            $table->string('sqd2', 50);
            $table->string('sqd3', 50);
            $table->string('sqd4', 50);
            $table->string('sqd5', 50);
            $table->string('sqd6', 50);
            $table->string('sqd7', 50);
            $table->string('sqd8', 50);
            $table->string('sqd9', 50);
            
            // Feedback
            $table->text('suggestions')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('csm_surveys');
    }
};
