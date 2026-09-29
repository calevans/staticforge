<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Characterization\Html;

/**
 * Shared HTML fragment corpus for the DOM characterization tests.
 *
 * Heading fragments use the shape CommonMark's HeadingPermalinkExtension emits
 * (`<h2><a id=".." href="#.." class="heading-permalink">..</a>Title</h2>`) because that is what
 * TableOfContentsService and MarkdownRendererService::fixHeadingIds actually receive.
 *
 * Changing or adding a case requires regenerating the goldens (UPDATE_GOLDEN=1); removing a case
 * requires deleting its golden files. The *GoldenSet tests fail on orphaned or missing goldens.
 */
final class HtmlCorpus
{
    /**
     * @return array<string, string> case name => HTML fragment
     */
    public static function cases(): array
    {
        $permalink = static fn (string $id): string =>
            '<a id="' . $id . '" href="#' . $id . '" class="heading-permalink" aria-hidden="true">#</a>';

        return [
            'empty' => '',
            'whitespace_only' => "  \n\t ",
            'plain_text_no_markup' => 'Just some text, no tags at all.',
            'simple_headings' => '<h2>' . $permalink('one') . 'One</h2><p>First para.</p>'
                . '<h3>' . $permalink('one-a') . 'One A</h3><p>Sub para.</p>'
                . '<h2>' . $permalink('two') . 'Two</h2><p>Second para.</p>',
            'entities' => '<h2>' . $permalink('fish')
                . 'Fish &amp; Chips &lt;b&gt; &copy; 2024 &#8212; &#x1F600;</h2>'
                . '<p>AT&amp;T &quot;quoted&quot; &apos;single&apos; &hellip; &euro;5 &unknown; &amp</p>',
            'nbsp' => '<h2>' . $permalink('nb') . 'A&nbsp;B</h2><p>x&nbsp;y &#160; z</p>',
            'void_elements' => '<h2>' . $permalink('v') . 'Void</h2>'
                . '<p>line<br>break<br/>self<hr><input type="text" name="q"><wbr><meta charset="x"></p>',
            'boolean_attributes' => '<h2>' . $permalink('b') . 'Boolean</h2>'
                . '<p><input disabled checked readonly><details open><summary>s</summary>t</details>'
                . '<script async defer src="/a.js"></script><video controls autoplay muted></video></p>',
            'nested_inline_markup' => '<h2>' . $permalink('n')
                . '<em>Nested <strong>inline <code>code</code></strong></em> heading</h2>'
                . '<p><a href="/x"><span>deep <b>bold <i>italic</i></b></span></a></p>',
            'unicode' => '<h2>' . $permalink('uni') . 'Caf&eacute; 日本語 مرحبا 🎉</h2>'
                . '<p>Ünïcödé — “smart quotes” ñ 中文 עברית 😀</p>'
                . '<h3>' . $permalink('uni-2') . 'Ελληνικά</h3><p>κείμενο</p>',
            'script_and_style' => '<h2>' . $permalink('ss') . 'Script</h2>'
                . '<script>if (a < b && c > d) { document.write("<h2>fake</h2>"); }</script>'
                . '<style>h2 > a { color: red; } /* <h3>no</h3> */</style><p>after</p>',
            'comments' => '<!-- <h2 id="hidden">hidden</h2> --><h2>' . $permalink('c') . 'Visible</h2>'
                . '<!--[if IE]><p>ie</p><![endif]--><p>tail</p>',
            'malformed_unclosed' => '<h2>' . $permalink('m1') . 'Open heading <p>para <div>div <span>span',
            'malformed_mismatched' => '<h2>' . $permalink('m2') . 'Mismatch</h3><p>a</b></p></div><h2>stray',
            'malformed_attributes' => '<h2 id=unquoted class=x>Unquoted</h2><p title="unterminated>text</p>'
                . '<h3 id=\'single\'>Single</h3><a href=/x>bare</a>',
            'heading_ids_without_permalink' => '<h2 id="plain-id">Plain ID</h2><h3 id="plain-id-3">Plain H3</h3>',
            'permalink_without_id_href_only' =>
                '<h2><a href="#only-href" class="heading-permalink">#</a>Href only</h2>',
            'h3_before_h2_and_skipped_levels' => '<h3>' . $permalink('h3first') . 'H3 first</h3>'
                . '<h2>' . $permalink('h2') . 'H2</h2><h4>' . $permalink('h4') . 'H4 ignored by toc</h4>'
                . '<h3>' . $permalink('h3') . 'H3</h3>',
            'duplicate_ids' => '<h2>' . $permalink('dup') . 'Dup 1</h2><h2>' . $permalink('dup') . 'Dup 2</h2>',
            'tables_lists_pre' => '<h2>' . $permalink('t') . 'Structure</h2>'
                . '<table><tr><td>a</td><td>b</td></tr></table>'
                . '<ul><li>one</li><li>two<ul><li>nested</li></ul></li></ul><pre>  keep   spaces
  and newlines</pre>',
            'full_document' => '<!DOCTYPE html><html lang="en"><head><title>T</title><meta charset="utf-8"></head>'
                . '<body><nav><a href="/">Home</a></nav><h2>' . $permalink('doc') . 'Doc</h2><p>Body text.</p>'
                . '<footer>Footer text</footer></body></html>',
            'hostile_doctype_entity' => '<!DOCTYPE html [<!ENTITY xxe SYSTEM "file:///etc/hostname">]>'
                . '<h2>' . $permalink('x') . 'XXE &xxe;</h2><p>text</p>',
            'xml_processing_instruction' => '<?xml version="1.0"?><h2>' . $permalink('pi') . 'PI</h2><p>t</p>',
            'cdata_like' => '<h2>' . $permalink('cd') . 'CDATA</h2><p><![CDATA[ raw ]]> text</p>',
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function asProvider(): array
    {
        $out = [];
        foreach (self::cases() as $name => $html) {
            $out[$name] = [$name];
        }

        return $out;
    }
}
