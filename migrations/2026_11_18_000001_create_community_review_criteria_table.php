<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// MyBB "fields": what every review of an item in the category rates 1-5 and comments on.
return Migration::createTable('community_review_criteria', function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('category_id');
    $table->string('name', 100);
    // 1 = MyBB's plain average; an admin may weigh a criterion more.
    $table->unsignedTinyInteger('weight')->default(1);
    $table->unsignedInteger('position')->default(0);

    $table->index(['category_id', 'position']);
    $table->foreign('category_id')->references('id')->on('community_review_categories')->cascadeOnDelete();
});
