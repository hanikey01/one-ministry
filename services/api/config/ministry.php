<?php

return [
    'playback_diagnostic_retention_days' => (int) env('PLAYBACK_DIAGNOSTIC_RETENTION_DAYS', 7),
    'livestream_probe_regions' => json_decode((string) env('LIVESTREAM_PROBE_REGIONS', '[]'), true) ?: [],
];
