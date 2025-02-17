<?php
// Copyright 2017-2024 Ino Detelić & Zaloa G. Ramos (setastart.com)
// Licensed under the European Union Public License, version 1.2 (EUPL-1.2)
// You may not use this work except in compliance with the Licence.
// Licence copy: https://joinup.ec.europa.eu/collection/eupl/eupl-text-11-12

namespace Galaxia;


use DateTimeInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;
use IntlDateFormatter;
use Normalizer;
use Transliterator;
use function array_search;
use function array_unshift;
use function htmlspecialchars;
use function in_array;
use function preg_replace_callback;
use function str_replace;
use function strip_tags;


class Text {

    public const array ALLOWED_TAGS = ['a', 'h1', 'h2', 'h3', 'strong', 'small', 'p', 'br', 'em', 'del', 'blockquote', 'pre', 'ul', 'ol', 'li'];

    public const int HTMLSPECIALCHARS_FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5;

    public const string TEST_HTML = <<<HTML
<div>This div tag should go away.</div><span>This span tag too.</span><h1 onclick="console.error('this should never happen')">Example <strong>Test Heading 1</strong> that <del>is</del> 49 <em>characters</em> long</h1><p>First paragraph, a bit <strong>long so it wraps around</strong>, to see how it <em>looks if it wraps</em>. This first paragraph <del>doesn't have</del> any newlines and is 155 characters long.</p><p>Second short paragraph with newine here.<br>This line is a soft break from the previous.<br>Another line with a <a href="https://setastart.com">hyperlink</a>.</p><h2>Example <strong>Test Heading 2</strong> that <del>is</del> 49 <em>characters</em> long</h2><p>Third short paragraph.</p><ul><li>Unordered list item one.<br>Soft break into a long line to see how it wraps. Lorem ipsum dolor sit amet everything bla bla bla andalongfakewordwhynot.</li><li>Unordered list item two.<ul><li>Subitem one.<ul><li>Subsubitem one. I'm making this one longer, so it wraps to see where it ends up. This item is 113 characters long.</li><li>Subsubitem two.</li></ul></li></ul></li></ul><ol><li>Unordered list item one.<br>Soft break into a long line to see how it wraps. Lorem ipsum dolor sit amet everything bla bla bla andalongfakewordwhynot.</li><li>Unordered list item two.<ol><li>Subitem one.<ol><li>Subsubitem one. I'm making this one longer, so it wraps to see where it ends up. This item is 113 characters long.</li><li>Subsubitem two.</li></ol></li></ol></li></ol><pre>This is a code fragment or something. First line, a bit long so it wraps around, to see how it looks if it wraps. This first line ends in a newline after the dot.
Second Line.</pre><blockquote>This is a quotation or something. First line, a bit long, so it wraps around, to see how it looks if it wraps. This first line ends in a newline after the dot.<br>Second Line.</blockquote>
HTML;

    public static array $translation      = [];
    public static array $translationAlias = [];

    private static ?Transliterator $transliterator      = null;
    private static ?Transliterator $transliteratorLower = null;
    private static array           $intlDateFormatters  = [];

    static function ricoSanitize(string $html, ?array $allowed = null): string {
        $html = str_replace('&nbsp;', ' ', $html);

        // Replace multiple spaces with a single space
        $html = preg_replace('!\s+!', ' ', $html);

        // // Convert curly quotes to straight equivalent
        // $replacements = [
        //     "\xE2\x80\x98" => "'",   // ‘
        //     "\xE2\x80\x99" => "'",   // ’
        //     "\xE2\x80\x9A" => "'",   // ‚
        //     "\xE2\x80\x9B" => "'",   // ‛
        //     "\xE2\x80\x9C" => '"',   // “
        //     "\xE2\x80\x9D" => '"',   // ”
        //     "\xE2\x80\x9E" => '"',   // „
        //     "\xE2\x80\x9F" => '"',   // ‟
        //     "\xE2\x80\x93" => '-',
        //     "\xE2\x80\x94" => '--',
        //     "\xE2\x80\xa6" => '...',
        // ];
        // $html = strtr($html, $replacements);

        $html = strip_tags($html, $allowed);
        if (!$html) return '';

        libxml_use_internal_errors(true) && libxml_clear_errors();
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOEMPTYTAG);
        $html  = '';
        $xpath = new DOMXPath($dom);

