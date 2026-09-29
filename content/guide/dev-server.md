---
title: 'Dev Server and Live Reload'
description: 'Preview your StaticForge site locally, and use --watch to rebuild on every change and reload the browser automatically.'
template: docs
menu: '2.2.5'
og_image: "A glowing browser window refreshing itself as a hammer strikes an anvil beside it, sparks turning into web pages, warm forge colors, --ar 16:9"
---

# Dev Server and Live Reload

Building a site, switching to the browser and pressing refresh gets old quickly. The StaticForge dev server serves your built site locally, and with `--watch` it also rebuilds when you save a file and reloads the open tab for you.

---

## Running the Dev Server

```bash
php bin/staticforge.php site:devserver
```

Open `http://localhost:8000`. The server is PHP's built-in web server pointed at your output directory (`public/` unless you changed `OUTPUT_DIR`). Press `Ctrl+C` to stop it.

| Option | Default | Purpose |
|--------|---------|---------|
| `--port`, `-p` | `8000` | Port to serve on. |
| `--host` | `localhost` | Host or address to bind to. |
| `--watch` | off | Rebuild when source files change and reload the browser. |
| `--allow-remote` | off | With `--watch`, allow binding an address other than localhost. |
| `--include-drafts` | off | With `--watch`, include drafts in the rebuilds. |

**The dev server does not build your site.** It only serves what is already in the output directory, and it stops with an error if that directory does not exist. Run `php bin/staticforge.php site:render` at least once first. This is true in watch mode too: `--watch` does not run a build when it starts. It waits for the first change.

---

## Watch Mode

```bash
php bin/staticforge.php site:devserver --watch
```

The terminal prints `Watching for changes...` and then checks for changes about twice a second.

### What It Watches

*   Everything under your content directory (`SOURCE_DIR`)
*   Everything under your templates directory (`TEMPLATE_DIR`)
*   `siteconfig.yaml`
*   Everything under `siteconfig.d/`
*   `.env`

A file counts as changed when its modification time or size changes. Symbolic links are not followed. The output directory is never watched, and neither is the dev server's own temporary directory.

### What Happens When Something Changes

1.  The dev server waits until changes stop for about 300 milliseconds, so saving several files at once triggers one build.
2.  It runs `site:render --incremental` in the background. Only one build runs at a time. If you save more files while a build is running, they are collected into a single follow-up build.
3.  The browser is told to reload once the build succeeds.

If you delete or rename a source file, the follow-up build runs with `--clean`. An incremental build would leave the old output file behind, and the dev server would keep serving it. Expect a brief window of 404s while the output directory is rebuilt.

Each rebuild prints one line in the terminal: the time, `OK` or `FAILED`, how long it took, the first changed file and how many others changed with it. When a change affects the whole site, the line says why: a config change (`siteconfig.yaml`, `siteconfig.d/`, `.env`), a template change, or a deleted or renamed source.

```text
[14:02:11] OK 0.8s content/guide/dev-server.md
[14:02:40] OK 3.1s templates/staticforce/base.html.twig [templates changed: full re-render]
[14:03:02] FAILED 0.4s content/index.md
```

### Drafts

Pass `--include-drafts` to have every rebuild include draft pages. It is handed to `site:render` as is, and it has no effect without `--watch`.

```bash
php bin/staticforge.php site:devserver --watch --include-drafts
```

### Stopping

`Ctrl+C` (or a termination signal) stops the server, ends any build in progress and removes the temporary files the dev server created. Catching `Ctrl+C` for a tidy shutdown relies on PHP's optional `pcntl` extension.

---

## What You See in the Browser

In watch mode the dev server adds a small script to every HTML page it serves. It is added on the fly. **Your `public/` directory never contains it**, so nothing you upload is affected.

*   **Automatic reload.** When a build finishes successfully the page reloads and your scroll position is restored.
*   **Forms are left alone.** If a form field has focus, the reload waits until you leave the field, so you never lose text you are typing.
*   **Rebuilding indicator.** A small `Rebuilding...` label appears in the bottom-right corner while a build runs.
*   **Failed build banner.** If a build fails, a red banner appears at the top of the page: "Build failed - showing last good version", followed by the first few lines of the error. The page underneath is the last successful build. Use the Dismiss button to hide it. It clears itself when a later build succeeds.
*   **Disconnected notice.** If the page cannot reach the dev server (for example, you stopped it), it shows `Dev server disconnected` and retries every few seconds.

The page checks for updates about once a second, and pauses while its tab is in the background. The full error text is always in the terminal, and the banner shows at most five lines with your project path shortened to `.`.

---

## Using Lando

Inside Lando, the container's `localhost` is usually not reachable from your browser, so bind to all interfaces:

```bash
lando php bin/staticforge.php site:devserver --watch --host=0.0.0.0
```

The rules for `--host` in watch mode are:

