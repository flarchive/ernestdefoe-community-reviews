<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Write;

/** Small input helpers shared by the writers. */
final class Clean
{
    public static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));

        return $value === '' ? null : $value;
    }

    /** MyBB strips these symbols from a price and keeps the rest ("$54", "£40 incl. shipping"). */
    public static function price(mixed $value): ?string
    {
        $value = self::text($value);
        if ($value === null) {
            return null;
        }
        $value = trim(str_replace(str_split('!@#%^&*()-=_+[]{};\'\\:"|/<>?'), '', $value));

        return $value === '' ? null : mb_substr($value, 0, 30);
    }

    /** @return list<string> at most $max short labels, each trimmed, no duplicates */
    public static function labels(mixed $value, int $max = 10, int $length = 40): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $label) {
            $label = self::text($label);
            if ($label !== null && ! in_array(mb_strtolower($label), array_map('mb_strtolower', $out), true)) {
                $out[] = mb_substr($label, 0, $length);
            }
        }

        return array_slice($out, 0, $max);
    }

    /** @return list<int> */
    public static function ids(mixed $value): array
    {
        $ids = [];
        foreach (is_array($value) ? $value : [] as $id) {
            if (is_numeric($id) && (int) $id > 0 && ! in_array((int) $id, $ids, true)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
