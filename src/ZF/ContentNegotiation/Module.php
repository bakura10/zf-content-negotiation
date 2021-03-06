<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2013 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\ContentNegotiation;

use Zend\Mvc\Controller\Plugin\AcceptableViewModelSelector;
use Zend\Mvc\MvcEvent;

class Module
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__,
                ),
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/../../../config/module.config.php';
    }

    public function getServiceConfig()
    {
        return array('factories' => array(
            'ZF\ContentNegotiation\AcceptListener' => function ($services) {
                $config = array();
                if ($services->has('Config')) {
                    $appConfig = $services->get('Config');
                    if (isset($appConfig['zf-content-negotiation'])
                        && is_array($appConfig['zf-content-negotiation'])
                    ) {
                        $config = $appConfig['zf-content-negotiation'];
                    }
                }

                $selector = null;
                if ($services->has('ControllerPluginManager')) {
                    $plugins = $services->get('ControllerPluginManager');
                    if ($plugins->has('AcceptableViewModelSelector')) {
                        $selector = $plugins->get('AcceptableViewModelSelector');
                    }
                }
                if (null === $selector) {
                    $selector = new AcceptableViewModelSelector();
                }
                return new AcceptListener($selector, $config);
            },
            'ZF\ContentNegotiation\AcceptFilterListener' => function ($services) {
                $listener = new AcceptFilterListener();

                $config   = array();
                if ($services->has('Config')) {
                    $moduleConfig = false;
                    $appConfig    = $services->get('Config');
                    if (isset($appConfig['zf-content-negotiation'])
                        && is_array($appConfig['zf-content-negotiation'])
                    ) {
                        $moduleConfig = $appConfig['zf-content-negotiation'];
                    }

                    if ($moduleConfig
                        && isset($moduleConfig['accept-whitelist'])
                        && is_array($moduleConfig['accept-whitelist'])
                    ) {
                        $config = $moduleConfig['accept-whitelist'];
                    }
                }

                if (!empty($config)) {
                    $listener->setConfig($config);
                }

                return $listener;
            },
            'ZF\ContentNegotiation\ContentTypeFilterListener' => function ($services) {
                $listener = new ContentTypeFilterListener();

                $config   = array();
                if ($services->has('Config')) {
                    $moduleConfig = false;
                    $appConfig    = $services->get('Config');
                    if (isset($appConfig['zf-content-negotiation'])
                        && is_array($appConfig['zf-content-negotiation'])
                    ) {
                        $moduleConfig = $appConfig['zf-content-negotiation'];
                    }

                    if ($moduleConfig
                        && isset($moduleConfig['content-type-whitelist'])
                        && is_array($moduleConfig['content-type-whitelist'])
                    ) {
                        $config = $moduleConfig['content-type-whitelist'];
                    }
                }

                if (!empty($config)) {
                    $listener->setConfig($config);
                }

                return $listener;
            },
        ));
    }

    public function onBootstrap($e)
    {
        $app      = $e->getApplication();
        $services = $app->getServiceManager();
        $em       = $app->getEventManager();

        $em->attach(MvcEvent::EVENT_ROUTE, new ContentTypeListener(), -99);

        $sem = $em->getSharedManager();
        $sem->attach(
            'Zend\Stdlib\DispatchableInterface',
            MvcEvent::EVENT_DISPATCH,
            $services->get('ZF\ContentNegotiation\AcceptListener'),
            -10
        );
        $sem->attachAggregate($services->get('ZF\ContentNegotiation\AcceptFilterListener'));
        $sem->attachAggregate($services->get('ZF\ContentNegotiation\ContentTypeFilterListener'));
    }
}
