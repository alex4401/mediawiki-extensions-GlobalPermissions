<?php
namespace MediaWiki\Extension\GlobalMessages;

use WANObjectCache;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use ObjectCache;
use User;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\LoadBalancer;

class GlobalPermissionsRegistry {
    public const SERVICE_NAME = 'GlobalPermissions.Registry';

    /**
     * @internal Use only in ServiceWiring
     */
    public const CONSTRUCTOR_OPTIONS = [
        'GlobalPermissionsDatabases',
    ];

    private const SHARED_CACHE_TTL = 60 * 2;

    /** @var ServiceOptions */
    private ServiceOptions $options;
    /** @var LBFactory */
    private LBFactory $dbLoadBalancerFactory;
    /** @var WANObjectCache */
    private WANObjectCache $wanObjectCache;

    public function __construct(
        ServiceOptions $options,
        LBFactory $dbLoadBalancerFactory,
        WANObjectCache $wanObjectCache
    ) {
        $options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
        $this->options = $options;
        $this->dbLoadBalancerFactory = $dbLoadBalancerFactory;
        $this->wanObjectCache = $wanObjectCache;
    }

    private function getDatabaseConnectionRef( string $name, int $index ): IDatabase {
        return $this->dbLoadBalancerFactory->getMainLB( $name )->getConnection( $index, [], $name );
    }

    private function makeCacheKey( int $userId ): string {
        return $this->wanObjectCache->makeGlobalKey( 'globalpermissions', $userId );
    }

    public function getGroupsForUserId( int $userId ): array {
        return $this->wanObjectCache->getWithSetCallback(
            $this->makeCacheKey( $userId ),
            self::SHARED_CACHE_TTL,
            function ( $old, &$ttl, &$setOpts ) use ( $userId ) {
                $results = [];
                foreach ( $this->options->get( 'GlobalPermissionsDatabases' ) as $share ) {
                    $db = $this->getDatabaseConnectionRef( $share['db'], DB_PRIMARY );
                    $results += $db->newSelectQueryBuilder()
                        ->select( 'ug_group' )
                        ->from( 'user_groups' )
                        ->where( [
                            'ug_user' => $userId,
                            'ug_group' => $share['allow'],
                            'ug_expiry IS NULL OR ug_expiry >= ' . $db->addQuotes( $db->timestamp() ),
                        ] )
                        ->fetchFieldValues();
                }
                sort( $results );
                return array_unique( $results );
            },
            [
                // Avoid database stampede
                'lockTSE' => 300,
            ]
        );
    }

    public function getGroupsForUser( User $user ): array {
        return $this->getGroupsForUserId( $user->getId() );
    }
}