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
3. Configure your web server to expose the file via the `RR_CONFIG_PATH` environment variable (see [Secrets](#secrets) below).
4. Run the DB bootstrap:
   - CLI: `php db/migrate.php`
   - or import `db/schema.sql` manually
5. Deploy the code into your web root.
6. Hit the API to verify:
   - `GET /api/rooms/TEST/state?since=-1` → JSON
   - `POST /api/rooms/TEST/bid` with `{"playerId":123,"value":12}` → starts 60s countdown

### Secrets

- Database credentials live in a private PHP array file (e.g., `/home/USER/secure/rr-config.php`) and must **never** be committed to Git.
- Set secure file permissions on your config file:
  ```bash
  chmod 600 /home/USER/secure/rr-config.php
  ```
- Set the environment variable for Apache by adding this line to your `.htaccess` file:
  ```apache
  SetEnv RR_CONFIG_PATH /home/USER/secure/rr-config.php
  ```

- `api/db.php` loads configuration in order: the path from `RR_CONFIG_PATH`, then `config.php` (gitignored), and finally `config.sample.php` as a last resort.

### DreamHost Setup

For DreamHost shared hosting:

1. Create your config file at `/home/USERNAME/secure/rr-config.php` (replace USERNAME with your actual username)
2. Set secure file permissions:
   ```bash
   chmod 600 /home/USERNAME/secure/rr-config.php
   ```
3. Add this line to your `.htaccess` file in the web root:
   ```apache
   SetEnv RR_CONFIG_PATH /home/USERNAME/secure/rr-config.php
   ```
4. Make sure your config file has the correct format:
   ```php
   <?php
   return [
     'db_host'    => 'mysql.dreamhost.com',  // or your database host
     'db_name'    => 'your_database_name',
     'db_user'    => 'your_database_user', 
     'db_pass'    => 'your_database_password',
     'db_port'    => 3306,
     'db_charset' => 'utf8mb4',
   ];
   ```

## Pretty API routes

The `.htaccess` rewrites map pretty URLs to PHP files:

- `/api/rooms/{code}/state` → `api/state.php?code={code}`
- `/api/rooms/{code}/bid`   → `api/bid.php?code={code}`

If rewrites are disabled, you can call the PHP files directly.

## License

MIT (see LICENSE)
