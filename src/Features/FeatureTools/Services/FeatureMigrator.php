<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\FeatureTools\Services;

use EICC\StaticForge\Features\FeatureTools\Models\MigrationResult;
use PhpToken;

/**
 * Converts a pre-3.0 Feature.php from the array-based event contract
 * (`protected array $eventListeners`, `handle(Container, array): array`)
 * to the typed-event contract (`#[EventListener]`, `handle(SomeEvent): void`).
 *
 * Deliberately conservative: if any part of a file doesn't match the exact
 * shape every real pre-3.0 Feature in this ecosystem used, the whole file is
 * skipped with a diagnostic rather than partially — and possibly
 * incorrectly — rewritten. See migrating-to-3-0.html for the manual
 * recipe this tool automates.
 */
class FeatureMigrator
{
    /**
     * Old event name => typed Event class (short name, in EICC\StaticForge\Core\Events).
     */
    private const EVENT_CLASS_MAP = [
        'CREATE' => 'Event',
        'PRE_GLOB' => 'Event',
        'POST_GLOB' => 'Event',
        'PRE_LOOP' => 'Event',
        'PRE_RENDER' => 'RenderEvent',
        'RENDER' => 'RenderEvent',
        'POST_RENDER' => 'RenderEvent',
        'MARKDOWN_CONVERTED' => 'RenderEvent',
        'POST_LOOP' => 'Event',
        'DESTROY' => 'Event',
        'CONSOLE_INIT' => 'ConsoleInitEvent',
        'COLLECT_MENU_ITEMS' => 'CollectMenuItemsEvent',
        'ROBOTS_TXT_BUILDING' => 'RobotsTxtBuildingEvent',
        'RSS_BUILDER_INIT' => 'RssBuilderInitEvent',
        'RSS_ITEM_BUILDING' => 'RssItemBuildingEvent',
        'SEO_AUDIT_PAGE' => 'SeoAuditPageEvent',
        'UPLOAD_CHECK_FILE' => 'UploadCheckFileEvent',
    ];

    /**
     * Old array key => new property name, per event class. Only keys
     * confirmed against a real pre-3.0 Feature this project actually
     * migrated are listed. Anything else found in a handler body is either
     * routed to $event->extra[...] (RenderEvent only) or flagged as a
     * manual TODO (every other event class has no such bag).
     */
    private const FIELD_MAP = [
        'RenderEvent' => [
            'file_path' => 'filePath',
            'file_url' => 'fileUrl',
            'metadata' => 'metadata',
            'file_metadata' => 'metadata',
            'rendered_content' => 'renderedContent',
            'html_content' => 'renderedContent',
            'output_path' => 'outputPath',
        ],
        'ConsoleInitEvent' => [
            'application' => 'application',
        ],
        'RobotsTxtBuildingEvent' => [
            'rules' => 'rules',
        ],
        'RssBuilderInitEvent' => [
            'builder' => 'builder',
            'category_metadata' => 'categoryMetadata',
        ],
        'RssItemBuildingEvent' => [
            'item' => 'item',
            'file' => 'file',
        ],
        'SeoAuditPageEvent' => [
            'crawler' => 'crawler',
            'filename' => 'filename',
            'issues' => 'issues',
        ],
        'UploadCheckFileEvent' => [
            'path' => 'path',
            'local_path' => 'localPath',
            'target_path' => 'targetPath',
            'current_hash' => 'currentHash',
            'remote_hash' => 'remoteHash',
            'should_upload' => 'shouldUpload',
            'skip_upload' => 'skipUpload',
            'handled' => 'handled',
        ],
        'CollectMenuItemsEvent' => [
            'menu_data' => 'menuData',
            'menuData' => 'menuData',
        ],
    ];

    private const HAS_EXTRA_BAG = ['RenderEvent'];

