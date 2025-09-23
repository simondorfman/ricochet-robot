# Ricochet Robots — Web Multiplayer (work-in-progress)

This is an experimental, web-based multiplayer adaptation of the board game **Ricochet Robots**  
(see the original on BoardGameGeek: https://boardgamegeek.com/boardgame/51/ricochet-robots).

The goal is to let a group on a video call open a shared room URL, bid on solutions, and
play synchronized rounds with a server-authoritative timer (first bid starts 60s; lower bids
do not reset the timer), then verify solutions in order of lowest bid.

## Credits

This project is forked from: https://github.com/fabiottini/ricochet-robot  
Huge thanks to the original author for the code and the generous MIT license.

## Status

Work in progress. Current MVP includes:
- Server-authoritative countdown with polling/long-polling endpoints
- Lowest-bid tracking
- Demo UI for basic interaction

## Install (quick start)

1. Create a MySQL database and user on your host.
2. Copy `config.sample.php` to a **private** location outside the web root (for example `/home/USER/secure/rr-config.php`) and fill in your credentials.
3. Configure your web server to expose the file via the `RR_CONFIG_PATH` environment variable (see [Secrets](#secrets)).
4. Run the DB bootstrap:
   - CLI: `php db/migrate.php`
   - or import `db/schema.sql` manually
5. Deploy the code into your web root.
6. Hit the API to verify:
   - `GET /api/rooms/TEST/state?since=-1` → JSON
   - `POST /api/rooms/TEST/bid` with `{"playerId":123,"value":12}` → starts 60s countdown

### Secrets

- Database credentials live in a private PHP array file (e.g., `/home/USER/secure/rr-config.php`) and must **never** be committed to Git.
- Set the environment variable for Apache by adding the line below to `.htaccess.local` (which is ignored by Git):

  ```apache
  SetEnv RR_CONFIG_PATH /home/USER/secure/rr-config.php
  ```

- The repository’s tracked `.htaccess` automatically includes `.htaccess.local` via `IncludeOptional`, so each server can keep its own overrides.
- `api/db.php` loads configuration in order: the path from `RR_CONFIG_PATH`, then `config.php` (gitignored), and finally `config.sample.php` as a last resort.

## Pretty API routes

The `.htaccess` rewrites map pretty URLs to PHP files:

- `/api/rooms/{code}/state` → `api/state.php?code={code}`
- `/api/rooms/{code}/bid`   → `api/bid.php?code={code}`

If rewrites are disabled, you can call the PHP files directly.

## License

MIT (see LICENSE)
