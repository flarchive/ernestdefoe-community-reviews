<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Console;

use ErnestDefoe\CommunityReviews\Photo\Cleanup;
use Illuminate\Console\Command;

/** Daily: photos uploaded more than a day ago that no review uses (an abandoned composer). */
class PruneCommand extends Command
{
    protected $signature = 'community-reviews:prune';

    protected $description = 'Remove review photos that were uploaded but never used.';

    public function handle(Cleanup $cleanup): int
    {
        $this->info($cleanup->pruneUnused().' unused photos removed.');

        return self::SUCCESS;
    }
}
