<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit24937435361e49603d72ea0b2bbdccd7
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInit24937435361e49603d72ea0b2bbdccd7', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInit24937435361e49603d72ea0b2bbdccd7', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        \Composer\Autoload\ComposerStaticInit24937435361e49603d72ea0b2bbdccd7::getInitializer($loader)();

        $loader->register(true);

        return $loader;
    }
}
