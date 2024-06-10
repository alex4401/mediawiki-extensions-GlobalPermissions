<?php
namespace MediaWiki\Extension\GlobalPermissions;

use MediaWiki\Extension\GlobalMessages\GlobalPermissionsRegistry;
use User;

final class CacheInvalidationHooks implements
    \MediaWiki\User\Hook\UserGroupsChangedHook
{
	private GlobalPermissionsRegistry $registry;

	public function __construct(
		GlobalPermissionsRegistry $registry
	) {
		$this->registry = $registry;
	}

    /**
	 * @param User $user User whose groups changed
	 * @param string[] $added Groups added
	 * @param string[] $removed Groups removed
	 * @param User|false $performer User who performed the change, false if via autopromotion
	 * @param string|false $reason The reason, if any, given by the user performing the change,
	 *   false if via autopromotion.
	 * @param UserGroupMembership[] $oldUGMs An associative array (group name => UserGroupMembership)
	 *   of the user's group memberships before the change.
	 * @param UserGroupMembership[] $newUGMs An associative array (group name => UserGroupMembership)
	 *   of the user's current group memberships.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onUserGroupsChanged(
		$user,
		$added,
		$removed,
		$performer,
		$reason,
		$oldUGMs,
		$newUGMs
	) {
        $this->registry->purgeLocalCacheForUser( $user );
    }
}
