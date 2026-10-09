# Security report for the maintainers of karir.pkp

Written 2026-10-09 from a read of the source code at `/usr/share/nginx/html/karir.pkp` (as of 2026-10-03). Nothing was run against the live site and no request was sent to it.
Please treat this as confidential until the points are fixed. Whether the issues were already exploited is unknown: the checks in section 3 will tell.

## 1. Findings

| # | Severity | Finding | Where |
|---|---|---|---|
| S1 | Critical | `GET /migrate/data` runs `artisan migrate` with no login. Anyone who knows the URL can start migrations on the production database. | `GlobalController.php:100-103`, `routes/website.php:228` |
| S2 | Critical | `POST /ckeditor/upload` needs no login, checks no file type, and saves the file with the extension the client gave, under `public/uploads/ckeditor`. An attacker may be able to upload a script. Whether the web server would run it depends on the nginx configuration, which was not checked. | `GlobalController.php:76-92`, `routes/website.php:230` |
| S3 | Medium | `GET /optimize-clear` needs no login. Anyone can clear the application caches, which can slow the site down or expose a configuration error. | `routes/website.php:229` |

## 2. Recommended fixes

1. **S1 and S3:** remove both routes. Run migrations and cache commands from the command line during a deployment, never over HTTP. If a route must stay, put it behind the admin login and a permission, and use POST.
2. **S2:** require a signed-in user with the right role; accept only an allow-list of image types (check the real content, not only the name); generate the file name on the server and ignore the client's extension; limit the size; store the files where PHP is not executed.
3. **nginx:** deny PHP execution under `public/uploads` (for example a `location ~* ^/uploads/.*\.php$ { deny all; }` rule above the generic PHP rule), and serve uploads with `X-Content-Type-Options: nosniff`.

## 3. What to check now

- The files in `public/uploads/ckeditor`: any file that is not an image (especially `.php`, `.phtml`, `.phar`, `.html`, `.svg`) or an image that is unexpectedly small or large.
- The access logs for requests to `/migrate/data`, `/optimize-clear` and `/ckeditor/upload`, and for requests to files in `public/uploads` that end in a script extension. Look for addresses that are not yours.
- The `migrations` table and the database schema for changes nobody made.
- If anything suspicious turns up: take the site out of service, keep a copy of the files and logs, rotate the database and application secrets, and review the admin accounts.

## 4. Related points of lower priority

The code review found more problems of the same kind (authorisation checks missing on the kanban board, campaigns, job activation, screening questions and messages; actions that change data through GET; a plain-text password in the welcome email). The list is in `docs/analysis/legacy-findings.md`, items S4 to S13, and can be shared on request.
