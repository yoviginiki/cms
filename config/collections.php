<?php

return [
    // Tier-1 static search index sharding (G2).
    'shard_raw_bytes' => (int) env('COLLECTIONS_SHARD_RAW_BYTES', 2_500_000),

    // Provisional Tier-1 record-count warning threshold (G3; G7 re-measures).
    'tier1_threshold' => (int) env('COLLECTIONS_TIER1_THRESHOLD', 2000),

    // Advanced SQL mode (G-Q2) guards.
    'sql_timeout_ms' => (int) env('COLLECTIONS_SQL_TIMEOUT_MS', 3000),
    'sql_row_cap' => (int) env('COLLECTIONS_SQL_ROW_CAP', 500),
    'sql_cost_limit' => (float) env('COLLECTIONS_SQL_COST_LIMIT', 5_000_000),
];
