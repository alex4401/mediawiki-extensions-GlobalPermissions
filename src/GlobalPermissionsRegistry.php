<?php
namespace MediaWiki\Extension\GlobalPermissions;

use WANObjectCache;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;

class GlobalPermissionsRegistry {
    public const SERVICE_NAME = 'GlobalPermissions.Registry';

    /**
     * @internal Use only in ServiceWiring
     */
    public const CONSTRUCTOR_OPTIONS = [
        MainConfigNames::DBname,
        'GlobalPermissionsDatabases',
        'GlobalPermissionsPinnedUsers',
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

    private function makeCacheKey( string $shareName, int $userId ): string {
        return $this->wanObjectCache->makeGlobalKey( 'globalpermissions', 1, $shareName, $userId );
    }

    public function getGroupsForUserId( int $userId ): array {
        $results = [];

        // Get pinned permissions
        foreach ( $this->options->get( 'GlobalPermissionsPinnedUsers' ) as $group => $userIds ) {
            if ( in_array( $userId, $userIds ) ) {
                $results[] = $group;
            }
        }
        
        // Load permissions from databases
        foreach ( $this->options->get( 'GlobalPermissionsDatabases' ) as $shareDb => $shareGroups ) {
            $results = array_merge( $results, $this->wanObjectCache->getWithSetCallback(
                $this->makeCacheKey( $shareDb, $userId ),
                self::SHARED_CACHE_TTL,
                function ( $old, &$ttl, &$setOpts ) use ( $shareDb, &$shareGroups, $userId ) {
                    $dbw = $this->getDatabaseConnectionRef( $shareDb, DB_PRIMARY );
                    return $dbw->newSelectQueryBuilder()
                        ->select( 'ug_group' )
                        ->from( 'user_groups' )
                        ->where( [
                            'ug_user' => $userId,
                            'ug_group' => $shareGroups,
                            'ug_expiry IS NULL OR ug_expiry >= ' . $dbw->addQuotes( $dbw->timestamp() ),
                        ] )
                        ->fetchFieldValues();
                },
                [
                    // Avoid database stampede
                    'lockTSE' => 300,
                ]
            ) );
        }

        sort( $results );
        return array_unique( $results );
    }

    public function getGroupsForUser( User $user ): array {
        return $this->getGroupsForUserId( $user->getId() );
    }

    public function purgeLocalCacheForUserId( int $userId ): void {
        $this->wanObjectCache->delete( $this->makeCacheKey( $this->options->get( MainConfigNames::DBname ),
            $userId ) );
    }

    public function purgeLocalCacheForUser( User $user ): void {
        $this->purgeLocalCacheForUserId( $user->getId() );
    }
}
