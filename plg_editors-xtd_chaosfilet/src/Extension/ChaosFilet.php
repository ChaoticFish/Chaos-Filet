<?php

/**
 * @package     Chaos Filet
 * @subpackage  plg_editors-xtd_chaosfilet
 * @version     1.4.0
 * @copyright   (C) 2026 Chaotic Fish Design
 * @license     GNU General Public License version 2 or later
 */

namespace ChaoticFishDesign\Plugin\EditorsXtd\ChaosFilet\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Event\Editor\EditorButtonsSetupEvent;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * "Chaos Filet" editor button: opens a dialog to pick an article, tick the
 * fields you want, and insert a ready-made {article ...} tag for the Chaos
 * Filet content plugin (plg_content_chaosfilet).
 *
 * Article search runs through com_ajax on this plugin:
 *   index.php?option=com_ajax&group=editors-xtd&plugin=chaosfiletSearch&format=json&q=…&TOKEN=1
 * It rides the logged-in session, requires a CSRF token, and requires
 * create or edit permission on com_content.
 *
 * The popup follows the shared CFD editor style (prefix cfp-); its CSS is
 * injected by the script, so there is no stylesheet asset.
 *
 * Built on the lessons from the Chaos Loupe button: the script is attached
 * with registerAndUseScript() (registerScript() alone never outputs it) via
 * an explicit media/ path, and the ajax handler returns its data with
 * $event->addResult(), not `return`.
 */
