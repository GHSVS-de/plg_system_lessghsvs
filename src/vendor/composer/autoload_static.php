<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit0aa1a67bdd913c23f3aa4e416b857272
{
    public static $prefixLengthsPsr4 = array (
        'M' => 
        array (
            'MatthiasMullie\\PathConverter\\' => 29,
            'MatthiasMullie\\Minify\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'MatthiasMullie\\PathConverter\\' => 
        array (
            0 => __DIR__ . '/..' . '/matthiasmullie/path-converter/src',
        ),
        'MatthiasMullie\\Minify\\' => 
        array (
            0 => __DIR__ . '/..' . '/matthiasmullie/minify/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit0aa1a67bdd913c23f3aa4e416b857272::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit0aa1a67bdd913c23f3aa4e416b857272::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit0aa1a67bdd913c23f3aa4e416b857272::$classMap;

        }, null, ClassLoader::class);
    }
}
