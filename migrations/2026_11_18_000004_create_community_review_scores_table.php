<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// MyBB "review_fields": one criterion's 1-5 score and its comment, in one review.
return Migration::createTable('community_review_scores', function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('review_id');
    $table->unsignedInteger('criterion_id');
    $table->unsignedTinyInteger('score');
    $table->text('comment')->nullable();

    $table->unique(['review_id', 'criterion_id']);
    $table->index('criterion_id');
    $table->foreign('review_id')->references('id')->on('community_reviews')->cascadeOnDelete();
    $table->foreign('criterion_id')->references('id')->on('community_review_criteria')->cascadeOnDelete();
});
