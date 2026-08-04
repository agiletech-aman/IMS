<?php

return [
    'path' => storage_path('app/backups'),

    /*
    | Set BACKUP_SHELL_ENABLED=false on hosts that disable process execution.
    | The database exporter will then use the portable PDO implementation.
    */
    'shell_enabled' => env('BACKUP_SHELL_ENABLED', true),
    'mysqldump_binary' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_binary' => env('BACKUP_MYSQL_PATH', 'mysql'),

    'health' => [
        'maximum_age_hours' => (int) env('BACKUP_HEALTH_MAX_AGE_HOURS', 72),
        'minimum_free_space_mb' => (int) env('BACKUP_MIN_FREE_SPACE_MB', 100),
    ],

    /*
    | Only upload roots are archived. This deliberately prevents application
    | source, .env, credentials, dependencies, logs, caches and prior backups
    | from entering a files backup.
    */
    'upload_paths' => [
        storage_path('app/public'),
        public_path('uploads'),
    ],

    /*
    | Ephemeral runtime tables are omitted. Backup/recovery control tables are
    | included in the export but protected from import during a live restore.
    */
    'excluded_database_tables' => [
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
    ],

    'protected_restore_tables' => [
        'backups',
        'backup_logs',
        'backup_schedules',
        'restore_jobs',
    ],
];