    public function migrateFile(string $filePath): MigrationResult
    {
        $source = file_get_contents($filePath);
        if ($source === false) {
            return new MigrationResult($filePath, false, true, 'Could not read file.', '', '', 0);
        }

        if (str_contains($source, '#[EventListener(') && !str_contains($source, 'protected array $eventListeners')) {
            return new MigrationResult($filePath, true, false, null, $source, $source, 0);
        }

        $tokens = PhpToken::tokenize($source);

        $listenersProperty = $this->findEventListenersProperty($tokens, $source);
        if ($listenersProperty === null) {
            return new MigrationResult(
                $filePath,
                false,
                true,
                "No 'protected array \$eventListeners' property found, and no #[EventListener] attributes either — " .
                "is this really a Feature.php? Nothing to do.",
                $source,
                $source,
                0
            );
        }

        $entries = $this->parseEventListenersEntries($listenersProperty['literal']);
        if ($entries === null) {
            return new MigrationResult(
                $filePath,
                false,
                true,
                "Could not parse \$eventListeners — it doesn't match the standard " .
                "'EVENT' => ['method' => 'x', 'priority' => N] shape. Migrate this file by hand.",
                $source,
                $source,
                0
            );
        }

        $warnings = [];
        $edits = [];
        $eventClassesUsed = [];

        foreach ($entries as $entry) {
            $eventName = $entry['event'];
            $methodName = $entry['method'];

            if (!isset(self::EVENT_CLASS_MAP[$eventName])) {
                return new MigrationResult(
                    $filePath,
                    false,
                    true,
                    "Unknown event '{$eventName}' (handler {$methodName}()) — this tool only knows the " .
                    "built-in StaticForge events. Migrate this file by hand; see the event table in " .
                    "migrating-to-3-0.html.",
                    $source,
                    $source,
                    0
                );
            }

            $eventClass = self::EVENT_CLASS_MAP[$eventName];
            $method = $this->findMethod($tokens, $source, $methodName);

            if ($method === null) {
                return new MigrationResult(
                    $filePath,
                    false,
                    true,
                    "Could not find a matching 'public function {$methodName}(...)' for the '{$eventName}' " .
                    "listener. Migrate this file by hand.",
                    $source,
                    $source,
                    0
                );
            }

            $parsedParams = $this->parseOldParams($method['paramsText']);
            if ($parsedParams === null) {
                return new MigrationResult(
                    $filePath,
                    false,
                    true,
                    "Handler {$methodName}({$method['paramsText']}) doesn't match either the " .
                    "'(Container \$x, array \$y)' or '(Container \$x)' shape this tool knows how to convert. " .
                    "Migrate this file by hand.",
                    $source,
                    $source,
                    0
                );
            }

            $eventClassesUsed[$eventClass] = true;

            // Replace any docblock immediately above the handler (it describes the old
            // Container/array signature and would be actively wrong afterward) plus the
            // signature itself, with the new attribute + typed signature.
            $docblockStart = $this->extendBackToDocblock($source, $method['lineStart']);
            $newSignature = "{$method['indent']}#[EventListener('{$eventName}', priority: {$entry['priority']})]\n"
                . "{$method['indent']}{$method['visibility']} function {$methodName}({$eventClass} \$event): void ";
            $edits[] = ['start' => $docblockStart, 'end' => $method['sigEnd'], 'text' => $newSignature];

            // Body substitutions.
            $bodyText = substr($source, $method['bodyStart'], $method['bodyEnd'] - $method['bodyStart']);
            $newBody = $this->transformBody(
                $bodyText,
                $eventClass,
                $parsedParams['arrayVar'],
                $parsedParams['containerVar'],
                $warnings,
                $methodName
            );
            $edits[] = ['start' => $method['bodyStart'], 'end' => $method['bodyEnd'], 'text' => $newBody];
        }

        // Remove the old $eventListeners property entirely, including its docblock.
        $propertyStart = $this->extendBackToDocblock($source, $listenersProperty['start']);
        $edits[] = ['start' => $propertyStart, 'end' => $listenersProperty['end'], 'text' => ''];

        // Simplify register(EventManager $x, Container $y) to register(EventManager $x), if present.
        $registerFix = $this->fixRegisterSignature($tokens, $source);
        if ($registerFix !== null) {
            $edits[] = $registerFix;
        }

        // Apply edits back-to-front so earlier byte offsets stay valid.
        usort($edits, fn ($a, $b) => $b['start'] <=> $a['start']);
        $result = $source;
        foreach ($edits as $edit) {
            $result = substr_replace($result, $edit['text'], $edit['start'], $edit['end'] - $edit['start']);
        }

        $result = $this->ensureImports($result, $eventClassesUsed);

        // Deletions (docblocks, the old $eventListeners property) tend to leave stacked
        // blank lines behind; collapse any run of 2+ blank lines down to exactly one.
        // Cosmetic only — a subsequent phpcbf run cleans up anything this misses.
        $result = preg_replace("/\n{3,}/", "\n\n", $result) ?? $result;

        return new MigrationResult($filePath, false, false, null, $source, $result, count($entries), $warnings);
    }

