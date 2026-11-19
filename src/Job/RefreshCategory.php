<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Job;

use ErnestDefoe\CommunityReviews\Aggregates;
use Flarum\Queue\AbstractJob;

/** Recounts a category after an item left it (deleted, or merged into another category's item). */
class RefreshCategory extends AbstractJob
{
    public function __construct(public readonly int $categoryId)
    {
    }

    public function handle(Aggregates $aggregates): void
    {
        $aggregates->refreshCategory($this->categoryId);
    }
}
