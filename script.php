<?php

/**
 * @package     Chaos Filet
 * @subpackage  pkg_chaosfilet
 * @version     1.4.0
 * @copyright   (C) 2026 Chaotic Fish Design
 * @license     GNU General Public License version 2 or later
 *
 * Install guard and housekeeping for the Chaos Filet package.
 *
 * - Refuses to install or update on PHP below 8.2 or Joomla below 5.
 * - Switches on only the plugins this install actually created. A site that
 *   already had the separate plugins (1.2.x / 1.0.x) keeps each one on or
 *   off exactly as it was; to Joomla that's still the package's "install",
 *   so the check is per plugin, not per package route.
 * - Deletes files earlier versions left behind (old language file names,
 *   READMEs that are no longer shipped, and the editor popup's old stylesheet).
 *
 * Kept free of newer PHP syntax so it parses, and can say why, on old servers.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class ($container->get(AdministratorApplication::class), $container->get(DatabaseInterface::class)) implements InstallerScriptInterface {
                const MIN_PHP    = '8.2.0';
                const MIN_JOOMLA = '5.0.0';

                /** Plugin groups this package installs; every child's element is "chaosfilet". */
                const GROUPS = ['content', 'editors-xtd'];

                private $app;
                private $db;

                /** Groups whose chaosfilet plugin existed before this run (filled in preflight). */
                private $existing = [];

                public function __construct($app, $db)
                {
                    $this->app = $app;
                    $this->db  = $db;
                }

                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type === 'uninstall') {
                        return true;
                    }

                    // The package's own language file, from the zip (install) or the site (update).
                    $this->app->getLanguage()->load('pkg_chaosfilet.sys', __DIR__)
                        || $this->app->getLanguage()->load('pkg_chaosfilet.sys', JPATH_ADMINISTRATOR);

                    if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
                        $this->app->enqueueMessage(Text::sprintf('PKG_CHAOSFILET_NEEDS_PHP', '8.2', PHP_VERSION), 'error');

                        return false;
                    }

                    if (version_compare(JVERSION, self::MIN_JOOMLA, '<')) {
                        $this->app->enqueueMessage(Text::sprintf('PKG_CHAOSFILET_NEEDS_JOOMLA', (new Version())->getShortVersion()), 'error');

                        return false;
                    }

                    $this->existing = $this->installedGroups();

                    return true;
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type === 'uninstall') {
                        return true;
                    }

                    // Only plugins that didn't exist before this run get switched on.
                    foreach (array_diff(self::GROUPS, $this->existing) as $group) {
                        $this->enable($group);
                    }

                    $this->removeLeftovers();

                    $version = (string) $adapter->getManifest()->version;

                    if ($type === 'update') {
                        $this->app->enqueueMessage(Text::sprintf('PKG_CHAOSFILET_UPDATED', $version), 'message');
                    } elseif ($this->existing) {
                        $this->app->enqueueMessage(Text::sprintf('PKG_CHAOSFILET_ADOPTED', $version), 'message');
                    } else {
                        $this->app->enqueueMessage(Text::_('PKG_CHAOSFILET_INSTALLED'), 'message');
                    }

                    return true;
                }

                /** @return string[] groups (folders) that already have a chaosfilet plugin */
                private function installedGroups(): array
                {
                    $query = $this->query()
                        ->select($this->db->quoteName('folder'))
                        ->from($this->db->quoteName('#__extensions'))
                        ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                        ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('chaosfilet'))
                        ->whereIn($this->db->quoteName('folder'), self::GROUPS, \Joomla\Database\ParameterType::STRING);

                    return $this->db->setQuery($query)->loadColumn() ?: [];
                }

                private function enable(string $group): void
                {
                    $query = $this->query()
                        ->update($this->db->quoteName('#__extensions'))
                        ->set($this->db->quoteName('enabled') . ' = 1')
                        ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                        ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('chaosfilet'))
                        ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote($group));

                    $this->db->setQuery($query)->execute();
                }

                /** Files earlier versions installed that nothing uses any more. */
                private function removeLeftovers(): void
                {
                    $files = [
                        // 1.1.0 used the old "en-GB." prefix; 1.2.0 switched to plg_content_chaosfilet.ini.
                        JPATH_ADMINISTRATOR . '/language/en-GB/en-GB.plg_content_chaosfilet.ini',
                        JPATH_ADMINISTRATOR . '/language/en-GB/en-GB.plg_content_chaosfilet.sys.ini',
                        JPATH_SITE . '/language/en-GB/en-GB.plg_content_chaosfilet.ini',
                        JPATH_SITE . '/language/en-GB/en-GB.plg_content_chaosfilet.sys.ini',
                        // READMEs now live in the package source only.
                        JPATH_PLUGINS . '/content/chaosfilet/README-chaosfilet.md',
                        JPATH_PLUGINS . '/content/chaosfilet/README-chaosfilet.html',
                        JPATH_PLUGINS . '/content/chaosfilet/README.md',
                        JPATH_PLUGINS . '/editors-xtd/chaosfilet/README.md',
                        // 1.4.0: the editor popup's CSS is injected by its script; the stylesheet is gone.
                        JPATH_ROOT . '/media/plg_editors_xtd_chaosfilet/css/insert-tag.css',
                        JPATH_ROOT . '/media/plg_editors_xtd_chaosfilet/css/insert-tag.min.css',
                    ];

                    foreach ($files as $file) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }

                    $cssDir = JPATH_ROOT . '/media/plg_editors_xtd_chaosfilet/css';

                    if (is_dir($cssDir) && !(new \FilesystemIterator($cssDir))->valid()) {
                        @rmdir($cssDir);
                    }
                }

                private function query()
                {
                    return method_exists($this->db, 'createQuery') ? $this->db->createQuery() : $this->db->getQuery(true);
                }
            }
        );
    }
};