final class ChaosFilet extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    private const SEARCH_LIMIT = 25;

    /** Strings the button's script reads with Joomla.Text._() (English fallbacks live in the script). */
    private const JS_STRINGS = [
        'TITLE', 'CLOSE', 'INTRO', 'SEC_ARTICLE', 'SEC_FIELDS', 'SEC_CUSTOM', 'SEC_OPTIONS', 'SEARCH',
        'SEARCH_HINT', 'RESULTS', 'NO_RESULTS', 'LOADING', 'SEARCH_FAILED', 'UNPUBLISHED', 'ARCHIVED',
        'FIELDS_HINT', 'SHORTCUTS', 'SHORTCUT_COMMON', 'SHORTCUT_ALL', 'CF_NONE', 'CF_RENDERED', 'CF_RAW',
        'CF_HINT', 'REFERENCE', 'REF_ID', 'REF_ALIAS', 'LAYOUT', 'LAYOUT_DEFAULT', 'LAYOUT_HINT', 'INLINE',
        'INLINE_HINT', 'YOUR_TAG', 'PICK_FIRST', 'COPY', 'COPIED', 'COPY_FAILED', 'RESET', 'CANCEL', 'INSERT',
        'PLUGIN_OFF', 'INSERT_COPIED', 'INSERT_FAILED',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'onEditorButtonsSetup'    => 'onEditorButtonsSetup',
            'onAjaxChaosfiletSearch'  => 'onAjaxChaosfiletSearch',
        ];
    }

    public function onEditorButtonsSetup(EditorButtonsSetupEvent $event): void
    {
        if (\in_array('chaosfilet', $event->getDisabledButtons(), true) || !$this->canUse()) {
            return;
        }

        $doc = $this->getApplication()->getDocument();
        $wa  = $doc->getWebAssetManager();

        $wa->registerAndUseScript(
            'editor-button.chaosfilet-insert',
            'media/plg_editors_xtd_chaosfilet/js/insert-tag.min.js',
            ['version' => '1.4.0'],
            ['type' => 'module'],
            ['editors']
        );

        // Passed as script options rather than the Button's third argument,
        // since whether that argument reaches custom actions is unverified.
        $doc->addScriptOptions('plg_editors_xtd_chaosfilet', [
            'endpoint'       => Uri::base(true) . '/index.php?option=com_ajax&group=editors-xtd&plugin=chaosfiletSearch&format=json',
            'token'          => Session::getFormToken(),
            'pluginEnabled'  => PluginHelper::isEnabled('content', 'chaosfilet'),
            'layouts'        => $this->getLayouts(),
            'customFields'   => $this->getCustomFields(),
        ]);

        foreach (self::JS_STRINGS as $key) {
            Text::script('PLG_EDITORS-XTD_CHAOSFILET_JS_' . $key);
        }

        $event->getButtonsRegistry()->add(new Button(
            'chaosfilet',
            [
                'text'   => Text::_('PLG_EDITORS-XTD_CHAOSFILET_BUTTON_TEXT'),
                'icon'   => 'copy',
                'action' => 'chaosfilet-insert',
            ]
        ));
    }

    /**
     * Article search for the dialog. Empty query → most recently modified.
     * A numeric query also matches that exact ID.
     */
    public function onAjaxChaosfiletSearch(Event $event): void
    {
        if (!Session::checkToken('get')) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN_NOTICE'), 403);
        }

        if (!$this->canUse()) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $q  = trim((string) $this->getApplication()->getInput()->getString('q', ''));
        $q  = mb_substr($q, 0, 100);
        $db = $this->getDatabase();

        $dupes = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__content', 'a2'))
            ->where($db->quoteName('a2.alias') . ' = ' . $db->quoteName('a.alias'))
            ->where($db->quoteName('a2.state') . ' <> -2');

        $query = $db->createQuery()
            ->select($db->quoteName(['a.id', 'a.title', 'a.alias', 'a.state', 'a.language']))
            ->select($db->quoteName('c.title', 'category'))
            ->select($db->quoteName('c.alias', 'category_alias'))
            ->select('(' . $dupes . ') AS ' . $db->quoteName('alias_count'))
            ->from($db->quoteName('#__content', 'a'))
            ->join('LEFT', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->where($db->quoteName('a.state') . ' IN (0, 1, 2)')
            ->order($db->quoteName('a.modified') . ' DESC')
            ->setLimit(self::SEARCH_LIMIT);

        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';

            $conditions = [
                $db->quoteName('a.title') . ' LIKE :like1',
                $db->quoteName('a.alias') . ' LIKE :like2',
            ];

            $query->bind(':like1', $like)->bind(':like2', $like);

            if (ctype_digit($q)) {
                $id           = (int) $q;
                $conditions[] = $db->quoteName('a.id') . ' = :id';
                $query->bind(':id', $id, ParameterType::INTEGER);
            }

            $query->extendWhere('AND', $conditions, 'OR');
        }

        $rows = $db->setQuery($query)->loadAssocList() ?: [];

        $results = array_map(static fn (array $r): array => [
            'id'             => (int) $r['id'],
            'title'          => (string) $r['title'],
            'alias'          => (string) $r['alias'],
            'state'          => (int) $r['state'],
            'language'       => (string) $r['language'],
            'category'       => (string) ($r['category'] ?? ''),
            'category_alias' => (string) ($r['category_alias'] ?? ''),
            'alias_unique'   => (int) $r['alias_count'] <= 1,
        ], $rows);

        $this->setResult($event, $results);
    }

    private function setResult(Event $event, array $data): void
    {
        if ($event instanceof ResultAwareInterface) {
            $event->addResult($data);

            return;
        }

        $result   = $event->getArgument('result') ?: [];
        $result[] = $data;
        $event->setArgument('result', $result);
    }

    private function canUse(): bool
    {
        $user = $this->getApplication()->getIdentity();

        return $user && $user->id && (
            $user->authorise('core.create', 'com_content')
            || $user->authorise('core.edit', 'com_content')
            || $user->authorise('core.edit.own', 'com_content')
        );
    }

    /**
     * Layout names the content plugin can use: its own tmpl/ files plus any
     * overrides in the default site template.
     *
     * @return array<int, string>
     */
    private function getLayouts(): array
    {
        $dirs = [JPATH_PLUGINS . '/content/chaosfilet/tmpl'];

        try {
            $db       = $this->getDatabase();
            $template = $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName('template'))
                    ->from($db->quoteName('#__template_styles'))
                    ->where($db->quoteName('client_id') . ' = 0')
                    ->where($db->quoteName('home') . ' = ' . $db->quote('1'))
            )->loadResult();

            if ($template) {
                $dirs[] = JPATH_SITE . '/templates/' . $template . '/html/plg_content_chaosfilet';
            }
        } catch (\Throwable $e) {
            // Layout list is a convenience; the dialog still works without it.
        }

        $names = [];

        foreach ($dirs as $dir) {
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                $name = basename($file, '.php');

                if ($name !== 'default' && preg_match('/^[a-z0-9_-]+$/', $name)) {
                    $names[$name] = true;
                }
            }
        }

        $names = array_keys($names);
        sort($names);

        return $names;
    }

    /**
     * Published article custom fields, for the dialog's custom-field picker.
     *
     * @return array<int, array{name: string, label: string}>
     */
    private function getCustomFields(): array
    {
        try {
            $db   = $this->getDatabase();
            $rows = $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName(['name', 'label']))
                    ->from($db->quoteName('#__fields'))
                    ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->order($db->quoteName('ordering') . ' ASC')
            )->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(static fn (array $r): array => [
            'name'  => (string) $r['name'],
            'label' => Text::_((string) $r['label']),
        ], $rows);
    }
}
