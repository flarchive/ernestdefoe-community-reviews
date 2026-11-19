<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Http;

use ErnestDefoe\CommunityReviews\Config;
use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Model\Review;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\User\Guest;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The item page's <head>: title, description and canonical URL for whoever may see the item, and (the rich
 * results extra) schema.org Product + AggregateRating + up to five Reviews, only when a GUEST could see the item,
 * so nothing a guest can't read ever reaches a search engine. Reviews in it are approved ones, through the same
 * visibility rule as the API, with the reviewer's display name only.
 */
class ItemHead
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlGenerator $url,
    ) {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $parameters = $request->getAttribute('routeParameters');
        $id = (int) Str::before((string) (is_array($parameters) ? ($parameters['id'] ?? '') : ''), '-');
        $item = Item::whereVisibleTo(RequestUtil::getActor($request))->with('category')->find($id);
        if ($item === null) {
            return;
        }

        $url = $this->url->to('forum')->route('community-reviews.item', ['id' => $item->id.'-'.$item->slug]);
        $document->title = $item->name;
        $document->canonicalUrl = $url;
        $document->meta['description'] = Str::limit(trim(implode(' · ', array_filter([
            $item->brand,
            $item->category?->name,
            $item->rating !== null ? number_format($item->rating, 1).' / 5 from '.$item->review_count.' reviews' : null,
        ]))), 155);

        if ($this->config->extra('rich_results') && Item::whereVisibleTo(new Guest())->whereKey($item->id)->exists()) {
            $document->head[] = '<script type="application/ld+json">'.json_encode($this->schema($item, $url), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).'</script>';
        }
    }

    /** @return array<string, mixed> */
    private function schema(Item $item, string $url): array
    {
        $schema = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $item->name, 'url' => $url];
        if ($item->brand) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $item->brand];
        }
        if ($item->category) {
            $schema['category'] = $item->category->name;
        }

        $reviews = Review::whereVisibleTo(new Guest())->where('community_reviews.item_id', $item->id)
            ->where('community_reviews.status', 'approved')->with('user')->latest('created_at')->limit(5)->get();
        // Google's rule: an AggregateRating only with at least one visible review behind it.
        if ($item->rating !== null && $reviews->isNotEmpty()) {
            $schema['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round($item->rating, 2), 'bestRating' => 5, 'worstRating' => 1, 'reviewCount' => $item->review_count];
            $schema['review'] = $reviews->map(fn (Review $r) => array_filter([
                '@type' => 'Review',
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => round($r->rating, 2), 'bestRating' => 5, 'worstRating' => 1],
                'author' => ['@type' => 'Person', 'name' => $r->user !== null ? $r->user->display_name : 'Member'],
                'name' => $r->title,
                'datePublished' => $r->created_at?->toDateString(),
            ]))->values()->all();
        }

        return $schema;
    }
}
