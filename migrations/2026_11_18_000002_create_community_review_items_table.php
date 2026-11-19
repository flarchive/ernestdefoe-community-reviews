<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// MyBB "products". Counts and averages live on the row (kept by Aggregates), never computed on a list.
return Migration::createTable('community_review_items', function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('category_id');
    $table->unsignedInteger('user_id')->nullable();
    $table->string('name', 255);
    $table->string('slug', 255);
    $table->string('brand', 100)->nullable();
    $table->text('fields')->nullable();
    $table->string('status', 10)->default('approved');
    $table->unsignedInteger('review_count')->default(0);
    $table->unsignedInteger('comment_count')->default(0);
    $table->unsignedInteger('view_count')->default(0);
    // MyBB cached_rating: the plain (or weighted) average of every criterion score of every live review.
    $table->decimal('rating', 3, 2)->nullable();
    // Bayesian average, only for ranking ("Top rated"), so one 5-star review never tops the list.
    $table->decimal('rating_rank', 5, 3)->nullable();
    $table->text('criteria_avg')->nullable();
    // How many approved reviews round to 1..5 stars: the item page's distribution bars.
    $table->string('distribution', 100)->nullable();
    $table->text('top_mentions')->nullable();
    $table->unsignedInteger('thumbnail_photo_id')->nullable();
    $table->dateTime('last_activity_at')->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();

    $table->index(['category_id', 'rating_rank']);
    $table->index(['status', 'created_at']);
    $table->index(['status', 'last_activity_at']);
    $table->index('slug');
    $table->index('name');
    $table->index('user_id');
    $table->foreign('category_id')->references('id')->on('community_review_categories')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
});
