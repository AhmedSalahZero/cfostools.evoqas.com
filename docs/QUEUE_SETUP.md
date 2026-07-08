# Queue Worker Setup

Upload jobs (`ProcessSalesUpload`, `ProcessExpenseUpload`, `ProcessExportSalesUpload`) use the database queue driver by default.

## Development

```bash
php artisan queue:work database --sleep=3 --tries=3
```

## Production (Supervisor example)

```ini
[program:evoqas-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/queue.log
```

## Notes

- Set `after_commit` to `true` in `config/queue.php` for the database connection so jobs run after DB transactions commit.
- Monitor `storage/logs/laravel.log` for failed jobs.
- Run `php artisan queue:failed` to inspect failures.
