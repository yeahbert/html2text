<?php

/*
 * Copyright (c) 2005-2007 Jon Abernathy <jon@chuggnutt.com>
 *
 * This script is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

namespace Html2Text;

class Html2Text
{
    const ENCODING = 'UTF-8';

    protected $htmlFuncFlags;

    const OPTION_UPPERCASE = 'optionUppercase';
    const OPTION_LOWERCASE = 'optionLowercase';
    const OPTION_NONE = 'optionNone';

    private static $caseModeMapping = [
        self::OPTION_LOWERCASE => MB_CASE_LOWER,
        self::OPTION_UPPERCASE => MB_CASE_UPPER,
    ];

    const DEFAULT_OPTIONS = array(
        'do_links' => 'inline',
        'width' => 70,
        'elements' => [
            'h1' => [
                'case' => self::OPTION_UPPERCASE,
            ],
            'h2' => [
                'case' => self::OPTION_UPPERCASE,
            ],
            'h3' => [
                'case' => self::OPTION_UPPERCASE,
            ],
            'h4' => [
                'case' => self::OPTION_UPPERCASE,
            ],
            'h5' => [
                'case' => self::OPTION_UPPERCASE,
            ],
            'h6' => [
                'case' => self::OPTION_UPPERCASE,
            ],
            'th' => [
                'case' => self::OPTION_UPPERCASE,
                'prepend' => "\t\t",
                'append' => "\n"
            ],
            'strong' => [
                'case' => self::OPTION_UPPERCASE,
            ],
            'b' => [
                'case' => self::OPTION_UPPERCASE,
            ],
            'li' => [
                'prepend' => "\t* ",
                'append' => "\n",
            ],
        ]
    );

    /**
     * Contains the HTML content to convert.
     *
     * @type string
     */
    protected $html;

    /**
     * Contains the converted, formatted text.
     *
     * @type string
     */
    protected $text;

    /**
     * List of preg* regular expression patterns to search for,
     * used in conjunction with $replace.
     *
     * @type array
     * @see $replace
     */
    protected $search = array(
        "/\r/" => '',                                           // Non-legal carriage return
        "/[\n\t]+/" => ' ',                                      // Newlines and tabs
        '/<head\b[^>]*>.*?<\/head>/i' => '',                    // <head>
        '/<script\b[^>]*>.*?<\/script>/i' => '',                // <script>s -- which strip_tags supposedly has problems with
        '/<style\b[^>]*>.*?<\/style>/i' => '',                  // <style>s -- which strip_tags supposedly has problems with
        '/<i\b[^>]*>(.*?)<\/i>/i' => '_\\1_',                        // <i>
        '/<em\b[^>]*>(.*?)<\/em>/i' => '_\\1_',                      // <em>
        '/(<ul\b[^>]*>|<\/ul>)/i' => "\n\n",                        // <ul> and </ul>
        '/(<ol\b[^>]*>|<\/ol>)/i' => "\n\n",                        // <ol> and </ol>
        '/(<dl\b[^>]*>|<\/dl>)/i' => "\n\n",                        // <dl> and </dl>
        '/<dd\b[^>]*>(.*?)<\/dd>/i' => " \\1\n",                      // <dd> and </dd>
        '/<dt\b[^>]*>(.*?)<\/dt>/i' => "\t* \\1",                      // <dt> and </dt>
        '/<(?<element>li)\b[^>]*>(?<value>.*?)<\/li>/i' => ['callback' => 'pregCallback'],                           // <li></li>
        '/<li\b[^>]*>/i' => "\n\t* ",                                 // <li>
        '/<hr\b[^>]*>/i' => "\n-------------------------\n",                                 // <hr>
        '/<div\b[^>]*>/i' => "<div>\n",                                // <div>
        '/(<table\b[^>]*>|<\/table>)/i' => "\n\n",                  // <table> and </table>
        '/(<tr\b[^>]*>|<\/tr>)/i' => "\n",                        // <tr> and </tr>
        '/<td\b[^>]*>(.*?)<\/td>/i' => "\t\t\\1\n",                      // <td> and </td>
        '/<span class="_html2text_ignore">.+?<\/span>/i' => "", // <span class="_html2text_ignore">...</span>
        '/<(img)\b[^>]*alt=\"([^>"]+)\"[^>]*>/i' => '[\\2]',         // <img> with alt tag
        '/<(?<element>h[123456])( [^>]*)?>(?<value>.*?)<\/h[123456]>/i' => ['callback' => 'pregCallback'],           // h1 - h6
        '/[ ]*<(?<element>p)( [^>]*)?>(?<value>.*?)<\/p>[ ]*/si' => ['callback' => 'pregCallback'],                  // <p> with surrounding whitespace.
        '/<(?<element>br)[^>]*>[ ]*/i' => ['callback' => 'pregCallback'],                                            // <br> with leading whitespace after the newline.
        '/<(?<element>b)( [^>]*)?>(?<value>.*?)<\/b>/i' => ['callback' => 'pregCallback'],                           // <b>
        '/<(?<element>strong)( [^>]*)?>(?<value>.*?)<\/strong>/i' => ['callback' => 'pregCallback'],                 // <strong>
        '/<(?<element>th)( [^>]*)?>(?<value>.*?)<\/th>/i' => ['callback' => 'pregCallback'],                         // <th> and </th>
        '/<(?<element>a) [^>]*href=("|\')([^"\']+)\2([^>]*)>(.*?)<\/a>/i' => ['callback' => 'pregCallback'],         // <a href="">
    );

    /**
     * List of preg* regular expression patterns to search for,
     * used in conjunction with $entReplace.
     *
     * @type array
     * @see $entReplace
     */
    protected $entSearch = array(
        '/&#153;/i',                                     // TM symbol in win-1252
        '/&#151;/i',                                     // m-dash in win-1252
        '/&(amp|#38);/i',                                // Ampersand: see converter()
        '/[ ]{2,}/',                                     // Runs of spaces, post-handling
    );

    /**
     * List of pattern replacements corresponding to patterns searched.
     *
     * @type array
     * @see $entSearch
     */
    protected $entReplace = array(
        '™',         // TM symbol
        '—',         // m-dash
        '|+|amp|+|', // Ampersand: see converter()
        ' ',         // Runs of spaces, post-handling
    );

    /**
     * List of preg* regular expression patterns to search for
     * and replace using callback function.
     *
     * @type array
     */
    protected $callbackSearch = array(
        '/<(?<element>h[123456])( [^>]*)?>(?<value>.*?)<\/h[123456]>/i',           // h1 - h6
        '/[ ]*<(?<element>p)( [^>]*)?>(?<value>.*?)<\/p>[ ]*/si',                  // <p> with surrounding whitespace.
        '/<(?<element>br)[^>]*>[ ]*/i',                                            // <br> with leading whitespace after the newline.
        '/<(?<element>b)( [^>]*)?>(?<value>.*?)<\/b>/i',                           // <b>
        '/<(?<element>strong)( [^>]*)?>(?<value>.*?)<\/strong>/i',                 // <strong>
        '/<(?<element>th)( [^>]*)?>(?<value>.*?)<\/th>/i',                         // <th> and </th>
        '/<(?<element>a) [^>]*href=("|\')([^"\']+)\2([^>]*)>(.*?)<\/a>/i',         // <a href="">
    );

    /**
     * List of preg* regular expression patterns to search for in PRE body,
     * used in conjunction with $preReplace.
     *
     * @type array
     * @see $preReplace
     */
    protected $preSearch = array(
        "/\n/",
        "/\t/",
        '/ /',
        '/<pre[^>]*>/',
        '/<\/pre>/'
    );

    /**
     * List of pattern replacements corresponding to patterns searched for PRE body.
     *
     * @type array
     * @see $preSearch
     */
    protected $preReplace = array(
        '<br>',
        '&nbsp;&nbsp;&nbsp;&nbsp;',
        '&nbsp;',
        '',
        '',
    );

    /**
     * Temporary workspace used during PRE processing.
     *
     * @type string
     */
    protected $preContent = '';

    /**
     * Contains the base URL that relative links should resolve to.
     *
     * @type string
     */
    protected $baseurl = '';

    /**
     * Indicates whether content in the $html variable has been converted yet.
     *
     * @type boolean
     * @see $html, $text
     */
    protected $converted = false;

    /**
     * Contains URL addresses from links to be rendered in plain text.
     *
     * @type array
     * @see buildlinkList()
     */
    protected $linkList = array();

    /**
     * Various configuration options (able to be set in the constructor)
     *
     * do_links:
     * 'none'
     * 'inline' (show links inline)
     * 'nextline' (show links on the next line)
     * 'table' (if a table of link URLs should be listed after the text.
     * 'bbcode' (show links as bbcode)
     *
     * width:
     * Maximum width of the formatted text, in columns.
     * Set this value to 0 (or less) to ignore word wrapping and not constrain text to a fixed-width column.
     *
     * case:
     * - uppercase: uppercase "SECTION TITLE"
     * - lowercase: lowercase ucfirst "Section Title"
     *
     * append: add a append at the end "Section Title:"
     *
     * @type array
     */
    protected $options;

    private function legacyConstruct($html = '', $fromFile = false, array $options = array())
    {
        $this->set_html($html, $fromFile);
        $this->options = array_merge(self::DEFAULT_OPTIONS, $options);
    }

    /**
     * @param string $html    Source HTML
     * @param array  $options Set configuration options
     */
    public function __construct($html = '', $options = array())
    {
        // for backwards compatibility
        if (!is_array($options)) {
            return call_user_func_array(array($this, 'legacyConstruct'), func_get_args());
        }

        $this->html = $html;
        $this->options = array_replace_recursive(self::DEFAULT_OPTIONS, $options);
        $this->htmlFuncFlags = (PHP_VERSION_ID < 50400)
            ? ENT_COMPAT
            : ENT_COMPAT | ENT_HTML5;

        return null;
    }

    /**
     * Set the source HTML
     *
     * @param string $html HTML source content
     */
    public function setHtml($html)
    {
        $this->html = $html;
        $this->converted = false;
    }

    /**
     * @deprecated
     */
    public function set_html($html, $from_file = false)
    {
        if ($from_file) {
            throw new \InvalidArgumentException("Argument from_file no longer supported");
        }

        $this->setHtml($html);
    }

    /**
     * Returns the text, converted from HTML.
     *
     * @return string
     */
    public function getText()
    {
        if (!$this->converted) {
            $this->convert();
        }

        return $this->text;
    }

    /**
     * @deprecated
     */
    public function get_text()
    {
        return $this->getText();
    }

    /**
     * @deprecated
     */
    public function print_text()
    {
        print $this->getText();
    }

    /**
     * @deprecated
     */
    public function p()
    {
        $this->print_text();
    }

    /**
     * Sets a base URL to handle relative links.
     *
     * @param string $baseurl
     */
    public function setBaseUrl($baseurl)
    {
        $this->baseurl = $baseurl;
    }

    /**
     * @deprecated
     */
    public function set_base_url($baseurl)
    {
        $this->setBaseUrl($baseurl);
    }

    protected function convert()
    {
        $origEncoding = mb_internal_encoding();
        mb_internal_encoding(self::ENCODING);

        $this->doConvert();

        mb_internal_encoding($origEncoding);
    }

    protected function doConvert()
    {
        $this->linkList = array();

        $text = trim($this->html);

        $this->converter($text);

        if ($this->linkList) {
            $text .= "\n\nLinks:\n------\n";
            foreach ($this->linkList as $i => $url) {
                $text .= '[' . ($i + 1) . '] ' . $url . "\n";
            }
        }

        $this->text = $text;

        $this->converted = true;
    }

    protected function converter(&$text)
    {
        $this->convertBlockquotes($text);
        $this->convertPre($text);

        foreach ($this->search as $pattern => $replace) {
            if (is_array($replace)) {
                $text = preg_replace_callback($pattern, [$this, $replace['callback']], $text);
            } else {
                $text = preg_replace($pattern, $replace, $text);
            }
        }

        $text = strip_tags($text);
        $text = preg_replace($this->entSearch, $this->entReplace, $text);
        $text = html_entity_decode($text, $this->htmlFuncFlags, self::ENCODING);

        // Remove unknown/unhandled entities (this cannot be done in search-and-replace block)
        $text = preg_replace('/&([a-zA-Z0-9]{2,6}|#[0-9]{2,4});/', '', $text);

        // Convert "|+|amp|+|" into "&", need to be done after handling of unknown entities
        // This properly handles situation of "&amp;quot;" in input string
        $text = str_replace('|+|amp|+|', '&', $text);

        // Normalise empty lines
        $text = preg_replace("/\n\s+\n/", "\n\n", $text);
        $text = preg_replace("/[\n]{3,}/", "\n\n", $text);

        // remove leading empty lines (can be produced by eg. P tag on the beginning)
        $text = ltrim($text, "\n");

        if ($this->options['width'] > 0) {
            $text = wordwrap($text, $this->options['width']);
        }
    }

    /**
     * Helper function called by preg_replace() on link replacement.
     *
     * Maintains an internal list of links to be displayed at the end of the
     * text, with numeric indices to the original point in the text they
     * appeared. Also makes an effort at identifying and handling absolute
     * and relative links.
     *
     * @param  string $link          URL of the link
     * @param  string $display       Part of the text to associate number with
     * @param  null   $linkOverride
     * @return string
     */
    protected function buildlinkList($link, $display, $linkOverride = null)
    {
        $linkMethod = ($linkOverride) ? $linkOverride : $this->options['do_links'];
        if ($linkMethod == 'none') {
            return $display;
        }

        // Ignored link types
        if (preg_match('!^(javascript:|mailto:|#)!i', $link)) {
            return $display;
        }

        if (preg_match('!^([a-z][a-z0-9.+-]+:)!i', $link)) {
            $url = $link;
        } else {
            $url = $this->baseurl;
            if (mb_substr($link, 0, 1) != '/') {
                $url .= '/';
            }
            $url .= $link;
        }

        if ($linkMethod == 'table') {
            if (($index = array_search($url, $this->linkList)) === false) {
                $index = count($this->linkList);
                $this->linkList[] = $url;
            }

            return $display . ' [' . ($index + 1) . ']';
        } elseif ($linkMethod == 'nextline') {
            return $display . "\n[" . $url . ']';
        } elseif ($linkMethod == 'bbcode') {
            return sprintf('[url=%s]%s[/url]', $url, $display);
        } else { // link_method defaults to inline
            return $display . ' [' . $url . ']';
        }
    }

    protected function convertPre(&$text)
    {
        // get the content of PRE element
        while (preg_match('/<pre[^>]*>(.*)<\/pre>/ismU', $text, $matches)) {
            // Replace br tags with newlines to prevent the search-and-replace callback from killing whitespace
            $this->preContent = preg_replace('/(<br\b[^>]*>)/i', "\n", $matches[1]);

            // Run our defined tags search-and-replace with callback
            $this->preContent = preg_replace_callback(
                $this->callbackSearch,
                array($this, 'pregCallback'),
                $this->preContent
            );

            // convert the content
            $this->preContent = sprintf(
                '<div><br>%s<br></div>',
                preg_replace($this->preSearch, $this->preReplace, $this->preContent)
            );

            // replace the content (use callback because content can contain $0 variable)
            $text = preg_replace_callback(
                '/<pre[^>]*>.*<\/pre>/ismU',
                array($this, 'pregPreCallback'),
                $text,
                1
            );

            // free memory
            $this->preContent = '';
        }
    }

    /**
     * Helper function for BLOCKQUOTE body conversion.
     *
     * @param string $text HTML content
     */
    protected function convertBlockquotes(&$text)
    {
        if (preg_match_all('/<\/*blockquote[^>]*>/i', $text, $matches, PREG_OFFSET_CAPTURE)) {
            $originalText = $text;
            $start = 0;
            $taglen = 0;
            $level = 0;
            $diff = 0;
            foreach ($matches[0] as $m) {
                $m[1] = mb_strlen(substr($originalText, 0, $m[1]));
                if ($m[0][0] == '<' && $m[0][1] == '/') {
                    $level--;
                    if ($level < 0) {
                        $level = 0; // malformed HTML: go to next blockquote
                    } elseif ($level > 0) {
                        // skip inner blockquote
                    } else {
                        $end = $m[1];
                        $len = $end - $taglen - $start;
                        // Get blockquote content
                        $body = mb_substr($text, $start + $taglen - $diff, $len);

                        // Set text width
                        $pWidth = $this->options['width'];
                        if ($this->options['width'] > 0) $this->options['width'] -= 2;
                        // Convert blockquote content
                        $body = trim($body);
                        $this->converter($body);
                        // Add citation markers and create PRE block
                        $body = preg_replace('/((^|\n)>*)/', '\\1> ', trim($body));
                        $body = '<pre>' . htmlspecialchars($body, $this->htmlFuncFlags, self::ENCODING) . '</pre>';
                        // Re-set text width
                        $this->options['width'] = $pWidth;
                        // Replace content
                        $text = mb_substr($text, 0, $start - $diff)
                            . $body
                            . mb_substr($text, $end + mb_strlen($m[0]) - $diff);

                        $diff += $len + $taglen + mb_strlen($m[0]) - mb_strlen($body);
                        unset($body);
                    }
                } else {
                    if ($level == 0) {
                        $start = $m[1];
                        $taglen = mb_strlen($m[0]);
                    }
                    $level++;
                }
            }
        }
    }

    /**
     * Callback function for preg_replace_callback use.
     *
     * @param  array  $matches PREG matches
     * @return string
     */
    protected function pregCallback($matches)
    {
        if (preg_match('/h[123456]/', $matches['element'])) {
            return $this->convertHeading($matches['value'], $matches['element']);
        }

        $element = mb_strtolower($matches['element']);

        switch ($matches['element']) {
            case 'p':
                // Replace newlines with spaces.
                $para = str_replace("\n", " ", $matches['value']);

                // Trim trailing and leading whitespace within the tag.
                $para = trim($para);

                // Add trailing newlines for this para.
                return "\n" . $para . "\n";
            case 'br':
                return "\n";
            case 'a':
                // override the link method
                $linkOverride = null;
                if (preg_match('/_html2text_link_(\w+)/', $matches[4], $linkOverrideMatch)) {
                    $linkOverride = $linkOverrideMatch[1];
                }
                // Remove spaces in URL (#1487805)
                $url = str_replace(' ', '', $matches[3]);

                return $this->buildlinkList($url, $matches[5], $linkOverride);
        }

        if (array_key_exists($element, $this->options['elements'])) {
            return $this->convertElement($matches['value'], $matches['element']);
        }

        return '';
    }

    /**
     * @param $string
     *
     * @param $element
     *
     * @return string
     */
    protected function convertHeading($string, $element)
    {
        $string = $this->convertElement($string, $element);

        return "\n\n" . ucfirst($string) . "\n\n";
    }

    /**
     * @param $element
     *
     * @return null|array
     */
    private function getOptionsForElement($element)
    {
        if (!array_key_exists($element, $this->options['elements'])) {
            return null;
        }

        return $this->options['elements'][$element];
    }

    /**
     * Callback function for preg_replace_callback use in PRE content handler.
     *
     * @param  array  $matches PREG matches
     * @return string
     */
    protected function pregPreCallback(/** @noinspection PhpUnusedParameterInspection */ $matches)
    {
        return $this->preContent;
    }

    /**
     * @param string $str
     * @param string $element
     *
     * @return string
     */
    private function convertElement($str, $element)
    {
        $options = $this->getOptionsForElement($element);

        if (!$options) {
            return $str;
        };

        if (isset($options['case']) && $options['case'] != self::OPTION_NONE) {
            $mode = self::$caseModeMapping[$options['case']];

            // string can contain HTML tags
            $chunks = preg_split('/(<[^>]*>)/', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

            // convert only the text between HTML tags
            foreach ($chunks as $i => $chunk) {
                if ($chunk[0] != '<') {

                    $chunk = mb_convert_case($chunk, $mode);
                    $chunk = htmlspecialchars($chunk, $this->htmlFuncFlags, self::ENCODING);
                    $chunk = html_entity_decode($chunk, $this->htmlFuncFlags, self::ENCODING);

                    $chunks[$i] = $chunk;
                }
            }

            $str = trim(implode($chunks));
        }

        if (isset($options['prepend']) && $options['prepend']) {
            $str = $options['prepend'] . $str;
        }

        if (isset($options['append']) && $options['append']) {
            $str = $str . $options['append'];
        }

        return $str;
    }

    /**
     * Strtoupper function with HTML tags and entities handling.
     *
     * @param  string $str Text to convert
     * @return string Converted text
     */
    protected function toupper($str)
    {
        // string can contain HTML tags
        $chunks = preg_split('/(<[^>]*>)/', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        // convert toupper only the text between HTML tags
        foreach ($chunks as $i => $chunk) {
            if ($chunk[0] != '<') {
                $chunks[$i] = $this->strtoupper($chunk);
            }
        }

        return implode($chunks);
    }

    /**
     * Strtoupper multibyte wrapper function with HTML entities handling.
     *
     * @param  string $str Text to convert
     * @return string Converted text
     */
    protected function strtoupper($str)
    {
        $str = html_entity_decode($str, $this->htmlFuncFlags, self::ENCODING);
        $str = mb_strtoupper($str);
        $str = htmlspecialchars($str, $this->htmlFuncFlags, self::ENCODING);

        return $str;
    }
}
