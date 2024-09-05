<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb47ca68fa6c4c184172d80824811a5c9
{
    public static $prefixLengthsPsr4 = array (
        'c' => 
        array (
            'cdigruttola\\Module\\PackageWeight\\' => 33,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'cdigruttola\\Module\\PackageWeight\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'PackageRangeWeight' => __DIR__ . '/../..' . '/classes/PackageRangeWeight.php',
        'Packageweight' => __DIR__ . '/../..' . '/packageweight.php',
        'cdigruttola\\Module\\PackageWeight\\Adapter\\Kpi\\PackageWeightCartTotalKpi' => __DIR__ . '/../..' . '/src/Adapter/Kpi/PackageWeightCartTotalKpi.php',
        'cdigruttola\\Module\\PackageWeight\\Adapter\\Kpi\\WeightCartTotalKpi' => __DIR__ . '/../..' . '/src/Adapter/Kpi/WeightCartTotalKpi.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb47ca68fa6c4c184172d80824811a5c9::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb47ca68fa6c4c184172d80824811a5c9::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitb47ca68fa6c4c184172d80824811a5c9::$classMap;

        }, null, ClassLoader::class);
    }
}
