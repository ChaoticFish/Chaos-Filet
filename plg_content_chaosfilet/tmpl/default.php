<?php

/**
 * @package     Chaos Filet
 * @subpackage  plg_content_chaosfilet
 * @version     1.4.0
 * @copyright   (C) 2026 Chaotic Fish Design
 * @license     GNU General Public License version 2 or later
 */

/**
 * Chaos Filet — default layout.
 *
 * Override by copying to templates/YOUR_TEMPLATE/html/plg_content_chaosfilet/default.php,
 * or add a new layout there (e.g. teaser.php) and call it with {article 12:common|teaser}.
 *
 * Available variables:
 *
 * @var array<int, array{name: string, raw: bool, html: string}> $fields  Rendered fields, in tag order
 * @var bool      $wrap          Whether to add the outer wrapper (setting on, and at least one field not marked "!")
 * @var string    $wrapperClass  e.g. "cf-filet cf-filet--12"
 * @var string    $layout        The layout name used in the tag ("default" when none was given)
 * @var \Joomla\Registry\Registry $params  The plugin's settings (e.g. card_heading)
 * @var \stdClass $article       Plain values: id, title, alias, catid, category, language, url, author,
 *                               created, modified, date, hits. Custom layouts (anything other than this
 *                               plugin's own default.php) also get intro, fulltext, body, excerpt,
 *                               image and fulltext_image (each null or {src, alt, width, height}),
 *                               tags (array of titles), and fields (custom fields keyed by name, each
 *                               with ->label, ->value rendered, ->rawvalue).
 */

defined('_JEXEC') or die;

$html = implode('', array_column($fields, 'html'));

if ($html === '') {
    return;
}

echo $wrap ? '<div class="' . htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8') . '">' . $html . '</div>' : $html;
