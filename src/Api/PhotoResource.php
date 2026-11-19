<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Api;

use Carbon\Carbon;
use ErnestDefoe\CommunityReviews\Config;
use ErnestDefoe\CommunityReviews\Model\Photo;
use ErnestDefoe\CommunityReviews\Photo\Imgur;
use ErnestDefoe\CommunityReviews\Photo\Storage;
use ErnestDefoe\CommunityReviews\Photo\Uploader;
use ErnestDefoe\CommunityReviews\Photo\Urls;
use ErnestDefoe\CommunityReviews\Write\Fail;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Serializer;
use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Tobyz\JsonApiServer\Context as BaseContext;

use function Tobyz\JsonApiServer\json_api_response;

/**
 * The `review-photos` type: a member's own uploads. A review takes them by id (ReviewWriter checks ownership).
 *
 * POST   /api/review-photos   multipart `file` (stored on the forum), or {"imgurUrl": "https://i.imgur.com/…"}
 *                             when the admin picked imgur (the browser uploads there itself, like MyBB)
 * DELETE /api/review-photos/{id}   an unused upload, by its owner
 *
 * @extends AbstractDatabaseResource<Photo>
 */
class PhotoResource extends AbstractDatabaseResource
{
    public function __construct(
        private readonly Uploader $uploader,
        private readonly Storage $storage,
        private readonly Urls $urls,
        private readonly Config $config,
        private readonly Fail $fail,
    ) {
    }

    public function type(): string
    {
        return 'review-photos';
    }

    public function model(): string
    {
        return Photo::class;
    }

    /** @param Builder<Photo> $query */
    public function scope(Builder $query, BaseContext $context): void
    {
        $actor = $context->getActor();
        $query->where('community_review_photos.user_id', $actor->isGuest() ? 0 : $actor->id);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Endpoint::make('upload')
                ->route('POST', '/')
                ->authenticated()
                ->can('communityReviews.write')
                ->action(fn (Context $context): Photo => $this->store($context))
                // A collection-level endpoint has no model in its context, so it says how to answer.
                ->response(fn (Context $context, Photo $photo): ResponseInterface => json_api_response($this->document($context, $photo), 201)),
            Endpoint\Delete::make()->authenticated(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('url')->get(fn (Photo $p): string => $this->urls->full($p)),
            Schema\Str::make('thumbUrl')->get(fn (Photo $p): string => $this->urls->thumb($p)),
            Schema\Integer::make('width'),
            Schema\Integer::make('height'),
        ];
    }

    /** @param Context $context */
    public function deleting(object $model, BaseContext $context): void
    {
        if ($model instanceof Photo) {
            if ($model->review_id !== null) {
                $this->fail->on('photo', 'photo_in_use');
            }
            $this->storage->delete([$model->path, $model->thumb_path]);
        }
    }

    private function store(Context $context): Photo
    {
        $actor = $context->getActor();
        if ($this->config->maxPhotos() < 1) {
            $this->fail->on('file', 'photos_off');
        }

        $link = $context->body()['imgurUrl'] ?? null;
        if (is_string($link)) {
            $urls = $this->config->imgur() ? Imgur::urls($link) : null;
            if ($urls === null) {
                $this->fail->on('imgurUrl', 'photo_bad_link');
            }
            $photo = new Photo();
            $photo->user_id = $actor->id;
            [$photo->url, $photo->thumb_url] = $urls;
            $photo->created_at = Carbon::now();
            $photo->save();

            return $photo;
        }

        $file = $context->request->getUploadedFiles()['file'] ?? null;
        if (! $file instanceof UploadedFileInterface) {
            $this->fail->on('file', 'upload_failed');
        }

        return $this->uploader->upload($actor, $file);
    }

    /** @return array{data: mixed} */
    private function document(Context $context, Photo $photo): array
    {
        $serializer = new Serializer($context);
        $serializer->addPrimary($this, $photo, []);
        [$primary] = $serializer->serialize();

        return ['data' => $primary[0]];
    }
}
