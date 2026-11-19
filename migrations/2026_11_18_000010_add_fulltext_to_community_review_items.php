<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

// MyBB searches product names with a FULLTEXT index. MySQL and PostgreSQL get one; SQLite has none and
// Search\ItemSearcher falls back to a name prefix match there.
return [
    'up' => function (Builder $schema): void {
        if ($schema->getConnection()->getDriverName() === 'sqlite') {
            return;
        }
        $schema->table('community_review_items', function (Blueprint $table): void {
            $table->fullText('name');
        });
    },
    'down' => function (Builder $schema): void {
        if ($schema->getConnection()->getDriverName() === 'sqlite') {
            return;
        }
        $schema->table('community_review_items', function (Blueprint $table): void {
            $table->dropFullText(['name']);
        });
    },
];
