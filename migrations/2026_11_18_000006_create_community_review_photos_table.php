<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// A review's photos. review_id is null between the upload and the review being saved.
// A photo is either a file on our disk (path) or an imgur link (url), never both.
return Migration::createTable('community_review_photos', function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('review_id')->nullable();
    $table->unsignedInteger('user_id');
    $table->string('path', 64)->nullable();
    $table->string('thumb_path', 64)->nullable();
    $table->string('url', 255)->nullable();
    $table->string('thumb_url', 255)->nullable();
    $table->unsignedSmallInteger('width')->default(0);
    $table->unsignedSmallInteger('height')->default(0);
    $table->unsignedInteger('position')->default(0);
    $table->dateTime('created_at')->nullable();

    $table->index(['review_id', 'position']);
    $table->index(['user_id', 'review_id']);
    $table->foreign('review_id')->references('id')->on('community_reviews')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
