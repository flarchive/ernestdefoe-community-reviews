<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews;

use ErnestDefoe\CommunityReviews\Model\Comment;
use ErnestDefoe\CommunityReviews\Model\Review;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Formatter\Formatter;

/** Reviews and comments are formatted like posts (Markdown, BBCode, emoji): the same formatter, set once. */
class ServiceProvider extends AbstractServiceProvider
{
    public function boot(Formatter $formatter): void
    {
        Review::setFormatter($formatter);
        Comment::setFormatter($formatter);
    }
}
