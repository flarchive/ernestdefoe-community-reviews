<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Photo;

/**
 * MyBB's photo host: the browser uploads straight to imgur with the admin's Client ID and sends us the link.
 * We only keep links to imgur's own image host, so nothing else can be embedded through a "photo".
 */
final class Imgur
{
    /** @return array{0: string, 1: string}|null the https image URL and its medium thumbnail ("m" suffix) */
    public static function urls(string $link): ?array
    {
        if (preg_match('#^https?://i\.imgur\.com/([A-Za-z0-9]{5,10})\.(jpe?g|png|gif|webp)$#', trim($link), $m) !== 1) {
            return null;
        }

        return ['https://i.imgur.com/'.$m[1].'.'.$m[2], 'https://i.imgur.com/'.$m[1].'m.'.$m[2]];
    }
}
