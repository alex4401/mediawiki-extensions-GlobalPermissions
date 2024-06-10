<?php
namespace MediaWiki\Extension\GlobalPermissions;

use MediaWiki\Extension\GlobalMessages\GlobalPermissionsRegistry;
use User;

final class PermissionsHooks implements
    \MediaWiki\User\Hook\UserEffectiveGroupsHook,
	\MediaWiki\User\Hook\UserIsBotHook
{
	private GlobalPermissionsRegistry $registry;

	public function __construct(
		GlobalPermissionsRegistry $registry
	) {
		$this->registry = $registry;
	}

	/**
	 * @param User $user User to get groups for
	 * @param string[] &$groups Current effective groups
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onUserEffectiveGroups( $user, &$groups ) {
		if ( $user->isRegistered() ) {
			$groups += $this->registry->getGroupsForUser( $user );
			$groups = array_unique( $groups );
		}
	}

	/**
	 * @param User $user
	 * @param bool &$isBot Whether this is user a bot or not (boolean)
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onUserIsBot( $user, &$isBot ) {
		if ( $user->isRegistered() ) {
			foreach ( $this->registry->getGroupsForUser( $user ) as $group ) {
				if ( str_contains( $group, '-bot' ) ) {
					$isBot = true;
					break;
				}
			}
		}
	}
}
