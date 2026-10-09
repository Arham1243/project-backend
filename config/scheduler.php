<?php

return [
    'job_timeout' => (int) env('SCHEDULER_JOB_TIMEOUT', 120),
    'overlap_minutes' => (int) env('SCHEDULER_OVERLAP_MINUTES', 10),
    'active_jobs_cache_ttl' => (int) env('SCHEDULER_ACTIVE_JOBS_CACHE_TTL', 60),
    'queue_health_pending_max_age' => (int) env('QUEUE_HEALTH_PENDING_MAX_AGE', 300),
];