| `--host` | `--allow-remote` | Under Lando | Result |
|----------|------------------|-------------|--------|
| `localhost`, `127.0.0.1` or `::1` | any | any | Runs, no warning. |
| anything else | not given | no | Refuses to start. |
| anything else | not given | yes | Runs, with a warning that the site and build errors are reachable from the container network. |
| anything else | given | any | Runs, with a warning that the site and build errors are reachable from the network. |

Lando is detected through the `LANDO=ON` environment variable, which Lando sets inside its containers. Without `--watch`, `--host` behaves as it always has: no refusal and no warning.

The reload check only answers requests addressed to a known host name. Under Lando, StaticForge also accepts the site URLs Lando reports for the app, so the proxy address such as `https://your-app.lndo.site` works. If the page keeps showing `Dev server disconnected`, check that you are opening the site through the bound host or a Lando URL.

---

## Security Notes

The dev server is a local development tool. Never run it on a production server and never use it to host a site for real visitors.

*   **Local by default.** It binds to `localhost`, so only your own machine can reach it. In watch mode, binding anything else requires Lando or an explicit `--allow-remote`, because build errors and your unpublished site would be visible to others on the network.
*   **Only your output directory is served.** Requests are checked so that `../` tricks and symbolic links pointing outside the output directory return a 404. This applies with and without `--watch`.
*   **The status check is narrow.** The one extra URL, `/__staticforge/state`, only answers `GET` requests and only when the `Host` header is a name the server expects (`localhost`, `127.0.0.1`, `[::1]`, the address you bound, and under Lando the app's Lando URLs). This stops a malicious web page from reading your build errors through your browser.
*   **Nothing an HTTP request does starts a build.** Builds start only when the dev server sees a file change on disk.
*   **Private temporary files.** The generated router and status file live in a directory in your system temp directory that only your user can read, and it is removed on exit.

---

## 404 Pages

Any URL that does not exist returns `public/404.html` with a real `404` status. If the file has not been built yet, the server shows a plain built-in 404 page instead. In watch mode the reload script is added to the 404 page too. See [404 Pages](404-pages.html) for how to create yours.

---

## Limits

*   `--watch` is not supported on native Windows. Use WSL2 or Lando. The command exits with an error if you try.
*   Change detection polls the file system about twice a second rather than using OS notifications.
*   Rebuilds are incremental. If the output ever looks out of step with your source, stop the server and run `php bin/staticforge.php site:render --clean`.
*   Starting the server does not build anything. Until your first change, you see whatever is already in the output directory.
*   Watch builds set `SITE_BASE_URL` to `http://localhost:<port>/` for that build only, so pages load their CSS and JS from the dev server instead of your production address. Your `.env` is not changed. The output folder then holds localhost links until your next normal `site:render` (which rebuilds everything), so don't copy it to a server by hand. `site:upload` always re-renders with your upload URL and is not affected. Pages you have not changed yet still use the old address until the first rebuild; run `site:render --clean` with the server stopped, or edit any file, to refresh them.
*   The dev server only serves files that really live inside the output directory. A symbolic link in `public/` that points outside it, for example `public/media` linking to `../storage`, returns `404`. Earlier releases served it.
*   PHP files in the output directory are run, not sent as text. That is how PHP's built-in server has always worked, so you can still test a form handler there. Keep PHP out of `public/` if you do not want that.
*   The `--host` checks only apply in watch mode. Without `--watch`, `--host=0.0.0.0` binds to every interface with no warning.
*   The reload script is added inline. If your site sends a strict `Content-Security-Policy` that blocks inline scripts, the browser will not run it and the page will not reload by itself.

---

## Troubleshooting

**`Port 8000 is already in use`**

Another process is using that port, possibly another dev server. Stop it or choose a different port with `--port=8001`.

**`Public directory not found`**

Nothing has been built yet. Run `php bin/staticforge.php site:render`, then start the dev server again.

**`Cannot use --watch: OUTPUT_DIR ... is inside, equal to or a parent of SOURCE_DIR`** (or `TEMPLATE_DIR`)
: Every build writes into the output directory, which would count as a change and start another build, forever. Move `OUTPUT_DIR` outside your content and templates directories. See [Local Configuration](configuration.html).

**`Refusing to bind ... with --watch`**

You passed a non-localhost `--host` outside Lando. Use `localhost`, or add `--allow-remote` if you understand that others on your network can reach the server.

**`--watch is not supported on native Windows`**

Run the command from WSL2 or Lando.

**The page never reloads**

Check the terminal for a `FAILED` line, which means the build failed and the browser is still showing the last good version. Also confirm you passed `--watch`, and that the file you edited is under your content or templates directory.

---

## Next Steps

*   [Quick Start](quick-start.html) - Build your first site.
*   [404 Pages](404-pages.html) - Set up the page the dev server shows for missing URLs.
*   [Site Management & Deployment](site-management.html) - Build for real and upload your site.
*   [CLI Commands](cli-commands.html) - The full command reference.
