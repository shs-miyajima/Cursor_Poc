<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained();
            $table->foreignId('question_option_id')->nullable()->constrained();
            $table->text('text_value')->nullable();
            $table->timestamps();

            $table->index('survey_response_id');
            // 設問 × 選択肢の件数集計（GROUP BY）用
            $table->index(['question_id', 'question_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
    }
};
