<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// MyBB product comments: on the item, optionally "in reply to" one review.
return Migration::createTable('community_review_comments', function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('item_id');
    $table->unsignedInteger('review_id')->nullable();
    $table->unsignedInteger('user_id')->nullable();
    $table->mediumText('body');
    $table->boolean('is_official')->default(false);
    $table->string('ip_address', 45)->nullable();
    $table->dateTime('edited_at')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();

    $table->index(['item_id', 'created_at']);
    $table->index('review_id');
    $table->index(['user_id', 'created_at']);
    $table->foreign('item_id')->references('id')->on('community_review_items')->cascadeOnDelete();
    $table->foreign('review_id')->references('id')->on('community_reviews')->nullOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
});
