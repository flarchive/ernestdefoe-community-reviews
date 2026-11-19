<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Write;

/**
 * MyBB's product URL rule: http(s) only (a bare domain gets http://), a real public host name or address,
 * never localhost or a private / reserved address. No request is made: the link is only shown, with
 * rel="nofollow ugc noopener".
 */
final class LinkCheck
{
    public static function normalise(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || mb_strlen($url) > 255 || preg_match('/\s/', $url) === 1) {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) !== 1) {
            $url = 'http://'.$url;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return self::publicHost(trim($host, '[]')) ? $url : null;
    }

    private static function publicHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        // A dotted name with a letters-only top-level part (MyBB requires the same).
        return preg_match('/^(?:[\p{L}\p{N}](?:[\p{L}\p{N}-]{0,61}[\p{L}\p{N}])?\.)+\p{L}{2,}$/u', $host) === 1;
    }
}
