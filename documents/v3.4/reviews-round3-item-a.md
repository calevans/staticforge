# Item A (dev server watch mode) — implementation review findings

Code-reviewer findings (verified by reading code; one scan-time measurement). Decisions marked FIX go to the fix pass; NOTE = documented, no code.

1. FIX: `runLoop` ends when the server process stops without a final drain of both pipes or a flush of partial line buffers; a failed bind ("Failed to listen") is swallowed and the command returns SUCCESS. Drain, flush, return FAILURE when the server exits unexpectedly. Cap line buffers.
2. FIX: a failed build prints only `[time] FAILED 1.2s path`; the reason goes only to state.json. Print the extracted error (capped, app_root replaced) to the console.
3. FIX: ProcessBuildRunner keeps the HEAD of the output (64 KB cap) but errors are at the tail; keep a rolling tail. Drain pipes more often than the 500 ms tick.
4. FIX: FileSignatureProvider: 127 ms per scan at 40k files (native ext4, warm); far worse on Lando/WSL mounts. Use an adaptive interval max(500 ms, 5 x last scan time). Ignore editor swap/temp files (.swp, .swo, ~ suffix, .#*, 4913, .DS_Store, *.tmp). NOTE: mtime:size has 1 s granularity; two same-size saves within one second can be missed. NOTE: symlinked content files/dirs are not watched (lstat).
5. NOTE (owner-visible behavior change, from SEC-1/SEC2-3): a symlink inside public/ that points outside it (e.g. public/media -> ../storage) used to be served and now returns 404, in the default non-watch mode too. Document in whats-new and dev-server guide.
6. FIX: with `--host 0.0.0.0 --allow-remote` browsers send the LAN IP/hostname as Host, so `/__staticforge/state` returns 403 and the page shows "Dev server disconnected". With --allow-remote, also accept any IPv4/IPv6 LITERAL Host (DNS rebinding needs a hostname); hostnames still need to be the bound host, localhost or a Lando URL.
7. FIX: WatchLoop clears `changed`/`deleted` when it issues a request; a failed `--clean` build leaves a partial output dir and the next build is incremental. Keep the clean requirement pending until a build succeeds. Do not auto-retry on failure (avoid loops).
8. FIX: `writeState` throwing inside the loop kills the server through the catch: catch, log, continue. PrivateStateDir::write leaks its temp file if chmod throws: clean up. NOTE: without ext-pcntl a signal death leaves the temp dir and child running (same as the old code); cleanup may block ~4 s.
9. NOTE: injected HTML always 200 full body (Range ignored, allowed); `strripos('</body>')` can hit a `</body>` inside an inline script string (rare); a strict CSP on the site blocks the injected inline script so reload silently does nothing — document.
10. SKIP: WatchLoop/FileSignatureProvider are constructed inline in runLoop (only runner and clock injectable); `??=` caches a runner built with the first call's --include-drafts. Acceptable; revisit if tests need it.

# Security-auditor findings for item A (reproduced against a real server)

Clean: traversal (all encodings, symlinks out of docroot, absolute-URI targets), Host allowlist (foreign/trailing-dot/userinfo/X-Forwarded-Host), GET-only state endpoint, private dir (0700, random, lstat verified, no symlink following), argv-array proc_open, client script (textContent, shadow root), SIGINT/SIGTERM cleanup.

- M1 FIX: SIGHUP (closed terminal / SSH drop) orphans `php -S` and leaves the state dir (reproduced). Subscribe SIGHUP and SIGQUIT too (getSubscribedSignals DevServerCommand.php ~462).
- L1 DECISION (left as before): `.php` files in the docroot are executed by php -S in both modes (pre-existing; reproduced). Not changed: some sites test PHP handlers through the dev server. Document in the dev-server guide.
- L2 FIX: both children inherit the full environment (SFTP_PASSWORD, key passphrase, LANDO_* keys, GPG_KEYS). Server child (php -S + router) gets an explicit minimal $env (PATH, HOME, TMPDIR, LANG, LC_*); the parent computes the Lando allowed hosts from LANDO_INFO and passes them to the router constructor instead of the router reading LANDO_INFO/LANDO itself. The build child keeps the full environment (same as a manual site:render). PHP_CLI_SERVER_WORKERS intentionally not used.
- L3 FIX: same as review finding 3 (keep tail, ring buffer).
- L4 FIX: escape server log lines before printing (OutputFormatter::escape or OUTPUT_RAW).
- I1 NOTE: `--host` guard only applies with --watch; non-watch `--host=0.0.0.0` binds publicly with no warning (router containment protects it). Document.
- I2 FIX (cheap): in the state endpoint error text also mask the user's home directory and the temp dir, not just app_root.
- Side effect to check: the audit's live run of `--watch` started a `site:render --incremental` against /app/public, i.e. a rebuild fired at startup or from the audit's own file touches. Investigate whether watch mode builds at startup unexpectedly (design says no initial build).
