<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Photo;

use ErnestDefoe\CommunityReviews\Model\Photo;
use Flarum\Http\UrlGenerator;

/** Where a photo is shown from: our controller for a local file, imgur for an imgur link. */
class Urls
{
    public function __construct(private readonly UrlGenerator $url)
    {
    }

    /** @return array{id: int, url: string, thumbUrl: string, width: int, height: int} */
    public function of(Photo $photo): array
    {
        return [
            'id' => $photo->id,
            'url' => $this->full($photo),
            'thumbUrl' => $this->thumb($photo),
            'width' => $photo->width,
            'height' => $photo->height,
        ];
    }

    public function full(Photo $photo): string
    {
        return $photo->isLocal()
            ? $this->url->to('forum')->route('community-reviews.photo', ['id' => $photo->id])
            : (string) $photo->url;
    }

    public function thumb(Photo $photo): string
    {
        return $photo->isLocal()
            ? $this->url->to('forum')->route('community-reviews.photo', ['id' => $photo->id]).'?thumb=1'
            : (string) ($photo->thumb_url ?? $photo->url);
    }
}