    /**
     * @param PhpToken[] $tokens
     * @return array{start: int, end: int, literal: string}|null
     */
    private function findEventListenersProperty(array $tokens, string $source): ?array
    {
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i]->text !== '$eventListeners') {
                continue;
            }

            // Walk back to confirm this is the property declaration, and forward to the '='.
            $j = $i + 1;
            while ($j < $count && trim($tokens[$j]->text) === '') {
                $j++;
            }
            if ($j >= $count || $tokens[$j]->text !== '=') {
                continue;
            }

            // Find the array literal's opening bracket ('[' or 'array(').
            $j++;
            while ($j < $count && trim($tokens[$j]->text) === '') {
                $j++;
            }
            $openBracket = $tokens[$j]->text === 'array' ? '(' : '[';
            $closeBracket = $openBracket === '(' ? ')' : ']';
            while ($j < $count && $tokens[$j]->text !== $openBracket) {
                $j++;
            }
            if ($j >= $count) {
                continue;
            }

            $literalStart = $tokens[$j]->pos;
            $depth = 0;
            $literalEnd = null;
            for ($k = $j; $k < $count; $k++) {
                if ($tokens[$k]->text === $openBracket) {
                    $depth++;
                } elseif ($tokens[$k]->text === $closeBracket) {
                    $depth--;
                    if ($depth === 0) {
                        $literalEnd = $tokens[$k]->pos + strlen($tokens[$k]->text);
                        break;
                    }
                }
            }
            if ($literalEnd === null) {
                continue;
            }

            // Property declaration starts at the beginning of its line (covers any
            // leading docblock-less "protected array $eventListeners" text) and ends
            // just after the trailing ';'.
            $semicolonEnd = $literalEnd;
            for ($m = $k; $m < $count; $m++) {
                if ($tokens[$m]->text === ';') {
                    $semicolonEnd = $tokens[$m]->pos + 1;
                    break;
                }
            }

            $lineStart = $this->startOfLine($source, $tokens[$i]->pos);

            return [
                'start' => $lineStart,
                'end' => $semicolonEnd,
                'literal' => substr($source, $literalStart, $literalEnd - $literalStart),
            ];
        }

        return null;
    }

    /**
     * @return array<int, array{event: string, method: string, priority: int}>|null
     */
    private function parseEventListenersEntries(string $literal): ?array
    {
        $pattern = "/'([A-Z_]+)'\\s*=>\\s*\\[\\s*'method'\\s*=>\\s*'([a-zA-Z0-9_]+)'\\s*,\\s*" .
            "'priority'\\s*=>\\s*(\\d+)\\s*\\]/";
        if (preg_match_all($pattern, $literal, $matches, PREG_SET_ORDER) === false) {
            return null;
        }

        // Reject the whole file if the literal has content preg couldn't account for
        // (a different shape mixed in) — count matched entries vs '=>' occurrences.
        $arrowCount = substr_count($literal, '=>');
        if (count($matches) === 0 || count($matches) * 3 !== $arrowCount) {
            return null;
        }

        $entries = [];
        foreach ($matches as $match) {
            $entries[] = [
                'event' => $match[1],
                'method' => $match[2],
                'priority' => (int) $match[3],
            ];
        }

        return $entries;
    }

    /**
     * @param PhpToken[] $tokens
     * @return array{
     *     sigStart: int, sigEnd: int, lineStart: int, indent: string,
     *     visibility: string, paramsText: string, bodyStart: int, bodyEnd: int
     * }|null
     */
    private function findMethod(array $tokens, string $source, string $methodName): ?array
    {
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i]->text !== 'function') {
                continue;
            }
            $j = $i + 1;
            while ($j < $count && trim($tokens[$j]->text) === '') {
                $j++;
            }
            if ($j >= $count || $tokens[$j]->text !== $methodName) {
                continue;
            }

            // Visibility modifier: walk back over whitespace to the previous token.
            $v = $i - 1;
            while ($v >= 0 && trim($tokens[$v]->text) === '') {
                $v--;
            }
            $visibility = $v >= 0 ? $tokens[$v]->text : 'public';
            if (!in_array($visibility, ['public', 'protected', 'private'], true)) {
                $visibility = 'public';
            }

            // Params: from the '(' after the method name to its matching ')'.
            $p = $j + 1;
            while ($p < $count && trim($tokens[$p]->text) === '') {
                $p++;
            }
            if ($p >= $count || $tokens[$p]->text !== '(') {
                return null;
            }
            $paramsOpenIdx = $p;
            $depth = 0;
            $paramsCloseIdx = null;
            for ($k = $paramsOpenIdx; $k < $count; $k++) {
                if ($tokens[$k]->text === '(') {
                    $depth++;
                } elseif ($tokens[$k]->text === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $paramsCloseIdx = $k;
                        break;
                    }
                }
            }
            if ($paramsCloseIdx === null) {
                return null;
            }
            $paramsText = substr(
                $source,
                $tokens[$paramsOpenIdx]->pos + 1,
                $tokens[$paramsCloseIdx]->pos - $tokens[$paramsOpenIdx]->pos - 1
            );

            // Body: the next '{' after the params/return-type, to its matching '}'.
            $b = $paramsCloseIdx + 1;
            while ($b < $count && $tokens[$b]->text !== '{') {
                $b++;
            }
            if ($b >= $count) {
                return null;
            }
            $bodyOpenIdx = $b;
            $depth = 0;
            $bodyCloseIdx = null;
            for ($k = $bodyOpenIdx; $k < $count; $k++) {
                if ($tokens[$k]->text === '{') {
                    $depth++;
                } elseif ($tokens[$k]->text === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $bodyCloseIdx = $k;
                        break;
                    }
                }
            }
            if ($bodyCloseIdx === null) {
                return null;
            }

            $lineStart = $this->startOfLine($source, $tokens[$v >= 0 ? $v : $i]->pos);
            $indent = substr($source, $lineStart, $tokens[$v >= 0 ? $v : $i]->pos - $lineStart);

            return [
                'sigStart' => $tokens[$v >= 0 ? $v : $i]->pos,
                'sigEnd' => $tokens[$bodyOpenIdx]->pos,
                'lineStart' => $lineStart,
                'indent' => $indent,
                'visibility' => $visibility,
                'paramsText' => $paramsText,
                'bodyStart' => $tokens[$bodyOpenIdx]->pos + 1,
                'bodyEnd' => $tokens[$bodyCloseIdx]->pos,
            ];
        }

        return null;
    }

    /**
     * @return array{containerVar: ?string, arrayVar: ?string}|null
     */
    private function parseOldParams(string $paramsText): ?array
    {
        $paramsText = trim($paramsText);

        $containerVar = null;
        $arrayVar = null;
        $remaining = $paramsText;

        if (preg_match('/Container\s+\$(\w+)/', $remaining, $m)) {
            $containerVar = $m[1];
            $remaining = trim(str_replace($m[0], '', $remaining), ", \t\n");
        }

        if (preg_match('/array\s+\$(\w+)/', $remaining, $m)) {
            $arrayVar = $m[1];
            $remaining = trim(str_replace($m[0], '', $remaining), ", \t\n");
        }

        if ($remaining !== '') {
            // Something else is in the param list this tool doesn't recognize.
            return null;
        }

        if ($containerVar === null && $arrayVar === null) {
            return null;
        }

        return ['containerVar' => $containerVar, 'arrayVar' => $arrayVar];
    }

    /**
     * @param string[] $warnings
     */
    private function transformBody(
        string $body,
        string $eventClass,
        ?string $arrayVar,
        ?string $containerVar,
        array &$warnings,
        string $methodName
    ): string {
        $fieldMap = self::FIELD_MAP[$eventClass] ?? [];
        $hasExtraBag = in_array($eventClass, self::HAS_EXTRA_BAG, true);

        if ($arrayVar !== null) {
            if (
                $eventClass === 'RenderEvent'
                && preg_match('/\$' . preg_quote($arrayVar, '/') . "\\['file_metadata'\\]/", $body)
                && preg_match('/\$' . preg_quote($arrayVar, '/') . "\\['metadata'\\]/", $body)
            ) {
                $warnings[] = "{$methodName}(): used both 'file_metadata' and 'metadata' — RenderEvent has a " .
                    'single unified $event->metadata now, so both now write the same property. Harmless, but ' .
                    'the second write is redundant; consider deleting it by hand.';
            }

            $body = preg_replace_callback(
                '/\$' . preg_quote($arrayVar, '/') . "\\['([a-zA-Z0-9_]+)'\\]/",
                function (array $m) use ($fieldMap, $hasExtraBag, &$warnings, $methodName) {
                    $key = $m[1];
                    if (isset($fieldMap[$key])) {
                        return '$event->' . $fieldMap[$key];
                    }
                    if ($hasExtraBag) {
                        $warnings[] = "{$methodName}(): auto-mapped '{$key}' to \$event->extra['{$key}'] — " .
                            'verify this is intended.';
                        return "\$event->extra['{$key}']";
                    }
                    $warnings[] = "{$methodName}(): no known property for '{$key}' on this event — " .
                        'left as a TODO, needs manual fix.';
                    return "/* TODO(feature:migrate): '{$key}' has no equivalent property */ \$event->{$key}";
                },
                $body
            ) ?? $body;

            // Every "return $arrayVar;" — not just a trailing one; early returns of the
            // array are common (guard clauses) — becomes a bare "return;" now that the
            // handler is void. Anything returning a more complex expression involving
            // the array var (not just the bare variable) won't match and is left alone,
            // flagged below so it doesn't silently stay broken.
            $bareReturnPattern = '/\breturn\s+\$' . preg_quote($arrayVar, '/') . '\s*;/';
            $bareReturnCount = preg_match_all($bareReturnPattern, $body);
            $body = preg_replace($bareReturnPattern, 'return;', $body) ?? $body;

            if (preg_match('/\breturn\s+\$' . preg_quote($arrayVar, '/') . '\b/', $body)) {
                $warnings[] = "{$methodName}(): a 'return \${$arrayVar} ...' with a non-bare expression " .
                    'was left unchanged — this handler is now void, fix this return statement by hand.';
            }

            if ($bareReturnCount > 1) {
                $warnings[] = "{$methodName}(): {$bareReturnCount} early-return guard clauses converted to " .
                    'bare "return;" — double check the logic still reads correctly.';
            }
        }

        if ($containerVar !== null && preg_match('/\$' . preg_quote($containerVar, '/') . '\b/', $body)) {
            $warnings[] = "{$methodName}(): still references \${$containerVar} — Container is no longer passed " .
                'to handlers. Constructor-inject it and update these references by hand.';
            $body = "\n        // TODO(feature:migrate): \${$containerVar} is no longer passed to this handler — " .
                "constructor-inject Container (see migrating-to-3-0.html) and update the reference(s) below.\n" .
                $body;
        }

        return $body;
    }

    /**
     * @param PhpToken[] $tokens
     * @return array{start: int, end: int, text: string}|null
     */
    private function fixRegisterSignature(array $tokens, string $source): ?array
    {
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i]->text !== 'register') {
                continue;
            }
            $j = $i + 1;
            while ($j < $count && trim($tokens[$j]->text) === '') {
                $j++;
            }
            if ($j >= $count || $tokens[$j]->text !== '(') {
                continue;
            }
            $depth = 0;
            $closeIdx = null;
            for ($k = $j; $k < $count; $k++) {
                if ($tokens[$k]->text === '(') {
                    $depth++;
                } elseif ($tokens[$k]->text === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $closeIdx = $k;
                        break;
                    }
                }
            }
            if ($closeIdx === null) {
                continue;
            }

            $paramsText = substr($source, $tokens[$j]->pos + 1, $tokens[$closeIdx]->pos - $tokens[$j]->pos - 1);
            if (!preg_match('/EventManager\s+\$(\w+)\s*,\s*Container\s+\$\w+/', $paramsText, $m)) {
                continue;
            }

            return [
                'start' => $tokens[$j]->pos + 1,
                'end' => $tokens[$closeIdx]->pos,
                'text' => "EventManager \${$m[1]}",
            ];
        }

        return null;
    }

    /**
     * @param array<string, bool> $eventClassesUsed
     */
    private function ensureImports(string $source, array $eventClassesUsed): string
    {
        $needed = ['EventListener' => true] + $eventClassesUsed;
        $inserted = [];

        foreach (array_keys($needed) as $class) {
            $fqcn = "EICC\\StaticForge\\Core\\Events\\{$class}";
            if (str_contains($source, "use {$fqcn};")) {
                continue;
            }
            $inserted[] = "use {$fqcn};";
        }

        if (empty($inserted)) {
            return $source;
        }

        // Insert right after the namespace declaration's semicolon (not its trailing
        // whitespace, which is handled explicitly below to avoid stacking blank lines).
        if (preg_match('/^namespace\s+[^;]+;/m', $source, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = $m[0][1] + strlen($m[0][0]);
            $block = "\n\n" . implode("\n", $inserted);
            return substr_replace($source, $block, $insertAt, 0);
        }

        return $source;
    }

    private function startOfLine(string $source, int $pos): int
    {
        $newlinePos = strrpos(substr($source, 0, $pos), "\n");
        return $newlinePos === false ? 0 : $newlinePos + 1;
    }

    /**
     * If a /** ... *\/ docblock sits immediately above $lineStart (only whitespace
     * between them), return its own start-of-line offset so callers can delete it
     * along with whatever it was describing. Otherwise return $lineStart unchanged.
     */
    private function extendBackToDocblock(string $source, int $lineStart): int
    {
        $before = rtrim(substr($source, 0, $lineStart));
        if (!str_ends_with($before, '*/')) {
            return $lineStart;
        }

        $docStart = strrpos($before, '/**');
        if ($docStart === false) {
            return $lineStart;
        }

        return $this->startOfLine($source, $docStart);
    }
}
