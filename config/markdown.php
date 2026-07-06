<?php

// Minimal Markdown renderer for admin-authored content_pages / faq_items text.
// All HTML is escaped before any markdown syntax is applied, so there is no
// raw-HTML passthrough even though only trusted admins write this content.
// Supported syntax: ## headings, **bold**, *italic*, [text](url) links,
// "- " bullet lists, and blank-line-separated paragraphs.

if (!function_exists('render_markdown')) {
    function render_markdown(string $md): string {
        $escaped = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');
        $lines = preg_split('/\r\n|\r|\n/', $escaped);

        $html = '';
        $listItems = [];
        $paragraphLines = [];

        $flushList = function () use (&$listItems, &$html) {
            if (!$listItems) {
                return;
            }
            $html .= "<ul>\n";
            foreach ($listItems as $item) {
                $html .= '<li>' . _render_markdown_inline($item) . "</li>\n";
            }
            $html .= "</ul>\n";
            $listItems = [];
        };

        $flushParagraph = function () use (&$paragraphLines, &$html) {
            if (!$paragraphLines) {
                return;
            }
            $html .= '<p>' . _render_markdown_inline(implode(' ', $paragraphLines)) . "</p>\n";
            $paragraphLines = [];
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $flushList();
                $flushParagraph();
                continue;
            }

            if (preg_match('/^##\s+(.*)$/', $trimmed, $m)) {
                $flushList();
                $flushParagraph();
                $html .= '<h2>' . _render_markdown_inline($m[1]) . "</h2>\n";
                continue;
            }

            if (preg_match('/^-\s+(.*)$/', $trimmed, $m)) {
                $flushParagraph();
                $listItems[] = $m[1];
                continue;
            }

            $flushList();
            $paragraphLines[] = $trimmed;
        }

        $flushList();
        $flushParagraph();

        return $html;
    }
}

if (!function_exists('_render_markdown_inline')) {
    function _render_markdown_inline(string $text): string {
        // Links: [text](url) — only allow http(s), relative, or in-page anchors.
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function (array $m): string {
            [, $label, $url] = $m;
            if (!preg_match('~^(https?://|/|#)~i', $url)) {
                return $label;
            }
            return '<a href="' . $url . '">' . $label . '</a>';
        }, $text);

        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);

        return $text;
    }
}
