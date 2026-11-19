<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Api\Concerns;

use Flarum\Api\Context;

/** The request's attributes (the writers read them together) and the client address MyBB stores with a post. */
trait ReadsInput
{
    /** @return array<string, mixed> */
    protected function attributes(Context $context): array
    {
        $attributes = $context->body()['data']['attributes'] ?? [];

        return is_array($attributes) ? $attributes : [];
    }

    protected function ip(Context $context): ?string
    {
        $ip = $context->request->getAttribute('ipAddress');

        return is_string($ip) ? $ip : null;
    }
}
