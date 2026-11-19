<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// MyBB "Report": a member's report of an item, a review or a comment, for the moderators' queue.
// flarum/flags only knows posts, so reviews keep their own. One open report per person per thing.
return Migration::createTable('community_review_reports', function (Blueprint $table): void {
    $table->increments('id');
    $table->string('kind', 10);
    $table->unsignedInteger('target_id');
    // The item it concerns, and a snapshot of what was reported: the queue lists both without joining three tables.
    $table->unsignedInteger('item_id')->nullable();
    $table->string('excerpt', 255)->nullable();
    $table->unsignedInteger('user_id');
    $table->string('reason', 500)->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('resolved_at')->nullable();
    $table->unsignedInteger('resolved_by')->nullable();

    // Short column names keep the generated index name under 64 characters even with a long table prefix.
    $table->unique(['kind', 'target_id', 'user_id']);
    $table->index(['resolved_at', 'created_at']);
    $table->foreign('item_id')->references('id')->on('community_review_items')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
});
