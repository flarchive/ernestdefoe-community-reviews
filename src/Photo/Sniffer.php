<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Photo;

/** The image type from its first bytes; the file name and the browser's claim are never trusted. */
final class Sniffer
{
    /** @return 'jpeg'|'png'|'gif'|'webp'|null */
    public static function sniff(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpeg',
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'png',
            str_starts_with($bytes, 'GIF87a'), str_starts_with($bytes, 'GIF89a') => 'gif',
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'webp',
            default => null,
        };
    }
}
