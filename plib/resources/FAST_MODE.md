# Bahman sitefile FAST_MODE (sales / peak traffic)

Reversible performance mode for `sitefile.bahman.ir`.

## What it does
- Skips PHP sessions on file/thumb requests (stops `Set-Cookie` + `Cache-Control: no-store`)
- Caches file metadata + type lists on disk for a few minutes
- Sends public cache headers for images/videos
- Works with Apache `Header unset Set-Cookie` in `.htaccess`

## Toggle
Flag file:

`/var/www/vhosts/siteapi.bahman.ir/httpdocs/file_manager/.FAST_MODE`

| Action | Command / step |
|--------|----------------|
| **ON** | create empty `.FAST_MODE` in `file_manager/` |
| **OFF** | delete `.FAST_MODE` |

No Apache restart required; PHP picks it up on the next request.

## Files involved
- `file_manager/file.php` — FAST_MODE logic
- `file_manager/.htaccess` — unset Set-Cookie
- `file_manager/.FAST_MODE` — on/off switch
- Backup of previous `file.php`: `file.php.bak-before-fast-*`

## Note
OPcache for `sitefile.bahman.ir` should stay enabled (separate from this flag).
