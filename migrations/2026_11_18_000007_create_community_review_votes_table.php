<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// "Helpful" votes (an extra, off by default). One per member per review.
return Migration::createTable('community_review_votes', function (Blueprint $table): void {
    $table->unsignedInteger('review_id');
    $table->unsignedInteger('user_id');
    $table->dateTime('created_at')->nullable();

    $table->primary(['review_id', 'user_id']);
    $table->index('user_id');
    $table->foreign('review_id')->references('id')->on('community_reviews')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
