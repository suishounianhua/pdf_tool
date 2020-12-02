<?php

static $_globalLoader;

if (!$_globalLoader){
    $_globalLoader = new \Phalcon\Loader();

    $_globalLoader->registerNamespaces(
        array(
            'plugins' => '../plugins/',
            'plugins\test\service' => '../plugins/test/service/',
            'plugins\test\models' => '../plugins/test/models/',
            // 按需设置多个
        )
    );

    /**
     * We're a registering a set of directories taken from the configuration file
     */

    $_globalLoader->registerDirs(
        array(
            $config->application->controllersDir,
            $config->application->modelsDir,
            $config->application->servicesDir,
            $config->application->validatorsDir,
            $config->application->utilitiesDir,
            $config->application->pluginsDir,
            $config->application->IOFactoryDir,
            $config->application->behaviorDir,
            $config->application->libraryDir,
            $config->application->taskDir,
        )
    )->register();
}


return $_globalLoader;
