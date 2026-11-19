<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// MyBB "categories": a flat list (parent_id null) or one level of sub-categories, in the admin's order.
return Migration::createTable('community_review_categories', function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('parent_id')->nullable();
    $table->string('name', 100);
    $table->string('slug', 100)->unique();
    $table->string('description', 500)->nullable();
    $table->string('icon', 64)->nullable();
    $table->string('color', 16)->nullable();
    $table->unsignedInteger('position')->default(0);
    // Admin-defined spec fields for items in this category (extras): [{key, label, type, options, filter}].
    $table->text('item_fields')->nullable();
    $table->unsignedInteger('item_count')->default(0);
    $table->unsignedInteger('review_count')->default(0);
    $table->dateTime('created_at')->nullable();

    $table->index(['parent_id', 'position']);
    $table->foreign('parent_id')->references('id')->on('community_review_categories')->nullOnDelete();
});
