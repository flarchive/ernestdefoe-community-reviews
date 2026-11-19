<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Write;

use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;

/** A translated validation error on one field. */
class Fail
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param array<string, string|int> $params
     * @return never
     */
    public function on(string $field, string $key, array $params = []): never
    {
        throw new ValidationException([$field => $this->translator->trans('ernestdefoe-community-reviews.api.'.$key, $params)]);
    }
}
