<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

/**
 * Live-reload client injected by DevServerRouter in watch mode only.
 * All DOM text goes through textContent; identifiers are prefixed "sfdev".
 */
final class ClientScript
{
    private const JS = <<<'JS'
(function () {
  if (window.sfdevLoaded) { return; }
  window.sfdevLoaded = true;
  var sfdevKey = 'sfdevScrollY', sfdevVer = null, sfdevWant = false, sfdevDelay = 1000, sfdevDismissed = null;
  try {
    var sfdevSaved = sessionStorage.getItem(sfdevKey);
    if (sfdevSaved !== null) { sessionStorage.removeItem(sfdevKey); window.scrollTo(0, +sfdevSaved || 0); }
  } catch (e) {}
  var sfdevHost = document.createElement('div');
  var sfdevRoot = sfdevHost.attachShadow({ mode: 'open' });
  var sfdevStyle = document.createElement('style');
  sfdevStyle.textContent = '[hidden]{display:none}div{font:14px/1.4 sans-serif;color:#fff;position:fixed;z-index:2147483647}'
    + '.s{right:12px;bottom:12px;background:#333;padding:6px 10px;border-radius:4px}'
    + '.b{left:0;right:0;top:0;background:#b00020;padding:10px 14px}pre{margin:6px 0 0;white-space:pre-wrap}button{margin-left:12px}';
  var sfdevStatus = document.createElement('div');
  sfdevStatus.className = 's'; sfdevStatus.setAttribute('role', 'status'); sfdevStatus.setAttribute('aria-live', 'polite'); sfdevStatus.hidden = true;
  var sfdevBanner = document.createElement('div');
  sfdevBanner.className = 'b'; sfdevBanner.setAttribute('role', 'alert'); sfdevBanner.hidden = true;
  var sfdevMsg = document.createElement('span'), sfdevErr = document.createElement('pre'), sfdevBtn = document.createElement('button');
  sfdevBtn.type = 'button'; sfdevBtn.textContent = 'Dismiss';
  sfdevBtn.addEventListener('click', function () { sfdevDismissed = sfdevErr.textContent; sfdevBanner.hidden = true; });
  sfdevBanner.append(sfdevMsg, sfdevBtn, sfdevErr);
  sfdevRoot.append(sfdevStyle, sfdevStatus, sfdevBanner);
  document.body.appendChild(sfdevHost);
  function sfdevBusy() {
    var a = document.activeElement;
    return !!a && (/^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) || a.isContentEditable);
  }
  function sfdevReload() {
    if (sfdevBusy()) { sfdevWant = true; return; }
    try { sessionStorage.setItem(sfdevKey, String(window.scrollY)); } catch (e) {}
    location.reload();
  }
  document.addEventListener('focusout', function () { if (sfdevWant) { setTimeout(sfdevReload, 0); } });
  function sfdevApply(s) {
    var text = s.status === 'building' ? 'Rebuilding...' : '';
    sfdevStatus.textContent = text; sfdevStatus.hidden = text === '';
    if (s.status === 'failed') {
      sfdevMsg.textContent = '⚠ Build failed - showing last good version';
      sfdevErr.textContent = s.error || '';
      sfdevBanner.hidden = sfdevDismissed === sfdevErr.textContent;
    } else { sfdevBanner.hidden = true; sfdevDismissed = null; }
    if (sfdevVer === null) { sfdevVer = s.v; } else if (s.v !== sfdevVer) { sfdevWant = true; }
    if (sfdevWant) { sfdevReload(); }
  }
  function sfdevPoll() {
    if (document.hidden) { setTimeout(sfdevPoll, sfdevDelay); return; }
    fetch('/__staticforge/state', { cache: 'no-store' })
      .then(function (r) { if (!r.ok) { throw new Error('state'); } return r.json(); })
      .then(function (s) { sfdevDelay = 1000; sfdevApply(s); })
      .catch(function () {
        sfdevDelay = 5000; sfdevStatus.textContent = 'Dev server disconnected'; sfdevStatus.hidden = false;
      })
      .then(function () { setTimeout(sfdevPoll, sfdevDelay); });
  }
  sfdevPoll();
})();
JS;

    public static function tag(): string
    {
        return '<script>' . self::JS . '</script>';
    }
}
