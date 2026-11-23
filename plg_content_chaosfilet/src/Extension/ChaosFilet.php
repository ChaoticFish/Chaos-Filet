<?php

/**
 * @package     Chaos Filet
 * @subpackage  plg_content_chaosfilet
 * @version     1.3.0
 * @copyright   (C) 2026 Chaotic Fish Design
 * @license     GNU General Public License version 2 or later
 */

namespace ChaoticFishDesign\Plugin\Content\ChaosFilet\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

/**
 * Pulls fields from one article into another article or a Custom HTML
 * module via {article REF:field1,field2|layout} tags.
 *
 *   REF     numeric ID, an article alias, or category-alias/article-alias
 *   fields  optional (defaults to "body"); a trailing "!" on a field drops
 *           its wrapper <div> so it can sit inline inside a sentence or an
 *           attribute
 *   layout  optional; picks tmpl/<layout>.php, overridable from the site
 *           template at html/plg_content_chaosfilet/<layout>.php
 *
 * Tags inside <pre> and <code> are never expanded, and \{article ...}
 * prints the tag literally, so documentation pages can show the syntax.
 *
 * Supported contexts: com_content views, and mod_custom modules that have
 * "Prepare Content" set to Yes.
 */
final class ChaosFilet extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    /** Contexts we will process. Everything else is left alone. */
    private const ALLOWED_CONTEXT_PREFIXES = ['com_content.', 'mod_custom'];

    /** Fields rendered by the "common" shortcut, in output order. */
    private const COMMON_FIELDS = ['title', 'image', 'body', 'date', 'author', 'link'];

    /** Fields rendered by the "all" shortcut, in output order. Custom fields are appended after these. */
    private const ALL_FIELDS = [
        'id', 'title', 'alias', 'url', 'link', 'readmore',
        'intro', 'fulltext', 'body', 'excerpt',
        'image', 'fulltext_image',
        'date', 'modified', 'author', 'category', 'tags', 'hits',
    ];

    /** Every built-in keyword renderField() understands. */
    private const BUILTIN_FIELDS = [
        'id', 'title', 'alias', 'url', 'link', 'readmore',
        'intro', 'fulltext', 'body', 'excerpt',
        'image', 'fulltext_image',
        'date', 'modified', 'author', 'category', 'tags', 'hits',
    ];

    /**
     * {article REF:fields|layout}, optionally preceded by a backslash to
     * escape it. Groups: 1 escape, 2 ref, 3 fields, 4 layout.
     */
    private const TAG_PATTERN = '/(\\\\?)\{article\s+([^\s:|{}]+)(?:\s*:\s*([^|{}]*?))?(?:\s*\|\s*([a-z0-9_-]*))?\s*\}/i';

    /** <pre>…</pre> and <code>…</code> blocks, which are passed through untouched. */
    private const SKIP_PATTERN = '#(<pre\b[^>]*>.*?</pre\s*>|<code\b[^>]*>.*?</code\s*>)#is';

    /** How deep nested tags may expand when "Run Content Plugins" is on. */
    private const MAX_DEPTH = 3;

    /** @var array<int, \stdClass|null> Per-request cache of article rows, keyed by ID. */
    private array $articleCache = [];

    /** @var array<string, array{0: int|null, 1: int}> Per-request cache of alias lookups: [id, match count]. */
    private array $aliasCache = [];

    /** @var array<int, array<int, string>> Per-request cache of tag titles, keyed by article ID. */
    private array $tagsCache = [];

    /** @var array<int, array<string, object>> Per-request cache of custom fields, keyed by article ID then field name. */
    private array $fieldsCache = [];

    /** @var array<int, true> Article IDs already hit-incremented this request. */
    private array $hitIncremented = [];

    /** @var array<int, int> Article IDs currently being expanded (outermost first), for loop detection. */
    private array $renderStack = [];

    /** ID of the article whose text is being scanned, when that's knowable. */
    private ?int $hostId = null;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        $context = $event->getContext();

        // Never expand tags while Smart Search is indexing: it runs under
        // the indexer's own permissions, not the visitor's, so access
        // checks here would be meaningless and could leak restricted
        // content into the search index.
        if ($context === 'com_finder.indexer') {
            return;
        }

        if (!$this->getApplication()->isClient('site') || !$this->isAllowedContext($context)) {
            return;
        }

        $item = $event->getItem();

        if (!\is_object($item) || !isset($item->text) || !\is_string($item->text)) {
            return;
        }

        if (strpos($item->text, '{article') === false) {
            return;
        }

        // Only com_content contexts carry an article as the item; a
        // mod_custom item's id is a module ID, not an article ID.
        $previousHost  = $this->hostId;
        $this->hostId  = (strpos($context, 'com_content.') === 0 && !empty($item->id)) ? (int) $item->id : null;

        try {
            $item->text = $this->expandText($item->text);
        } finally {
            $this->hostId = $previousHost;
        }
    }

    private function isAllowedContext(string $context): bool
    {
        foreach (self::ALLOWED_CONTEXT_PREFIXES as $prefix) {
            if (strpos($context, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Expands every tag outside <pre>/<code>. Single pass: replacement text
     * is not re-scanned here, so a pulled-in tag can't loop. (Nested tags
     * only expand through the optional content-plugin pass, which has its
     * own depth and loop guards.)
     */
    private function expandText(string $text): string
    {
        $parts = preg_split(self::SKIP_PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $text;
        }

        foreach ($parts as $i => $part) {
            // With one capture group, odd indexes are the skipped blocks.
            if ($i % 2 === 1 || strpos($part, '{article') === false) {
                continue;
            }

            $parts[$i] = preg_replace_callback(self::TAG_PATTERN, [$this, 'replaceTag'], $part) ?? $part;
        }

        return implode('', $parts);
    }

    /**
     * @param array<int, string> $m
     */
    private function replaceTag(array $m): string
    {
        // \{article ...} → print the tag itself, minus the backslash.
        if ($m[1] !== '') {
            return substr($m[0], 1);
        }

        $ref    = $m[2];
        $fields = $this->parseFields($m[3] ?? '');
        $layout = strtolower($m[4] ?? '');
        $layout = $layout === '' ? 'default' : $layout;

        [$article, $matches] = $this->resolveArticle($ref);

        if ($article === null) {
            return $this->canEditAny()
                ? $this->renderNote('missing', Text::sprintf('PLG_CONTENT_CHAOSFILET_NOTE_NOT_FOUND', $ref))
                : '';
        }

        $id      = (int) $article->id;
        $canEdit = $this->canEditArticle($article);

        if ($id === $this->hostId || \in_array($id, $this->renderStack, true)) {
            return $canEdit ? $this->renderNote('loop', Text::sprintf('PLG_CONTENT_CHAOSFILET_NOTE_LOOP', $id)) : '';
        }

        $blocked = $this->getBlockedReason($article);

        if ($blocked !== null) {
            return $canEdit ? $this->renderNote($blocked[0], $blocked[1]) : '';
        }

        $this->maybeIncrementHits($article);

        $notes = [];

        if ($matches > 1) {
            $notes[] = Text::sprintf('PLG_CONTENT_CHAOSFILET_NOTE_AMBIGUOUS', $ref, $matches, $id);
        }

        $layoutPath     = PluginHelper::getLayoutPath('content', 'chaosfilet', $layout);
        $pluginDefault  = JPATH_PLUGINS . '/content/chaosfilet/tmpl/default.php';
        $customLayout   = $layoutPath !== $pluginDefault;

        if ($layout !== 'default' && !$customLayout) {
            $notes[] = Text::sprintf('PLG_CONTENT_CHAOSFILET_NOTE_NO_LAYOUT', $layout);
        }

        $this->renderStack[] = $id;

        try {
            $rendered = [];

            foreach ($this->expandFieldList($article, $fields) as $entry) {
                if ($entry['label']) {
                    $rendered[] = [
                        'name' => 'label',
                        'raw'  => false,
                        'html' => '<div class="cf-debug-label">' . $this->esc($entry['name']) . ':</div>',
                    ];
                    continue;
                }

                $html = $this->renderField($article, $entry['name'], $entry['raw']);

                if ($html === null) {
                    $notes[] = Text::sprintf('PLG_CONTENT_CHAOSFILET_NOTE_UNKNOWN_FIELD', $entry['name']);
                    continue;
                }

                $rendered[] = ['name' => $entry['name'], 'raw' => $entry['raw'], 'html' => $html];
            }

            $anyWrapped = false;

            foreach ($rendered as $r) {
                $anyWrapped = $anyWrapped || !$r['raw'];
            }

            $wrapperClass = 'cf-filet cf-filet--' . $id;

            if ($layout !== 'default') {
                $wrapperClass .= ' cf-filet--' . $this->cssClass($layout);
            }

            $output = $this->renderLayout($layoutPath, [
                'article'      => $this->buildArticleData($article, $customLayout),
                'fields'       => $rendered,
                'wrap'         => $anyWrapped && (bool) $this->params->get('wrap_output', 1),
                'wrapperClass' => $wrapperClass,
                'layout'       => $layout,
            ]);
        } finally {
            array_pop($this->renderStack);
        }

        if ($canEdit) {
            foreach ($notes as $note) {
                $output .= $this->renderNote('info', $note);
            }
        }

        return $output;
    }

    /**
     * Splits a tag's field list into entries. A trailing "!" marks the
     * field raw (no wrapper div). Built-in keywords are lowercased so
     * {article 12:TITLE} still works; field:/fieldraw: aliases keep their
     * case after the prefix, since Joomla field names are matched exactly.
     *
     * @return array<int, array{name: string, raw: bool}>
     */
    private function parseFields(string $fieldsRaw): array
    {
        $fields = [];

        foreach (explode(',', $fieldsRaw) as $token) {
            $token = trim($token);
            $raw   = false;

            if (substr($token, -1) === '!') {
                $raw   = true;
                $token = rtrim(substr($token, 0, -1));
            }

            if ($token === '') {
                continue;
            }

            if (preg_match('/^(field|fieldraw)\s*:\s*(.+)$/i', $token, $fm)) {
                $token = strtolower($fm[1]) . ':' . trim($fm[2]);
            } else {
                $token = strtolower($token);
            }

            $fields[] = ['name' => $token, 'raw' => $raw];
        }

        return $fields ?: [['name' => 'body', 'raw' => false]];
    }

    /**
     * Flattens the "common" and "all" shortcuts into individual fields.
     * A "!" on a shortcut applies to every field it expands to.
     *
     * @param array<int, array{name: string, raw: bool}> $fields
     *
     * @return array<int, array{name: string, raw: bool, label: bool}>
     */
    private function expandFieldList(\stdClass $article, array $fields): array
    {
        $out = [];

        foreach ($fields as $f) {
            if ($f['name'] === 'common') {
                foreach (self::COMMON_FIELDS as $sub) {
                    $out[] = ['name' => $sub, 'raw' => $f['raw'], 'label' => false];
                }
                continue;
            }

            if ($f['name'] === 'all') {
                $names = self::ALL_FIELDS;

                foreach (array_keys($this->getCustomFields($article)) as $alias) {
                    $names[] = 'field:' . $alias;
                }

                foreach ($names as $sub) {
                    $out[] = ['name' => $sub, 'raw' => false, 'label' => true];
                    $out[] = ['name' => $sub, 'raw' => $f['raw'], 'label' => false];
                }
                continue;
            }

            $out[] = ['name' => $f['name'], 'raw' => $f['raw'], 'label' => false];
        }

        return $out;
    }

    /**
     * Returns rendered HTML ('' when the field is simply empty), or null
     * when the keyword isn't a field this plugin knows.
     */
    private function renderField(\stdClass $article, string $field, bool $raw): ?string
    {
        if (strpos($field, 'field:') === 0) {
            $alias = substr($field, 6);
            $cf    = $this->getCustomFields($article)[$alias] ?? null;

            return $cf === null ? '' : $this->wrapField($alias, (string) $cf->value, $raw);
        }

        if (strpos($field, 'fieldraw:') === 0) {
            $alias = substr($field, 9);
            $cf    = $this->getCustomFields($article)[$alias] ?? null;

            if ($cf === null) {
                return '';
            }

            $value = $cf->rawvalue;
            $value = \is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;

            return $this->wrapField($alias, $this->esc($value), $raw);
        }

        if (!\in_array($field, self::BUILTIN_FIELDS, true)) {
            return null;
        }

        switch ($field) {
            case 'id':
                return $this->wrapField('id', (string) (int) $article->id, $raw);

            case 'title':
                return $this->wrapField('title', $this->esc($article->title), $raw);

            case 'alias':
                return $this->wrapField('alias', $this->esc($article->alias), $raw);

            case 'url':
                return $this->wrapField('url', $this->esc($this->articleUrl($article)), $raw);

            case 'link':
                return '<a class="' . $this->fieldClasses('link') . '" href="' . $this->esc($this->articleUrl($article)) . '">'
                    . $this->esc($article->title) . '</a>';

            case 'readmore':
                return '<a class="' . $this->fieldClasses('readmore') . '" href="' . $this->esc($this->articleUrl($article)) . '">'
                    . $this->esc(Text::_('PLG_CONTENT_CHAOSFILET_READMORE'))
                    . '<span class="visually-hidden">: ' . $this->esc($article->title) . '</span>'
                    . ' <span aria-hidden="true">&rarr;</span></a>';

            case 'intro':
                return $this->wrapField('intro', $this->processText((string) $article->introtext, $article), $raw);

            case 'fulltext':
                return $this->wrapField('fulltext', $this->processText((string) $article->fulltext, $article), $raw);

            case 'body':
                $body = trim(((string) $article->introtext) . ' ' . ((string) $article->fulltext));

                return $this->wrapField('body', $this->processText($body, $article), $raw);

            case 'excerpt':
                $excerpt = $this->excerpt($article);

                return $this->wrapField('excerpt', $excerpt === '' ? '' : $this->esc($excerpt), $raw);

            case 'image':
                return $this->renderImage($this->imageData($article, 'intro'), 'image');

            case 'fulltext_image':
                return $this->renderImage($this->imageData($article, 'fulltext'), 'fulltext_image');

            case 'date':
                return $this->wrapField('date', $this->formatDate($article->created ?? ''), $raw);

            case 'modified':
                return $this->wrapField('modified', $this->formatDate($article->modified ?? ''), $raw);

            case 'author':
                return $this->wrapField('author', $this->esc($this->authorName($article)), $raw);

            case 'category':
                return $this->wrapField('category', $this->esc((string) ($article->category_name ?? '')), $raw);

            case 'tags':
                $tags = array_map([$this, 'esc'], $this->getArticleTags((int) $article->id));

                return $this->wrapField('tags', implode(', ', $tags), $raw);

            case 'hits':
                return $this->wrapField('hits', (string) (int) ($article->hits ?? 0), $raw);
        }

        return null;
    }

    /**
     * The values a custom layout can use directly, so a template override
     * doesn't have to parse HTML back out of the rendered fields.
     */
    private function buildArticleData(\stdClass $article, bool $full): \stdClass
    {
        $d = new \stdClass();

        $d->id       = (int) $article->id;
        $d->title    = (string) $article->title;
        $d->alias    = (string) $article->alias;
        $d->catid    = (int) $article->catid;
        $d->category = (string) ($article->category_name ?? '');
        $d->language = (string) $article->language;
        $d->url      = $this->articleUrl($article);
        $d->author   = $this->authorName($article);
        $d->created  = (string) ($article->created ?? '');
        $d->modified = (string) ($article->modified ?? '');
        $d->date     = $this->formatDate($d->created);
        $d->hits     = (int) ($article->hits ?? 0);

        // Heavier values (extra queries or plugin passes) only for layouts
        // other than the plugin's own default, which never reads them.
        if ($full) {
            $d->intro          = $this->processText((string) $article->introtext, $article);
            $d->fulltext       = $this->processText((string) $article->fulltext, $article);
            $d->body           = trim($d->intro . ' ' . $d->fulltext);
            $d->excerpt        = $this->excerpt($article);
            $d->image          = $this->imageData($article, 'intro');
            $d->fulltext_image = $this->imageData($article, 'fulltext');
            $d->tags           = $this->getArticleTags($d->id);
            $d->fields         = $this->getCustomFields($article);
        }

        return $d;
    }

    /**
     * @param array<string, mixed> $displayData
     */
    private function renderLayout(string $path, array $displayData): string
    {
        if (!is_file($path)) {
            return implode('', array_column($displayData['fields'], 'html'));
        }

        ob_start();

        try {
            (static function (string $__path, array $__data): void {
                extract($__data, EXTR_SKIP);
                include $__path;
            })($path, $displayData);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * Runs pulled-in text through the site's content plugins, when that
     * option is on. Our own plugin is one of them, so nested tags expand
     * too — bounded by MAX_DEPTH and the render stack's loop check.
     */
    private function processText(string $html, \stdClass $article): string
    {
        if ($html === '' || !(bool) $this->params->get('process_plugins', 0) || \count($this->renderStack) > self::MAX_DEPTH) {
            return $html;
        }

        $item           = new \stdClass();
        $item->id       = (int) $article->id;
        $item->catid    = (int) $article->catid;
        $item->title    = (string) $article->title;
        $item->alias    = (string) $article->alias;
        $item->language = (string) $article->language;
        $item->access   = (int) $article->access;
        $item->text     = $html;

        $params = new Registry((string) ($article->attribs ?? ''));

        $this->getApplication()->getDispatcher()->dispatch(
            'onContentPrepare',
            new ContentPrepareEvent('onContentPrepare', [
                'context' => 'com_content.article',
                'subject' => $item,
                'params'  => $params,
                'page'    => 0,
            ])
        );

        return (string) $item->text;
    }

    /**
     * @return array{0: string, 1: string}|null [note type, message] when the visitor may not see this article
     */
    private function getBlockedReason(\stdClass $article): ?array
    {
        if ((int) $article->state !== 1) {
            return ['unpublished', Text::_('PLG_CONTENT_CHAOSFILET_NOTE_UNPUBLISHED')];
        }

        $now = Factory::getDate()->toSql();

        if ((!empty($article->publish_up) && $article->publish_up > $now)
            || (!empty($article->publish_down) && $article->publish_down < $now)) {
            return ['unpublished', Text::_('PLG_CONTENT_CHAOSFILET_NOTE_UNPUBLISHED')];
        }

        if ((int) ($article->category_published ?? 0) <= 0) {
            return ['unpublished', Text::_('PLG_CONTENT_CHAOSFILET_NOTE_CATEGORY_UNPUBLISHED')];
        }

        $levels = $this->getApplication()->getIdentity()->getAuthorisedViewLevels();

        if (!\in_array((int) $article->access, $levels, true)) {
            return ['restricted', Text::_('PLG_CONTENT_CHAOSFILET_NOTE_RESTRICTED')];
        }

        if (!\in_array((int) ($article->category_access ?? 0), $levels, true)) {
            return ['restricted', Text::_('PLG_CONTENT_CHAOSFILET_NOTE_CATEGORY_RESTRICTED')];
        }

        return null;
    }

    private function canEditArticle(\stdClass $article): bool
    {
        $user  = $this->getApplication()->getIdentity();
        $asset = 'com_content.article.' . (int) $article->id;

        return $user->authorise('core.edit', $asset)
            || $user->authorise('core.edit.state', $asset)
            || ($user->id && (int) $article->created_by === (int) $user->id && $user->authorise('core.edit.own', $asset));
    }

    /** For badges that aren't about one specific article (e.g. "not found"). */
    private function canEditAny(): bool
    {
        $user = $this->getApplication()->getIdentity();

        return $user->authorise('core.edit', 'com_content')
            || $user->authorise('core.create', 'com_content')
            || $user->authorise('core.edit.own', 'com_content');
    }

    private function renderNote(string $type, string $message): string
    {
        $badge = [
            'restricted' => 'bg-secondary',
            'info'       => 'bg-info text-dark',
            'missing'    => 'bg-danger',
            'loop'       => 'bg-danger',
        ][$type] ?? 'bg-warning text-dark';

        return '<span class="cf-note cf-note--' . $this->cssClass($type) . ' badge ' . $badge . '">'
            . $this->esc($message) . '</span>';
    }

    private function renderImage(?\stdClass $img, string $field): string
    {
        if ($img === null) {
            return '';
        }

        $attrs = 'class="' . $this->fieldClasses($field) . '" src="' . $this->esc($img->src) . '" alt="' . $this->esc($img->alt) . '"';

        if ($img->width > 0 && $img->height > 0) {
            $attrs .= ' width="' . $img->width . '" height="' . $img->height . '"';
        }

        if ((bool) $this->params->get('lazy_images', 1)) {
            $attrs .= ' loading="lazy" decoding="async"';
        }

        return '<img ' . $attrs . '>';
    }

    /**
     * @param string $which 'intro' or 'fulltext'
     */
    private function imageData(\stdClass $article, string $which): ?\stdClass
    {
        $images = json_decode((string) $article->images);
        $key    = 'image_' . $which;

        if (!\is_object($images) || empty($images->{$key})) {
            return null;
        }

        // cleanImageURL strips Joomla's "#joomlaImage://…?width=&height="
        // suffix and hands back the dimensions it carried.
        $clean = HTMLHelper::_('cleanImageURL', (string) $images->{$key});
        $url   = (string) $clean->url;
        $attrs = (array) ($clean->attributes ?? []);

        if (!preg_match('#^([a-z][a-z0-9+.\-]*:)?//#i', $url) && strpos($url, '/') !== 0) {
            $url = Uri::root() . $url;
        }

        $altKey = $key . '_alt';

        return (object) [
            'src'    => $url,
            'alt'    => (string) ($images->{$altKey} ?? ''),
            'width'  => (int) ($attrs['width'] ?? 0),
            'height' => (int) ($attrs['height'] ?? 0),
        ];
    }

    private function articleUrl(\stdClass $article): string
    {
        return Route::_(RouteHelper::getArticleRoute((int) $article->id, (int) $article->catid, $article->language ?: '*'));
    }

    private function authorName(\stdClass $article): string
    {
        return !empty($article->created_by_alias) ? (string) $article->created_by_alias : (string) ($article->author_name ?? '');
    }

    private function formatDate(string $value): string
    {
        if ($value === '' || strpos($value, '0000-00-00') === 0) {
            return '';
        }

        $format = trim((string) $this->params->get('date_format', 'DATE_FORMAT_LC3'));

        if ($format === '') {
            $format = 'DATE_FORMAT_LC3';
        }

        // Language keys (DATE_FORMAT_LC3 etc.) follow the site language;
        // anything else is used as a literal PHP date() format.
        if (strpos($format, 'DATE_FORMAT_') === 0) {
            $format = Text::_($format);
        }

        return $this->esc(HTMLHelper::_('date', $value, $format));
    }

    private function excerpt(\stdClass $article): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $article->introtext), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if ($text === '') {
            return '';
        }

        $limit = (int) $this->params->get('excerpt_limit', 40);

        if ($limit < 1) {
            return $text;
        }

        if ((string) $this->params->get('excerpt_unit', 'words') === 'characters') {
            return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit)) . '…' : $text;
        }

        $words = preg_split('/\s+/u', $text);

        return \count($words) > $limit ? implode(' ', \array_slice($words, 0, $limit)) . '…' : $text;
    }

    /** Wraps field output in a div, unless the field was marked raw with "!". */
    private function wrapField(string $field, string $content, bool $raw): string
    {
        if ($content === '' || $raw) {
            return $content;
        }

        return '<div class="' . $this->fieldClasses($field) . '">' . $content . '</div>';
    }

    private function fieldClasses(string $field): string
    {
        $class = $this->cssClass($field);

        return 'cf-field cf-field--' . $class . ' ' . $class;
    }

    private function cssClass(string $name): string
    {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower($name));
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Off by default. Increments the source article's hit count once per
     * article per page load, no matter how many times its tag appears. A
     * plain increment, not com_content's hit() model method, to avoid that
     * component's event side effects for what's meant to be a counter bump.
     */
    private function maybeIncrementHits(\stdClass $article): void
    {
        $id = (int) $article->id;

        if (!(bool) $this->params->get('increment_hits', 0) || isset($this->hitIncremented[$id])) {
            return;
        }

        $this->hitIncremented[$id] = true;

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->update($db->quoteName('#__content'))
            ->set($db->quoteName('hits') . ' = ' . $db->quoteName('hits') . ' + 1')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }

    /**
     * @return array<string, object> Custom fields keyed by field name (alias), each with ->value (rendered) and ->rawvalue
     */
    private function getCustomFields(\stdClass $article): array
    {
        $id = (int) $article->id;

        if (isset($this->fieldsCache[$id])) {
            return $this->fieldsCache[$id];
        }

        $byName = [];

        foreach (FieldsHelper::getFields('com_content.article', clone $article, true) as $field) {
            $byName[(string) $field->name] = $field;
        }

        return $this->fieldsCache[$id] = $byName;
    }

    /**
     * @return array<int, string>
     */
    private function getArticleTags(int $id): array
    {
        if (\array_key_exists($id, $this->tagsCache)) {
            return $this->tagsCache[$id];
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName('t.title'))
            ->from($db->quoteName('#__contentitem_tag_map', 'm'))
            ->join('INNER', $db->quoteName('#__tags', 't'), $db->quoteName('t.id') . ' = ' . $db->quoteName('m.tag_id'))
            ->where($db->quoteName('m.type_alias') . ' = ' . $db->quote('com_content.article'))
            ->where($db->quoteName('m.content_item_id') . ' = :id')
            ->where($db->quoteName('t.published') . ' = 1')
            ->whereIn($db->quoteName('t.access'), $this->getApplication()->getIdentity()->getAuthorisedViewLevels())
            ->bind(':id', $id, ParameterType::INTEGER)
            ->order($db->quoteName('t.title') . ' ASC');

        return $this->tagsCache[$id] = $db->setQuery($query)->loadColumn() ?: [];
    }

    /**
     * Resolves a tag's REF — a numeric ID, an alias, or category/alias.
     *
     * @return array{0: \stdClass|null, 1: int} [article row, how many articles the alias matched]
     */
    private function resolveArticle(string $ref): array
    {
        if (ctype_digit($ref)) {
            return [(int) $ref > 0 ? $this->getArticleRow((int) $ref) : null, 1];
        }

        if (!isset($this->aliasCache[$ref])) {
            $this->aliasCache[$ref] = $this->lookupAlias($ref);
        }

        [$id, $count] = $this->aliasCache[$ref];

        return [$id === null ? null : $this->getArticleRow($id), $count];
    }

    /**
     * Aliases are only unique within a category (and language), so this
     * can match several articles. On multilingual sites, articles in the
     * visitor's language or "All" are considered; the lowest ID wins and
     * editors see a badge saying the alias was ambiguous.
     *
     * @return array{0: int|null, 1: int}
     */
    private function lookupAlias(string $ref): array
    {
        $catAlias = null;
        $alias    = $ref;

        if (strpos($ref, '/') !== false) {
            [$catAlias, $alias] = explode('/', $ref, 2);
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName('a.id'))
            ->from($db->quoteName('#__content', 'a'))
            ->where($db->quoteName('a.alias') . ' = :alias')
            ->bind(':alias', $alias)
            ->order($db->quoteName('a.id') . ' ASC');

        if ($catAlias !== null) {
            $query->join('INNER', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
                ->where($db->quoteName('c.alias') . ' = :calias')
                ->bind(':calias', $catAlias);
        }

        if (Multilanguage::isEnabled()) {
            $query->whereIn($db->quoteName('a.language'), [$this->getApplication()->getLanguage()->getTag(), '*'], ParameterType::STRING);
        }

        $ids = $db->setQuery($query)->loadColumn() ?: [];

        return [$ids ? (int) $ids[0] : null, \count($ids)];
    }

    private function getArticleRow(int $id): ?\stdClass
    {
        if (\array_key_exists($id, $this->articleCache)) {
            return $this->articleCache[$id];
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName([
                'a.id', 'a.title', 'a.alias', 'a.introtext', 'a.fulltext', 'a.images', 'a.attribs',
                'a.state', 'a.access', 'a.catid', 'a.publish_up', 'a.publish_down',
                'a.created', 'a.modified', 'a.hits', 'a.created_by', 'a.created_by_alias', 'a.language',
            ]))
            ->select($db->quoteName('u.name', 'author_name'))
            ->select($db->quoteName('c.title', 'category_name'))
            ->select($db->quoteName('c.published', 'category_published'))
            ->select($db->quoteName('c.access', 'category_access'))
            ->from($db->quoteName('#__content', 'a'))
            ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by'))
            ->join('LEFT', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->where($db->quoteName('a.id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $this->articleCache[$id] = $db->setQuery($query)->loadObject() ?: null;
    }
}
