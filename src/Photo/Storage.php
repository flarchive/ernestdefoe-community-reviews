<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Photo;

use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/**
 * The photo disk (`ernestdefoe-community-reviews`): storage/community-reviews by default, outside the public
 * folder, so a file is only served through Http\PhotoController after the review's visibility check.
 * Files get random names; no part of a path comes from the upload.
 */
class Storage
{
    public const DISK = 'ernestdefoe-community-reviews';

    private const PATH = '#^\d{4}/\d{2}/[0-9a-f]{32}(-t)?\.webp$#';

    public function __construct(private readonly Factory $filesystems)
    {
    }

    public function disk(): Filesystem
    {
        return $this->filesystems->disk(self::DISK);
    }

    /** @return array{0: string, 1: string} the photo's path and its thumbnail's */
    public function putPair(string $photo, string $thumb): array
    {
        $base = date('Y/m').'/'.bin2hex(random_bytes(16));
        $paths = [$base.'.webp', $base.'-t.webp'];
        if ($this->disk()->put($paths[0], $photo) !== true || $this->disk()->put($paths[1], $thumb) !== true) {
            $this->delete($paths);

            throw new RuntimeException('Community Reviews could not write a photo to its disk.');
        }

        return $paths;
    }

    public function valid(?string $path): bool
    {
        return is_string($path) && preg_match(self::PATH, $path) === 1;
    }

    /** @param iterable<mixed> $paths only paths this class wrote are touched */
    public function delete(iterable $paths): void
    {
        $valid = [];
        foreach ($paths as $path) {
            if ($this->valid(is_string($path) ? $path : null)) {
                $valid[] = $path;
            }
        }
        if ($valid !== []) {
            $this->disk()->delete($valid);
        }
    }
}
