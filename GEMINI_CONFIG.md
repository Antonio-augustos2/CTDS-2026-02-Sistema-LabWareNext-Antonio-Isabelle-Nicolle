# Gemini API and cron configuration

`GEMINI_API_KEY` resolution is explicit: a non-empty environment variable takes precedence; otherwise, the application uses `.env` beside `config.php`. Keep `.env` local and out of version control. For local setup, add `GEMINI_API_KEY=<your-local-key>` to that file without sharing the value. In production, configure the environment variable for Apache and restart Apache after changing it. On local database hosts (`localhost`, `127.0.0.1`, or `::1`), an omitted `DB_PASS` defaults to an empty password for XAMPP installations configured that way. Remote database hosts still require `DB_PASS` to be configured.

The cron runner uses the same environment-first, project-`.env`-fallback rule. The endpoint loads configuration from the project directory served by XAMPP (`C:\xampp\htdocs\TCC-labware`). Do not put credentials in the task XML, command arguments, URL query strings, frontend code, or logs.

The Task Scheduler action should be:

- Program: `C:\xampp\php\php.exe`
- Arguments: `-f "C:\xampp\htdocs\TCC-labware\data\run_cron.php"`
- Start in: `C:\xampp\htdocs\TCC-labware`

The admin connection check uses `models.list`; a successful result does not confirm `generateContent` permission, project verification, or billing. Authentication diagnostics preserve only sanitized Google error fields. A `CONSUMER_SUSPENDED` response is reported as `project_suspended`; code changes cannot reactivate a suspended Google Cloud project. Restore the associated project in Google Cloud or create a new eligible project and key, then update the server configuration. The automated test suite uses fictitious values and does not call Google or process resumes.