        /** @var DOMElement $node */
        foreach ($xpath->query('//*') as $node) {
            for ($i = $node->attributes->length - 1; $i >= 0; $i--) {
                $attribute = $node->attributes->item($i);
                if ($node->tagName == 'a' && $attribute->name == 'href') continue;
                if ($node->tagName == 'a' && $attribute->name == 'target') continue;
                $node->removeAttributeNode($attribute);
            }
        }

        $html = str_replace(array('<body>', '</body>'), '', $dom->saveHTML($dom->documentElement));
        return $html;
    }


    static function unsafe(string $text, bool $condition = true): ?string {
        if ($condition) return $text;

        return null;
    }

    static function unsafeg(array $arr, ?string $key = null, string $lang = '') {
        if (is_null($key)) {
            $key = 'temp';
            $arr = [$key => $arr];
        }

        if (empty($arr)) return null;
        if (!isset($arr[$key])) return null;
        $text = null;

        switch (gettype($arr[$key])) {
            case 'integer':
            case 'double':
                $text = (string)$arr[$key];
                break;

            case 'string':
                $text = $arr[$key];
                break;

            case 'array':
                $langs = G::langs();
                if ($lang && in_array($lang, $langs)) {
                    $langKey = array_search($lang, $langs);
                    if ($langKey > 0) {
                        unset($langs[$langKey]);
                        array_unshift($langs, $lang);
                    }
                }
                foreach ($langs as $lang) {
                    if (!isset($arr[$key][$lang])) continue;
                    if (!is_string($arr[$key][$lang])) continue;
                    if (empty($arr[$key][$lang])) continue;
                    $text = (string)$arr[$key][$lang];
                    break;
                }
                break;
        }

        if ($text !== '0' && empty($text)) return null;

        return $text;
    }




    static function h($text, bool $condition = true): ?string {
        if (!$condition) return null;

        return htmlspecialchars(
            string: (string)$text,
            flags: self::HTMLSPECIALCHARS_FLAGS,
            encoding: 'UTF-8',
            double_encode: false,
        );
    }

    static function hg(array $arr, ?string $key = null, string $lang = ''): ?string {
        if (is_null($key)) {
            $key = 'temp';
            $arr = [$key => $arr];
        }
        if (empty($arr)) return null;
        if (!isset($arr[$key])) return null;
        $text = null;

        switch (gettype($arr[$key])) {
            case 'integer':
            case 'double':
                $text = (string)$arr[$key];
                break;

            case 'string':
                $text = htmlspecialchars($arr[$key], self::HTMLSPECIALCHARS_FLAGS, 'UTF-8', false) ?: null;
                break;

            case 'array':
                $langs = G::langs();
                if ($lang && in_array($lang, $langs)) {
                    $langKey = array_search($lang, $langs);
                    if ($langKey > 0) {
                        unset($langs[$langKey]);
                        array_unshift($langs, $lang);
                    }
                }
                foreach ($langs as $lang) {
                    if (!isset($arr[$key][$lang])) continue;
                    if (!is_string($arr[$key][$lang])) continue;
                    if (empty($arr[$key][$lang])) continue;
                    $text = htmlspecialchars($arr[$key][$lang], self::HTMLSPECIALCHARS_FLAGS, 'UTF-8', false);
                    break;
                }
                break;
        }

        if ($text !== '0' && empty($text)) return null;

        return $text;
    }




    /** @deprecated */
    static function st(string $text, int $h1 = 0, int $f1 = 0, int $f2 = 0): string {
        // if (G::$dev) $text = TEST_HTML;
        if (empty($text)) return '';
        $text = trim(strip_tags($text, self::ALLOWED_TAGS));
        if (empty($text)) return '';
        if ($h1 == 0) return $text;

        // add target="_blank" rel="noopener" to outgoing links
        $host = explode('.', G::$req->host);
        $host = implode('\.', $host);
        $re   = '~<a href="https?(?!://' . $host . '/)://([^:/\s">]+)~m';

        $text = preg_replace_callback($re, function($matches) {
            $inject = 'target="_blank" rel="noopener';
            if (self::nofollowHost($matches[1])) $inject .= ' nofollow';

            return '<a ' . $inject . '"' . substr($matches[0], 2);
        }, $text);

        // Header modification for <h1> and <h2>
        // $h1 changes the start numbering of the <h1>. If set to 0, disable header modification.
        // <h2> is $h1 + 1.
        // $f1 ads class="$f1" to <h1>. If set to 0, it is $h1
        // $f2 ads class="$f2" to <h2>. If set to 0, it is $h1 + 1
        if ($f1 == 0) $f1 = $h1;
        if ($f2 == 0) $f2 = $f1 + 1;
        if ($f1 == 7) $f1 = 'p';
        if ($f2 == 7) $f2 = 'p';
        if ($f1 == 8) $f1 = 's';
        if ($f2 == 8) $f2 = 's';
        $text = str_replace('<h2>', '<h' . ($h1 + 1) . ' class="t t2 f' . $f2 . '">', $text);
        $text = str_replace('</h2>', '</h' . ($h1 + 1) . '>', $text);

        $text = str_replace('<h1>', '<h' . $h1 . ' class="t t1 f' . $f1 . '">', $text);
        $text = str_replace('</h1>', '</h' . $h1 . '>', $text);

        return $text;
    }

    /** @deprecated */
    static function stg(array $arr, ?string $key = null, int $h1 = 0, int $f1 = 0, int $f2 = 0, string $lang = ''): ?string {
        if (is_null($key)) {
            $key = 'temp';
            $arr = [$key => $arr];
        }

        if (!isset($arr[$key])) return null;
        $text = '';

        switch (gettype($arr[$key])) {
            case 'integer':
            case 'double':
                $text = (string)$arr[$key];
                break;

            case 'string':
                $text = self::st($arr[$key], $h1, $f1, $f2);
                break;

            case 'array':
                $langs = G::langs();
                if ($lang && in_array($lang, $langs)) {
                    $langKey = array_search($lang, $langs);
                    if ($langKey > 0) {
                        unset($langs[$langKey]);
                        array_unshift($langs, $lang);
                    }
                }
                foreach ($langs as $lang) {
                    if (!isset($arr[$key][$lang])) continue;
                    if (!is_string($arr[$key][$lang])) continue;
                    if (empty($arr[$key][$lang])) continue;
                    $text = self::st($arr[$key][$lang], $h1, $f1, $f2);
                    break;
                }
                break;
        }

        if ($text !== '0' && empty($text)) return null;

        return $text;
    }




    static function html(string $text = '', array ...$transforms): ?string {
        // if (G::$dev) $text = TEST_HTML;
        $text = trim(strip_tags($text, self::ALLOWED_TAGS));
        if (empty($text)) return null;

        // add target="_blank" rel="noopener" to outgoing links
        $host = explode('.', G::$req->host);
        $host = implode('\.', $host);
        $re   = '~<a href="https?(?!://' . $host . '/)://([^:/\s">]+)~m';

        $text = preg_replace_callback($re, function($matches) {
            $inject = 'target="_blank" rel="noopener';
            if (self::nofollowHost($matches[1])) $inject .= ' nofollow';

            return '<a ' . $inject . '"' . substr($matches[0], 2);
        }, $text);

        if (empty($transforms)) return $text;

        foreach ($transforms as $tagOld => $transform) {
            $tagNew = 'galaxiaTemp' . $transform[0];
            $class  = '';
            if (isset($transform[1])) $class = ' class="' . $transform[1] . '"';
            $id = '';
            if (isset($transform[2])) $id = ' id="' . $transform[2] . '"';
            $text = str_replace("<$tagOld>", "<$tagNew$class$id>", $text);
            $text = str_replace("</$tagOld>", "</$tagNew>", $text);
        }

        foreach ($transforms as $transform) {
            if (!$transform[0] ?? false) {
                $text = str_replace('<galaxiaTemp>', '', $text);
                $text = str_replace('</galaxiaTemp>', '', $text);
            } else {
                $tagOld = 'galaxiaTemp' . $transform[0];
                $tagNew = $transform[0];
                $text   = str_replace($tagOld, $tagNew, $text);
            }
        }

        return $text;
    }

    static function htmlg(array $arr, ?string $key = null, string $lang = '', array ...$transforms): ?string {
        if (is_null($key)) {
            $key = 'temp';
            $arr = [$key => $arr];
        }

        if (!isset($arr[$key])) return null;
        $text = '';

        switch (gettype($arr[$key])) {
            case 'integer':
            case 'double':
                $text = (string)$arr[$key];
                break;

            case 'string':
                $text = self::html($arr[$key], ...$transforms);
                break;

            case 'array':
                $langs = G::langs();
                if ($lang && in_array($lang, $langs)) {
                    $langKey = array_search($lang, $langs);
                    if ($langKey > 0) {
                        unset($langs[$langKey]);
                        array_unshift($langs, $lang);
                    }
                }
                foreach ($langs as $lang) {
                    if (!isset($arr[$key][$lang])) continue;
                    if (!is_string($arr[$key][$lang])) continue;
                    if (empty($arr[$key][$lang])) continue;
                    $text = self::html($arr[$key][$lang], ...$transforms);
                    break;
                }
                break;
        }

        if ($text !== '0' && empty($text)) return null;

        return $text;
    }




    static function stp(string $text, int $f1 = 0, int $f2 = 0, int $fp = 0): string {
        // if (G::$dev) $text = TEST_HTML;
        if (empty($text)) return '';
        $text = trim(strip_tags($text, self::ALLOWED_TAGS));
        if (empty($text)) return '';

        // add target="_blank" rel="noopener" to outgoing links
        $host = explode('.', G::$req->host);
        $host = implode('\.', $host);
        $re   = '~<a href="https?(?!://' . $host . '/)://([^:/\s">]+)~m';

        $text = preg_replace_callback($re, function($matches) {
            $inject = 'target="_blank" rel="noopener';
            if (self::nofollowHost($matches[1])) $inject .= ' nofollow';

            return '<a ' . $inject . '"' . substr($matches[0], 2);
        }, $text);

        // Header modification for <h1> and <h2>
        // $h1 changes the start numbering of the <h1>. If set to 0, disable header modification.
        // <h2> is $h1 + 1.
        // $f1 ads class="$f1" to <h1>. If set to 0, it is $h1
        // $f2 ads class="$f2" to <h2>. If set to 0, it is $h1 + 1
        $class1 = '';
        if ($f1 > 0 && $f1 <= 6) {
            $class1 = ' class="t t1 f' . $f1 . '"';
        } else if ($f1 == 8) {
            $class1 = ' class="fs"';
        }
        $class2 = '';
        if ($f2 > 0 && $f2 <= 6) {
            $class2 = ' class="t t2 f' . $f2 . '"';
        } else if ($f2 == 8) {
            $class2 = ' class="fs"';
        }
        if ($fp > 0 && $fp <= 6) {
            $text = str_replace('<p>', '<p class="f' . $fp . '">', $text);
        } else if ($fp == 8) {
            $text = str_replace('<p>', '<p class="fs">', $text);
        }

        $text = str_replace('<h2>', '<p' . $class2 . '>', $text);
        $text = str_replace('</h2>', '</p>', $text);

        $text = str_replace('<h1>', '<p' . $class1 . '>', $text);
        $text = str_replace('</h1>', '</p>', $text);

        return $text;
    }

    static function stpg(array $arr, ?string $key = null, int $f1 = 0, int $f2 = 0, int $fp = 0, string $lang = ''): ?string {
        if (is_null($key)) {
            $key = 'temp';
            $arr = [$key => $arr];
        }

        if (!isset($arr[$key])) return null;
        $text = '';

        switch (gettype($arr[$key])) {
            case 'integer':
            case 'double':
                $text = (string)$arr[$key];
                break;

            case 'string':
                $text = self::stp($arr[$key], $f1, $f2, $fp);
                break;

            case 'array':
                $langs = G::langs();
                if ($lang && in_array($lang, $langs)) {
                    $langKey = array_search($lang, $langs);
                    if ($langKey > 0) {
                        unset($langs[$langKey]);
                        array_unshift($langs, $lang);
                    }
                }
                foreach ($langs as $lang) {
                    if (!isset($arr[$key][$lang])) continue;
                    if (!is_string($arr[$key][$lang])) continue;
                    if (empty($arr[$key][$lang])) continue;
                    $text = self::stp($arr[$key][$lang], $f1, $f2, $fp);
                    break;
                }
                break;
        }

        if ($text !== '0' && empty($text)) return null;

        return $text;
    }




    static function desc(string $html, ?int $length = null, string $separator = ' / '): string {
        if (empty($html)) return '';
        if (is_null($length)) $length = 255;

        $html = preg_replace('~<br ?/?>~m', PHP_EOL, $html);
        $html = str_replace('&nbsp;', '', $html);

        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $pTags = $dom->getElementsByTagName('p');

        $text  = '';
        $chars = 0;
        $i     = 0;
        foreach ($pTags as $pTag) {
            if ($i > 0) {
                $text .= match (substr($text, -1)) {
                    '.', ':' => ' ',
                    default  => PHP_EOL,
                };
            }
            $line = $pTag->nodeValue;
            $line = trim($line, " \t\n\r\0\x0B\xC2\xA0");

            $line = preg_replace('~(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))~m', '', $line); // remove urls

            $text  .= $line;
            $chars += mb_strlen($line);
            if ($length > 0 && $chars > $length) {
                break;
            }
            $i++;
        }
        if (!$text) {
            $text = $html;
            $text = trim($text, " \t\n\r\0\x0B\xC2\xA0");
            $text = strip_tags($text);
            $text = preg_replace('~\s+~u', ' ', $text ?? '');
        }
        $text = preg_replace('~\s*\.\s*\n+~m', '. ', $text ?? '');
        $text = preg_replace('~\s*:\s*\n+~m', ': ', $text ?? '');
        $text = preg_replace('~\s*\n+\s*~m', $separator, $text ?? '');
        $text = preg_replace('~\s+~u', ' ', $text ?? '');
        $text = preg_replace('~\s?,\s?~', ', ', $text ?? '');

        if ($length > 0 && mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length) . '[…]';
        }

        return htmlspecialchars($text, self::HTMLSPECIALCHARS_FLAGS, 'UTF-8', false);
    }

    static function descg(array $arr, ?string $key = null, ?int $length = null, string $separator = ' / ', string $lang = ''): ?string {
        if (is_null($key)) {
            $key = 'temp';
            $arr = [$key => $arr];
        }

        if (!isset($arr[$key])) return null;
        if (is_null($length)) $length = 255;
        $text = '';

        switch (gettype($arr[$key])) {
            case 'integer':
            case 'double':
                $text = (string)$arr[$key];
                break;

            case 'string':
                $text = self::desc($arr[$key], $length, $separator);
                break;

            case 'array':
                $langs = G::langs();
                if ($lang && in_array($lang, $langs)) {
                    $langKey = array_search($lang, $langs);
                    if ($langKey > 0) {
                        unset($langs[$langKey]);
                        array_unshift($langs, $lang);
                    }
                }
                foreach ($langs as $lang) {
                    if (!isset($arr[$key][$lang])) continue;
                    if (!is_string($arr[$key][$lang])) continue;
                    if (empty($arr[$key][$lang])) continue;
                    $text = self::desc($arr[$key][$lang], $length, $separator);
                    break;
                }
        }

        if ($text !== '0' && empty($text)) return null;

        return $text;
    }




    static function unsafet(string $text, ?string $lang = null) {
        if ($lang == null) $lang = G::lang();

        if (isset(self::$translation[$text][$lang]) &&
            self::$translation[$text][$lang]
        ) {
            return self::$translation[$text][$lang];
        }

        if (isset(self::$translationAlias[$text])) {
            if (isset(self::$translation[self::$translationAlias[$text]][$lang]) &&
                self::$translation[self::$translationAlias[$text]][$lang]
            ) {
                return self::$translation[self::$translationAlias[$text]][$lang];
            }

            return self::$translationAlias[$text];
        }

        // if (G::$debug) $text = '@' . $text;
        return $text;
    }

    static function t(string $text, ?string $lang = null): string {
        return htmlspecialchars(self::unsafet($text, $lang), self::HTMLSPECIALCHARS_FLAGS, 'UTF-8', false);
    }




    /**
     * put SQL query quotes around table and field names
     */
    static function q(string $text): string {
        return '`' . str_replace('`', '``', self::h($text)) . '`';
    }




    static function tagExtractFirst(string &$text, string $tag): string {
        $extract = '';
        if (preg_match("~<$tag>.+?</$tag>~", $text, $m)) {
            $extract = $m[0];
            $text    = preg_replace("~<$tag>.+?</$tag>~", '', $text, 1);
        }

        return $extract;
    }




    static function firstLine(string $text): string {
        if (empty($text)) return '';
        $text = str_replace('<', ' <', $text);
        $text = strip_tags($text);
        $text = trim($text);
        $len  = mb_strlen($text);
        $text = strtok($text, PHP_EOL);
        $text = mb_substr((string)$text, 0, 100);
        $text = trim($text);
        if (mb_strlen($text) < $len) $text .= ' […]';

        return $text;
    }




    static function renderLinkEmail($email, string $subject = '', string $class = '', string $prepend = '', string $append = ''): ?string {
        if (!is_string($email)) return '';
        if (!$email = filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $email = self::h($email);
        if ($subject) $subject = '?subject=' . self::h($subject);
        if (!empty($class)) $class = ' class="' . self::h($class) . '"';

        return '<a aria-label="' . self::t('Email') . '" href="mailto:' . $email . $subject . '"' . $class . '>' . $prepend . $email . $append . '</a>';
    }

    static function renderLinkTel($prefix, $tel = '', string $class = '', string $prepend = '', string $append = ''): ?string {
        if (!is_string($prefix)) return '';
        if (!is_string($tel)) return '';
        if (!$tel) return null;
        $prefix       = self::h(preg_replace('/[\D]/', '', $prefix));
        $prefixSmall  = '';
        $telStripped  = preg_replace('/^\+' . $prefix . '/', '', self::h($tel));
        $telStripped  = preg_replace('/^00' . $prefix . '/', '', $telStripped);
        $telFormatted = $telStripped;
        $telStripped  = preg_replace('/[\D]/', '', $telStripped);
        if ($prefix == '351') $telFormatted = number_format((int)$telStripped, 0, ' ', ' ');
        if (!empty($prefix)) {
            $prefix      = '+' . $prefix;
            $prefixSmall = '<small>' . $prefix . ' </small>';
            $telStripped = ltrim($telStripped, '0');
        }
        if (!empty($class)) $class = ' class="' . self::h($class) . '"';

        return '<a aria-label="' . self::t('Phone') . '" href="tel:' . $prefix . $telStripped . '"' . $class . '>' . $prepend . $prefixSmall . $telFormatted . $append . '</a>';
    }



    // todo: empty hosts
    static function nofollowHost(string $host, array $hosts = ['facebook', 'google', 'instagram', 'twitter', 'linkedin', 'youtube']): bool {
        foreach ($hosts as $nofollowHost)
            if (str_contains($host, $nofollowHost)) return true;

        return false;
    }




    static function formatSlug(string $text, array $existing = []): string {

        $text = str_replace('<', ' <', $text);
        $text = strip_tags($text);

        $text = str_replace(['&nbsp;', '&#160;', '&ndash;', '&#8211;', '&mdash;', '&#8212;'], '-', $text);

        $tr   = self::$transliteratorLower ?? self::getTransliteratorLower();
        $text = $tr->transliterate($text);

        $text = preg_replace('~[\x{1F600}-\x{1F64F}]~u', '', $text); // Match Emoticons
        $text = preg_replace('~[\x{1F300}-\x{1F5FF}]~u', '', $text); // Match Miscellaneous Symbols and Pictographs
        $text = preg_replace('~[\x{1F680}-\x{1F6FF}]~u', '', $text); // Match Transport And Map Symbols
        $text = preg_replace('~[\x{2600}-\x{26FF}]~u', '', $text);   // Match Miscellaneous Symbols
        $text = preg_replace('~[\x{2700}-\x{27BF}]~u', '', $text);   // Match Dingbats
        $text = preg_replace('~[^a-z0-9-]+~u', '-', $text);
        $text = preg_replace('~-+~', '-', $text);
        $text = trim($text, '-');

        if (empty($existing)) return $text;

        // if slug is in existing, append a number
        while (isset($existing[$text])) {
            if (preg_match('~-(\d+$)~', $text, $matches, PREG_OFFSET_CAPTURE)) {
                $text = substr($text, 0, 5) . ++$matches[1][0];
            } else {
                $text .= '-2';
            }
        }

        return $text;
    }




    static function formatSearch(string $text): ?string {
        $text = self::translit($text);

        // replace non letter or digits by a space
        $text = preg_replace('~[^\pL\d]+~u', ' ', $text);

        $text = preg_replace('~\s~', ' ', $text);
        $text = str_replace('<', ' <', $text);
        $text = strip_tags($text);
        $text = preg_replace('~\s+~m', ' ', $text);

        return self::h(trim($text));
    }




    /**
     * cached IntlDateFormatter for localized date and time
     * pattern reference: http://userguide.icu-project.org/formatparse/datetime
     * pattern characters inside single quotes are escaped: 'example'
     *
     * @param mixed $value a timestamp, a DateTime or a string that creates a DateTime
     */
    static function formatDate(mixed $value, string $pattern = '', string $lang = ''): string {
        if (is_string($value) && !ctype_digit($value)) {
            $value = date_create($value);
            if (!$value instanceof DateTimeInterface) return '';
        }

        if (empty($lang) || !isset(G::langs()[$lang]))
            $lang = G::lang();

        $df = self::$intlDateFormatters[$pattern][$lang] ?? self::getIntlDateFormatter($pattern, $lang);

        return $df->format($value);
    }




    static function normalize(string $text, string $delimiter = '-', string $keep = ''): string {
        $text = str_replace('<', ' <', $text);
        $text = strip_tags($text);
        $text = Normalizer::normalize($text);

        // replace non letter or digits by delimiter, keeping $keep characters
        $text = preg_replace('~[^\pL\d' . preg_quote($keep) . ']+~u', $delimiter, $text);

        return $text;
    }

    static function translit(string $text, bool $lower = true): string {
        if ($lower)
            $tr = self::$transliteratorLower ?? self::getTransliteratorLower();
        else
            $tr = self::$transliterator ?? self::getTransliterator();

        return $tr->transliterate($text);
    }




    static function bytesIntToAbbr(int $bytes, int $decimals = 2, $byteAlign = ''): string {
        $negative = ($bytes < 0) ? '-' : '';
        $bytes    = abs($bytes);
        if ($bytes < 1024) return $bytes . ' ' . $byteAlign . 'B';
        $size   = [' ' . $byteAlign . 'B', ' kB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB'];
        $factor = (int)floor((strlen($bytes) - 1) / 3);

        return $negative . sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ($size[$factor] ?? '');
    }

    /**
     * converts for example 1M => 1048576 or 1k => 1024
     */
    static function bytesAbbrToInt(string $size): int {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);

        $size = preg_replace('/[^0-9\\.]/', '', $size);
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }



    static function commentHeader(string $text): string {
        $r = '';
        $r .= '/********' . str_repeat('*', strlen($text)) . '********/' . PHP_EOL;
        $r .= '/******  ' . self::h($text) . '  ******/' . PHP_EOL;
        $r .= '/********' . str_repeat('*', strlen($text)) . '********/' . PHP_EOL;

        return $r;
    }




    static function br2nl(string $text): string {
        return preg_replace('~<br(\s*)?/?>~i', PHP_EOL, $text) ?? '';
    }




    private static function getTransliteratorLower(): ?Transliterator {
        if (self::$transliteratorLower == null) {
            self::$transliteratorLower = Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;');
        }

        return self::$transliteratorLower;
    }


    private static function getTransliterator(): ?Transliterator {
        if (self::$transliterator == null) {
            self::$transliterator = Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
        }

        return self::$transliterator;
    }




    private static function getIntlDateFormatter(string $pattern, string $lang) {
        if (!isset(self::$intlDateFormatters[$pattern][$lang])) {
            self::$intlDateFormatters[$pattern][$lang] = new IntlDateFormatter(
                locale: $lang,
                dateType: IntlDateFormatter::FULL,
                timeType: IntlDateFormatter::NONE,
                timezone: null,
                calendar: IntlDateFormatter::GREGORIAN,
                pattern: $pattern
            );
        }

        return self::$intlDateFormatters[$pattern][$lang];
    }

}
