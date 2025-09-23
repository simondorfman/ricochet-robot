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
2. Copy `config.sample.php` to a **private** location (outside the web root), fill credentials, and set an env var pointing to it:
   - e.g. in Apache: `SetEnv RR_CONFIG_PATH /home/youruser/secure/rr-config.php`
3. Run the DB bootstrap:
   - CLI: `php db/migrate.php`
   - or import `db/schema.sql` manually
4. Deploy the code into your web root.
5. Hit the API to verify:
   - `GET /api/rooms/TEST/state?since=-1` → JSON
   - `POST /api/rooms/TEST/bid` with `{"playerId":123,"value":12}` → starts 60s countdown

## Pretty API routes

The `.htaccess` rewrites map pretty URLs to PHP files:

- `/api/rooms/{code}/state` → `api/state.php?code={code}`
- `/api/rooms/{code}/bid`   → `api/bid.php?code={code}`

If rewrites are disabled, you can call the PHP files directly.

## License

MIT (see LICENSE)
