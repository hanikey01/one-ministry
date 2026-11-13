<?php

namespace App\Services\Ai;

final class AiPageCompositionContract
{
    public const MAX_SECTIONS = 12;

    public const MAX_COLUMNS = 4;

    public const MAX_COLUMN_ELEMENTS = 8;

    public const MAX_ELEMENTS = 40;

    public static function schema(array $types): array
    {
        $object = fn (array $fields): array => ['type' => 'object', 'properties' => $fields, 'required' => array_keys($fields), 'additionalProperties' => false];
        $list = fn (array $items, int $max, int $min = 0): array => ['type' => 'array', 'items' => $items, 'minItems' => $min, 'maxItems' => $max];
        $text = ['type' => 'string'];
        $pairs = $list($object(['field' => $text, 'value' => $text]), 80);
        $media = $list($object(['field' => $text, 'id' => $text]), 12);
        $responsive = $list($object(['breakpoint' => ['type' => 'string', 'enum' => ['desktop', 'tablet', 'mobile']], 'values' => $pairs]), 3);
        $design = ['design' => $pairs, 'responsive' => $responsive, 'media' => $media];
        $element = $object(['type' => ['type' => 'string', 'enum' => $types], 'content' => $pairs, ...$design]);
        $column = $object(['weight' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12], ...$design, 'elements' => $list($element, self::MAX_COLUMN_ELEMENTS, 1)]);
        $section = $object(['purpose' => $text, ...$design, 'columns' => $list($column, self::MAX_COLUMNS, 1)]);

        return $object(['title' => $text, 'seo_title' => $text, 'seo_description' => $text, 'sections' => $list($section, self::MAX_SECTIONS, 1)]);
    }

    public static function scopedSchema(array $types, string $kind): array
    {
        $section = self::schema($types)['properties']['sections']['items'];
        $column = $section['properties']['columns']['items'];
        $element = $column['properties']['elements']['items'];

        return ['type' => 'object', 'properties' => ['proposal' => match ($kind) {
            'section' => $section, 'column' => $column, default => $element
        }], 'required' => ['proposal'], 'additionalProperties' => false];
    }
}
