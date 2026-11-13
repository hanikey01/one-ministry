<?php

namespace App\Services\Ai;

use App\Services\PageElementRegistry;

final class AiElementCatalogue
{
    public function __construct(private readonly PageElementRegistry $registry) {}

    public function forFeatures(array $features): array
    {
        $result = [];
        foreach ($this->registry->available($features) as $type => $definition) {
            $fields = [];
            foreach ($definition['field_definitions'] as $key => $field) {
                $fields[$key] = $field['validation'];
            }
            $result[$type] = [
                'purpose' => $definition['purpose'], 'content' => $fields,
                'design' => $definition['settings'], 'responsive' => $definition['responsive'],
                'media' => $definition['media'], 'use_cases' => $definition['use_cases'],
                'constraints' => [...$definition['factual_restrictions'], ...$definition['accessibility']],
                'guidance' => $definition['ai_guidance'],
            ];
        }

        return $result;
    }
}
