<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Tests\integration;

use Flarum\Database\Migrator;
use Flarum\Extension\ExtensionManager;
use PHPUnit\Framework\Attributes\Test;

/** Every migration can be undone and redone, leaving nothing behind. */
class MigrationsTest extends ReviewsTestCase
{
    #[Test]
    public function the_migrations_roll_back_cleanly_and_run_again(): void
    {
        $this->app();
        $container = $this->app()->getContainer();
        $extension = $container->make(ExtensionManager::class)->getExtension('ernestdefoe-community-reviews');
        $this->assertNotNull($extension);
        $schema = $this->database()->getSchemaBuilder();
        $tables = ['community_review_categories', 'community_review_criteria', 'community_review_items', 'community_reviews',
            'community_review_scores', 'community_review_comments', 'community_review_photos', 'community_review_votes', 'community_review_reports'];

        $migrator = $container->make(Migrator::class);
        $extension->migrate($migrator, 'down');
        foreach ($tables as $table) {
            $this->assertFalse($schema->hasTable($table), "$table was left behind");
        }
        $this->assertSame(0, $this->database()->table('group_permission')->where('permission', 'like', 'communityReviews.%')->count());

        $extension->migrate($migrator, 'up');
        foreach ($tables as $table) {
            $this->assertTrue($schema->hasTable($table), "$table is missing");
        }
        $this->assertSame(9, $this->database()->table('group_permission')->where('permission', 'like', 'communityReviews.%')->count());
    }
}
