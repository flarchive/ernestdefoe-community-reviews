<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// A member's review of an item. MyBB allows several per member per item, and so do we unless the admin says one.
return Migration::createTable('community_reviews', function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('item_id');
    $table->unsignedInteger('user_id')->nullable();
    // The average of this review's criterion scores (MyBB shows a review's rating this way too).
    $table->decimal('rating', 3, 2);
    $table->string('title', 150)->nullable();
    // "General thoughts": formatter XML, like a post's content.
    $table->mediumText('body')->nullable();
    $table->string('price', 30)->nullable();
    $table->string('url', 255)->nullable();
    $table->unsignedInteger('merchant_id')->nullable();
    $table->string('owned_for', 16)->nullable();
    $table->text('pros')->nullable();
    $table->text('cons')->nullable();
    $table->boolean('is_verified')->default(false);
    $table->unsignedInteger('helpful_count')->default(0);
    $table->unsignedInteger('comment_count')->default(0);
    $table->string('status', 10)->default('approved');
    // "Updated after N months": the rating before the latest edit and when it was first posted.
    $table->decimal('previous_rating', 3, 2)->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->dateTime('edited_at')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();

    $table->index(['item_id', 'status', 'created_at']);
    $table->index(['user_id', 'created_at']);
    $table->index(['merchant_id', 'created_at']);
    $table->index(['status', 'created_at']);
    $table->foreign('item_id')->references('id')->on('community_review_items')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
    $table->foreign('merchant_id')->references('id')->on('users')->nullOnDelete();
});
