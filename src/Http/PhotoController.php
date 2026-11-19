<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Http;

use ErnestDefoe\CommunityReviews\Model\Photo;
use ErnestDefoe\CommunityReviews\Model\Review;
use ErnestDefoe\CommunityReviews\Photo\Storage;
use Flarum\Http\Exception\RouteNotFoundException;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /reviews/photo/{id}` (`?thumb=1` for the thumbnail): a review photo, only for someone who may see the
 * review (the visibility rule), or who uploaded it (the preview before posting). No file extension in the
 * route: nginx would serve a *.webp path from disk and PHP would never see it.
 */
class PhotoController implements RequestHandlerInterface
{
    public function __construct(private readonly Storage $storage)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $parameters = $request->getAttribute('routeParameters');
        $photo = Photo::query()->find((int) (is_array($parameters) ? ($parameters['id'] ?? 0) : 0));
        if ($photo === null || ! $photo->isLocal()) {
            throw new RouteNotFoundException();
        }

        $own = ! $actor->isGuest() && $photo->user_id === $actor->id;
        if (! $own && ($photo->review_id === null || ! Review::whereVisibleTo($actor)->whereKey($photo->review_id)->exists())) {
            throw new PermissionDeniedException();
        }

        $path = ($request->getQueryParams()['thumb'] ?? null) ? $photo->thumb_path : $photo->path;
        $disk = $this->storage->disk();
        $source = $this->storage->valid($path) ? $disk->readStream((string) $path) : null;
        if (! is_resource($source)) {
            throw new RouteNotFoundException();
        }

        return new Response(new Stream($source), 200, [
            'Content-Type' => 'image/webp',
            'Content-Length' => (string) $disk->size((string) $path),
            // Private: hiding or deleting a review takes the photo away from anyone who hasn't loaded it.
            'Cache-Control' => 'private, max-age=86400',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
