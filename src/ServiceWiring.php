<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\GlobalMessages\GlobalPermissionsRegistry;
use MediaWiki\MediaWikiServices;

return [
    GlobalPermissionsRegistry::SERVICE_NAME => static function (
        MediaWikiServices $services
    ): GlobalPermissionsRegistry {
        return new GlobalPermissionsRegistry(
            new ServiceOptions(
                GlobalPermissionsRegistry::CONSTRUCTOR_OPTIONS,
                $services->getMainConfig()
            ),
            $services->getDBLoadBalancerFactory(),
            $services->getMainWANObjectCache()
        );
    },
];
