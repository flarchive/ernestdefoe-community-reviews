<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Photo;

use Carbon\Carbon;
use ErnestDefoe\CommunityReviews\Model\Photo;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\RuntimeException as ImageException;
use Intervention\Image\ImageManager;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Turns an upload into a Photo row and two clean files (the photo, and a thumbnail for cards and the strip).
 *
 * - The size is checked before anything is read; the type comes from the bytes, not the name or the browser.
 * - JPEG, PNG, WebP and GIF are decoded and re-encoded as WebP, which drops EXIF and GPS and anything hidden
 *   after the picture, scaled to fit 2048 px (thumbnail 480 px).
 * - A member holds at most MAX_UNUSED photos no review uses yet; the oldest make way.
 */
class Uploader
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    private const MAX_PIXELS = 40_000_000;

    private const MAX_SIDE = 2048;

    private const THUMB_SIDE = 480;

    public const MAX_UNUSED = 12;

    public function __construct(
        private readonly Storage $storage,
        private readonly ImageManager $images,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function upload(User $actor, UploadedFileInterface $file): Photo
    {
        $bytes = $this->read($file);
        [$photo, $thumb, $width, $height] = $this->encode($bytes);
        $this->makeRoom($actor);

        [$path, $thumbPath] = $this->storage->putPair($photo, $thumb);
        $row = new Photo();
        $row->user_id = $actor->id;
        $row->path = $path;
        $row->thumb_path = $thumbPath;
        $row->width = $width;
        $row->height = $height;
        $row->created_at = Carbon::now();
        try {
            $row->save();
        } catch (\Throwable $e) {
            $this->storage->delete([$path, $thumbPath]);

            throw $e;
        }

        return $row;
    }

    private function read(UploadedFileInterface $file): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $this->fail('upload_failed');
        }
        $size = (int) $file->getSize();
        if ($size < 1 || $size > self::MAX_BYTES) {
            $this->fail('photo_too_large', ['max' => 10]);
        }
        $stream = $file->getStream();
        $stream->rewind();
        $bytes = $stream->getContents();
        if (! in_array(Sniffer::sniff($bytes), ['jpeg', 'png', 'gif', 'webp'], true)) {
            $this->fail('photo_bad_type');
        }

        return $bytes;
    }

    /** @return array{0: string, 1: string, 2: int, 3: int} */
    private function encode(string $bytes): array
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            $this->fail('photo_bad_type');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            $this->fail('photo_too_many_pixels', ['max' => 40]);
        }

        try {
            $image = $this->images->read($bytes);
            if ($image->isAnimated()) {
                $image = $image->removeAnimation();
            }
            $image->orient()->scaleDown(self::MAX_SIDE, self::MAX_SIDE);
            $photo = (string) $image->encode(new WebpEncoder(quality: 82));
            $width = $image->width();
            $height = $image->height();
            $thumb = (string) $image->scaleDown(self::THUMB_SIDE, self::THUMB_SIDE)->encode(new WebpEncoder(quality: 78));
        } catch (ImageException) {
            $this->fail('photo_bad_type');
        }

        return [$photo, $thumb, $width, $height];
    }

    /** The photo picker uploads every pick: unused old ones make way instead of refusing the member. */
    private function makeRoom(User $actor): void
    {
        $stale = Photo::query()->where('user_id', $actor->id)->whereNull('review_id')
            ->orderByDesc('id')->skip(self::MAX_UNUSED - 1)->limit(50)->get();
        foreach ($stale->chunk(50) as $chunk) {
            $this->storage->delete($chunk->flatMap(fn (Photo $p) => [$p->path, $p->thumb_path]));
            Photo::query()->whereIn('id', $chunk->modelKeys())->delete();
        }
    }

    /** @param array<string, int|string> $params */
    private function fail(string $key, array $params = []): never
    {
        throw new ValidationException(['file' => $this->translator->trans('ernestdefoe-community-reviews.api.'.$key, $params)]);
    }
}
