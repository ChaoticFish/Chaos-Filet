<?php

/**
 * @package     Chaos Filet
 * @subpackage  plg_editors-xtd_chaosfilet
 * @version     1.3.0
 * @copyright   (C) 2026 Chaotic Fish Design
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use ChaoticFishDesign\Plugin\EditorsXtd\ChaosFilet\Extension\ChaosFilet;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $config = (array) PluginHelper::getPlugin('editors-xtd', 'chaosfilet');

                $plugin = new ChaosFilet($config);
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));

                return $plugin;
            }
        );
    }
};
