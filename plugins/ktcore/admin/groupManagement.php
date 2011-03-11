<?php
/**
 * $Id$
 *
 * KnowledgeTree Community Edition
 * Document Management Made Simple
 * Copyright (C) 2008, 2009, 2010 KnowledgeTree Inc.
 *
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 3 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * You can contact KnowledgeTree Inc., PO Box 7775 #87847, San Francisco,
 * California 94120-7775, or email info@knowledgetree.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * KnowledgeTree" logo and retain the original copyright notice. If the display of the
 * logo is not reasonably feasible for technical reasons, the Appropriate Legal Notices
 * must display the words "Powered by KnowledgeTree" and retain the original
 * copyright notice.
 * Contributor( s): ______________________________________
 *
 */

require_once(KT_LIB_DIR . '/users/User.inc');
require_once(KT_LIB_DIR . '/groups/GroupUtil.php');
require_once(KT_LIB_DIR . '/groups/Group.inc');
require_once(KT_LIB_DIR . '/unitmanagement/Unit.inc');

require_once(KT_LIB_DIR . '/templating/templating.inc.php');
require_once(KT_LIB_DIR . '/dispatcher.inc.php');
require_once(KT_LIB_DIR . '/templating/kt3template.inc.php');
require_once(KT_LIB_DIR . '/widgets/fieldWidgets.php');
require_once(KT_LIB_DIR . '/widgets/forms.inc.php');

require_once(KT_LIB_DIR . '/authentication/authenticationsource.inc.php');
require_once(KT_LIB_DIR . '/authentication/authenticationproviderregistry.inc.php');
require_once(KT_LIB_DIR . '/authentication/builtinauthenticationprovider.inc.php');

class KTGroupAdminDispatcher extends KTAdminDispatcher {

    public $sHelpPage = 'ktcore/admin/manage groups.html';
	public $aCannotView = array('starter', 'professional');

    function predispatch()
    {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('Group Management'));
        $this->persistParams(array('old_search'));
    }

    function do_main()
    {
        $this->oPage->setBreadcrumbDetails(_kt('select a group'));
        $this->oPage->setTitle(_kt('Group Management'));

        $KTConfig =& KTConfig::getSingleton();
        $alwaysAll = 1; //$KTConfig->get('alwaysShowAll');

        $name = KTUtil::arrayGet($_REQUEST, 'search_name', KTUtil::arrayGet($_REQUEST, 'old_search'));
        $show_all = KTUtil::arrayGet($_REQUEST, 'show_all', $alwaysAll);
        $group_id = KTUtil::arrayGet($_REQUEST, 'group_id');

        $no_search = true;

        if (KTUtil::arrayGet($_REQUEST, 'do_search', false) != false) {
            $no_search = false;
        }

        if ($name == '*') {
            $show_all = true;
            $name = '';
        }

        $search_fields = array();
        $search_fields[] =  new KTStringWidget(_kt(''), _kt("Enter part of the group's name: <strong>ad</strong> will match <strong>administrators</strong>."), 'search_name', $name, $this->oPage, false);

        if (!empty($name)) {
            $search_results =& Group::getList('WHERE name LIKE \'%' . DBUtil::escapeSimple($name) . '%\' AND id > 0');
        }
        else if ($show_all !== false) {
            $search_results =& Group::getList('id > 0');
            $no_search = false;
            $name = '*';
        }

        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/principals/groupadmin');
        $aTemplateData = array(
            'context' => $this,
            'search_fields' => $search_fields,
            'search_results' => $search_results,
            'no_search' => $no_search,
            'old_search' => $name,
        );

        return $oTemplate->render($aTemplateData);
    }

    function do_editGroup()
    {
        $old_search = KTUtil::arrayGet($_REQUEST, 'old_search');


        $this->oPage->setBreadcrumbDetails(_kt('edit group'));

        $group_id = KTUtil::arrayGet($_REQUEST, 'group_id');
        $oGroup = Group::get($group_id);
        if (PEAR::isError($oGroup) || $oGroup == false) {
            $this->errorRedirectToMain(_kt('Please select a valid group.'), sprintf('old_search=%s&do_search=1', $old_search));
        }

        $this->oPage->setTitle(sprintf(_kt('Edit Group (%s)'), $oGroup->getName()));

        $edit_fields = array();
        $edit_fields[] =  new KTStringWidget(_kt('Group Name'), _kt('A short name for the group.  e.g. <strong>administrators</strong>.'), 'group_name', $oGroup->getName(), $this->oPage, true);
        $edit_fields[] =  new KTCheckboxWidget(_kt('Unit Administrators'), _kt('Should all the members of this group be given <strong>unit</strong> administration privileges?'), 'is_unitadmin', $oGroup->getUnitAdmin(), $this->oPage, false);
        $edit_fields[] =  new KTCheckboxWidget(_kt('System Administrators'), _kt('Should all the members of this group be given <strong>system</strong> administration privileges?'), 'is_sysadmin', $oGroup->getSysAdmin(), $this->oPage, false);

        // grab all units.
        $unitId = $oGroup->getUnitId();
        if ($unitId == null) { $unitId = 0; }

        $oUnits = Unit::getList();
        $vocab = array();
        $vocab[0] = _kt('No Unit');
        foreach ($oUnits as $oUnit) { $vocab[$oUnit->getID()] = $oUnit->getName(); }
        $aOptions = array('vocab' => $vocab);

        $edit_fields[] =  new KTLookupWidget(_kt('Unit'), _kt('Which Unit is this group part of?'), 'unit_id', $unitId, $this->oPage, false, null, null, $aOptions);

        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/principals/editgroup');
        $aTemplateData = array(
            'context' => $this,
            'edit_fields' => $edit_fields,
            'edit_group' => $oGroup,
            'old_search' => $old_search,
        );

        return $oTemplate->render($aTemplateData);
    }

    function do_saveGroup()
    {
        $old_search = KTUtil::arrayGet($_REQUEST, 'old_search');

        $group = $this->getGroupFromRequest('Please select a valid group.');

        $group_name = KTUtil::arrayGet($_REQUEST, 'group_name');
        if (empty($group_name)) { $this->errorRedirectToMain(_kt('Please specify a name for the group.')); }

        $is_unitadmin = KTUtil::arrayGet($_REQUEST, 'is_unitadmin', false);
        if ($is_unitadmin !== false) { $is_unitadmin = true; }

        $is_sysadmin = KTUtil::arrayGet($_REQUEST, 'is_sysadmin', false);
        if ($is_sysadmin !== false) { $is_sysadmin = true; }

        $this->startTransaction();

        $group->setName($group_name);
        $group->setUnitAdmin($is_unitadmin);
        $group->setSysAdmin($is_sysadmin);

        $unit_id = KTUtil::arrayGet($_REQUEST, 'unit_id', 0);
        if ($unit_id == 0) { // not set, or set to 0.
            $group->setUnitId(null); // safe.
        }
        else {
            $group->setUnitId($unit_id);
        }

        $res = $group->update();
        if (($res == false) || (PEAR::isError($res))) { return $this->errorRedirectToMain(_kt('Failed to set group details.'), sprintf('old_search=%s&do_search=1', $old_search)); }

        if (!Permission::userIsSystemAdministrator($_SESSION['userID'])) {
            $this->rollbackTransaction();
            $this->errorRedirectTo('editGroup', _kt('For security purposes, you cannot remove your own administration priviledges.'), sprintf('group_id=%d', $group->getId()), sprintf('old_search=%s&do_search=1', $old_search));
            exit(0);
        }

        $this->commitTransaction();
        if ($unit_id == 0 && $is_unitadmin) {
            $this->successRedirectToMain(_kt('Group details updated.') . _kt(' Note: group is set as unit administrator, but is not assigned to a unit.'), sprintf('old_search=%s&do_search=1', $old_search));
        }
        else {
            $this->successRedirectToMain(_kt('Group details updated.'), sprintf('old_search=%s&do_search=1', $old_search));
        }
    }

    function _do_manageUsers_source()
    {
        $old_search = KTUtil::arrayGet($_REQUEST, 'old_search');
        $oGroup =& $this->oValidator->validateGroup($_REQUEST['group_id']);
        $aGroupUsers = $oGroup->getMembers();

        $oTemplate = $this->oValidator->validateTemplate('ktcore/principals/groups_sourceusers');
        $aTemplateData = array(
            'context' => $this,
            'group_users' => $aGroupUsers,
            'group' => $oGroup,
            'old_search' => $old_search,
        );

        return $oTemplate->render($aTemplateData);
    }

    function do_synchroniseGroup()
    {
        $old_search = KTUtil::arrayGet($_REQUEST, 'old_search');

        require_once(KT_LIB_DIR . '/authentication/authenticationutil.inc.php');
        $oGroup =& $this->oValidator->validateGroup($_REQUEST['group_id']);
        $res = KTAuthenticationUtil::synchroniseGroupToSource($oGroup);

        // Invalidate the permissions cache to force an update .
        KTPermissionUtil::clearCache();

        $this->successRedirectTo('manageusers', 'Group synchronised', sprintf('group_id=%d', $oGroup->getId()), sprintf('old_search=%s&do_search=1', $old_search));
        exit(0);
    }

    function do_manageUsers()
    {
        $old_search = KTUtil::arrayGet($_REQUEST, 'old_search');

        $group = $this->getGroupFromRequest();

        $this->aBreadcrumbs[] = array('name' => $group->getName());
        $this->oPage->setBreadcrumbDetails(_kt('manage members'));
        $this->oPage->setTitle(sprintf(_kt('Manage members of group %s'), $group->getName()));

        $iSourceId = $group->getAuthenticationSourceId();
        if (!empty($iSourceId)) {
            return $this->_do_manageUsers_source();
        }

        $initialUsers = $group->getMembers();
        /*$allUsers = User::getList('id > 0');*/

        /*// FIXME this is massively non-performant for large userbases..
        $groupUsers = array();
        $freeUsers = array();
        foreach ($initialUsers as $user) {
            $groupUsers[$user->getId()] = $user;
        }

        foreach ($allUsers as $user) {
            if (!array_key_exists($user->getId(), $groupUsers)) {
                $freeUsers[$user->getId()] = $user;
            }
        }*/

        $assigned['users'] = array();
        foreach ($initialUsers as $member) {
            $name = $member->getName();
            if (empty($name)) { $name = $member->getUserName(); }
            $assigned['users'][] = "{id: '{$member->getId()}', name: '$name'}";
        }

        $jsonWidget = new KTJSONLookupWidget(_kt('Users'),
            _kt('Select the users which should be part of this group. Once you have added all the users that you require, press <strong>save changes</strong>.'),
            'members', '',
            $this->oPage,
            false,
            null,
            null,
            array(
                'action' => 'getUsers',
                'groups_roles' => $groupUsers,
                'assigned' => array('', implode(',', $assigned['users'])),
                'type' => 'users',
                'parts' => 'users'
            )
        );

        $templating =& KTTemplating::getSingleton();
        $template = $templating->loadTemplate('ktcore/principals/groups_manageusers');
        $templateData = array(
            'context' => $this,
            'edit_group' => $group,
            'widget' => $jsonWidget,
            'old_search' => $old_search,
        );

        return $template->render($templateData);
    }

    function json_getUsers()
    {
        $sFilter = KTUtil::arrayGet($_REQUEST, 'filter', false);
        $aUserList = array('off' => _kt('-- Please filter --'));

        if ($sFilter && trim($sFilter)) {
            $aUsers = User::getList(sprintf('name like "%%%s%%" AND (disabled = 0 OR disabled = 3) AND id > 0', $sFilter));
            $aUserList = array();
            foreach($aUsers as $oUser) {
                if ($oUser->getDisabled() == 3) $oUser->setName('(Invited) ' . $oUser->getEmail());
                $aUserList[$oUser->getId()] = $oUser->getName();
            }
        }

        return $aUserList;
    }

    private function getGroupFromRequest($message = 'No such group.')
    {
        $group_id = KTUtil::arrayGet($_REQUEST, 'group_id');
        $group = Group::get($group_id);
        if ((PEAR::isError($oGroup)) || ($oGroup === false)) {
            $this->errorRedirectToMain(_kt($message), sprintf('old_search=%s&do_search=1', $old_search));
        }

        return $group;
    }

    function do_updateUserMembers()
    {
        $old_search = KTUtil::arrayGet($_REQUEST, 'old_search');

        $group = $this->getGroupFromRequest();

        /*$userAdded = KTUtil::arrayGet($_REQUEST, 'users_items_added','');
        $userRemoved = KTUtil::arrayGet($_REQUEST, 'users_items_removed','');

        $aUserToAddIDs = explode(',', $userAdded);
        $aUserToRemoveIDs = explode(',', $userRemoved);*/

        $this->startTransaction();

        // Detect existing group members (and diff with current, to see which were removed.)
        $currentUsers = $group->getMembers();
        // Probably should add a function for just getting this info, but shortcut for now.
        foreach ($currentUsers as $key => $user) {
            $name = $user->getName();
            $currentUsers[$key] = !empty($name) ? $name : $user->getUsername();
        }

        // Remove any current groups for this user.
        if (!empty($currentUsers) && !GroupUtil::removeUsersForGroup($group)) {
            $this->errorRedirectToMain(sprintf(_kt('Unable to remove existing group memberships')), sprintf('old_search=%s&do_search=1', $old_search));
        }

        // Insert submitted users for this group.

        $usersAdded = array();
        $addWarnings = array();
        // TODO I am sure we can do this much better, create a single insert query instead of one per added group.
        $users = trim(KTUtil::arrayGet($_REQUEST, 'users'), ',');
        if (!empty($users)) {
            $users = explode(',', $users);
            foreach ($users as $userId) {
                $user = User::get($userId);
                // Not sure this has any validity in the new method.
                $memberReason = GroupUtil::getMembershipReason($user, $group);
                if (!(PEAR::isError($memberReason) || is_null($memberReason))) {
                    $addWarnings[] = $memberReason;
                }

                $res = $group->addMember($user);
                if (PEAR::isError($res) || $res == false) {
                    $this->rollbackTransaction();
                    $this->errorRedirectToMain(sprintf(_kt('Unable to add user "%s" to group "%s"'), $user->getName(), $group->getName()), sprintf('old_search=%s&do_search=1', $old_search));
                }
                else {
                    $usersAdded[] = $user->getName();
                }
            }
        }

        $usersRemoved = array_diff($currentUsers, $usersAdded);
        $usersAdded = array_diff($usersAdded, $currentUsers);

        // ==-=-=-=-=-=-=-== //

        if (!empty($addWarnings)) {
            $sWarnStr = _kt('Warning:  some users were already members of some subgroups') . ' &mdash; ';
            $sWarnStr .= implode(', ', $addWarnings);
            $_SESSION['KTInfoMessage'][] = $sWarnStr;
        }

        /*// No longer valid for new method
        if (!empty($removeWarnings)) {
            $sWarnStr = _kt('Warning:  some users are still members of some subgroups') . ' &mdash; ';
            $sWarnStr .= implode(', ', $removeWarnings);
            $_SESSION['KTInfoMessage'][] = $sWarnStr;
        }*/

        /*$usersAdded = array();
        $usersRemoved = array();
        $addWarnings = array();
        $removeWarnings = array();

        foreach ($aUserToAddIDs as $iUserId ) {
            if ($iUserId > 0) {
                $oUser= User::Get($iUserId);
                $memberReason = GroupUtil::getMembershipReason($oUser, $group);
                if (!(PEAR::isError($memberReason) || is_null($memberReason))) {
                    $addWarnings[] = $memberReason;
                }

                $res = $group->addMember($oUser);
                if (PEAR::isError($res) || $res == false) {
                    $this->rollbackTransaction();
                    $this->errorRedirectToMain(sprintf(_kt('Unable to add user "%s" to group "%s"'), $oUser->getName(), $group->getName()), sprintf('old_search=%s&do_search=1', $old_search));
                    exit();
                }
                else {
                    $usersAdded[] = $oUser->getName();
                }
            }
        }

        // Remove groups
        foreach ($aUserToRemoveIDs as $iUserId ) {
            if ($iUserId > 0) {
                $oUser = User::get($iUserId);
                $res = $group->removeMember($oUser);
                if (PEAR::isError($res) || $res == false) {
                    $this->rollbackTransaction();
                    $this->errorRedirectToMain(sprintf(_kt('Unable to remove user "%s" from group "%s"'), $oUser->getName(), $group->getName()), sprintf('old_search=%s&do_search=1', $old_search));
                    exit();
                }
                else {
                    $usersRemoved[] = $oUser->getName();
                    $memberReason = GroupUtil::getMembershipReason($oUser, $group);
                    if (!(PEAR::isError($memberReason) || is_null($memberReason))) {
                        $removeWarnings[] = $memberReason;
                    }
                }
            }
        }

        if (!empty($addWarnings)) {
            $sWarnStr = _kt('Warning:  some users were already members of some subgroups') . ' &mdash; ';
            $sWarnStr .= implode(', ', $addWarnings);
            $_SESSION['KTInfoMessage'][] = $sWarnStr;
        }

        if (!empty($removeWarnings)) {
            $sWarnStr = _kt('Warning:  some users are still members of some subgroups') . ' &mdash; ';
            $sWarnStr .= implode(', ', $removeWarnings);
            $_SESSION['KTInfoMessage'][] = $sWarnStr;
        }*/

        $msg = '';
        if (!empty($usersAdded)) { $msg .= ' ' . _kt('Added') . ': ' . implode(', ', $usersAdded) . '. '; }
        if (!empty($usersRemoved)) { $msg .= ' ' . _kt('Removed') . ': ' . implode(', ',$usersRemoved) . '.'; }

        if (!Permission::userIsSystemAdministrator($_SESSION['userID'])) {
            $this->rollbackTransaction();
            $this->errorRedirectTo('manageUsers', _kt('For security purposes, you cannot remove your own administration priviledges.'), sprintf('group_id=%d', $group->getId()), sprintf('old_search=%s&do_search=1', $old_search));
            exit(0);
        }

        $this->commitTransaction();

        // Invalidate the permissions cache to force an update .
        // It is possible to update only the new / removed members of the group
        // but if there are a large number of members involved this can become an expensive operation.
        // It is cheaper to invalidate the cache and force a validation of each users permissions.
        KTPermissionUtil::clearCache();

        $this->successRedirectToMain($msg, sprintf('old_search=%s&do_search=1', $old_search));
    }

    // FIXME copy-paste ...
    function do_manageSubgroups()
    {
        $old_search = KTUtil::arrayGet($_REQUEST, 'old_search');

        $group = $this->getGroupFromRequest();

        $this->aBreadcrumbs[] = array('name' => $group->getName());
        $this->oPage->setBreadcrumbDetails(_kt('manage members'));
        $this->oPage->setTitle(sprintf(_kt('Manage members of %s'), $group->getName()));

        $groups = array('null' => 'Select group');
        $groupList = GroupUtil::listGroups();
        foreach ($groupList as $subGroup) {
            if ($group->getId() == $subGroup->getId()) { continue; }
            $groups["group_{$subGroup->getId()}"] = $subGroup->getName();
        }

        $memberGroups = $group->getMemberGroups();

        $assigned['groups_roles'] = array();
        foreach ($memberGroups as $member) {
            $assigned['groups_roles'][] = "{id: 'group_{$member->getId()}', name: '{$member->getName()}'}";
        }

        $jsonWidget = new KTJSONLookupWidget(_kt('Groups'),
            _kt('Select the users which should be part of this group. Once you have added all the users that you require, press <strong>save changes</strong>.'),
            'members', '',
            $this->oPage,
            false,
            null,
            null,
            array(
                'action' => 'getUsers',
                'groups_roles' => $groups,
                'assigned' => array(implode(',', $assigned['groups_roles'])),
                'type' => 'groups',
                'parts' => 'groups'
            )
        );

        $templating =& KTTemplating::getSingleton();
        $template = $templating->loadTemplate('ktcore/principals/groups_managesubgroups');
        $templateData = array(
            'context' => $this,
            'edit_group' => $group,
            'widget' => $jsonWidget,
            'old_search' => $old_search,
        );

        return $template->render($templateData);
    }

    function json_getSubGroups()
    {
        $sFilter = KTUtil::arrayGet($_REQUEST, 'filter', false);
        $aAllowedGroups = array('off' => _kt('-- Please filter --'));

        if ($sFilter && trim($sFilter)) {
            $iGroupID = KTUtil::arrayGet($_REQUEST, 'group_id', false);
            if (!$iGroupID) {
                return array('error'=>true, 'type'=>'kt.invalid_entity', 'message'=>_kt('An invalid group was selected'));
            }

            $oGroup = Group::get($iGroupID);
            $aMemberGroupsUnkeyed = $oGroup->getMemberGroups();
            $aMemberGroups = array();
            $aMemberIDs = array();

            foreach ($aMemberGroupsUnkeyed as $oMemberGroup) {
                $aMemberIDs[] = $oMemberGroup->getID();
                $aMemberGroups[$oMemberGroup->getID()] = $oMemberGroup;
            }

            $aGroupArray = GroupUtil::buildGroupArray();
            $aAllowedGroupIDs = GroupUtil::filterCyclicalGroups($oGroup->getID(), $aGroupArray);
            $aAllowedGroupIDs = array_diff($aAllowedGroupIDs, $aMemberIDs);
            $aAllowedGroups = array();

            foreach ($aAllowedGroupIDs as $iAllowedGroupID) {
                $group = Group::get($iAllowedGroupID);
                if (!PEAR::isError($group) && ($group != false)) {
                    $aAllowedGroups[$iAllowedGroupID] = $group->getName();
                }
            }
        }

        return $aAllowedGroups;
    }

    function _getUnitName($oGroup)
    {
        $iUnitId = $oGroup->getUnitId();
        if (empty($iUnitId)) {
            return null;
        }

        $unit = Unit::get($iUnitId);
        if (PEAR::isError($unit)) {
            return null;   // XXX: prevent failure if the $unit is a PEAR::error
        }

        return $unit->getName();
    }

    // FIXME copy-paste ...
    function do_updateGroupMembers()
    {
        $old_search = KTUtil::arrayGet($_REQUEST, 'old_search');

        $group = $this->getGroupFromRequest();

        /*$groupAdded = KTUtil::arrayGet($_REQUEST, 'groups_items_added','');
        $groupRemoved = KTUtil::arrayGet($_REQUEST, 'groups_items_removed','');

        $aGroupToAddIDs = explode(',', $groupAdded);
        $aGroupToRemoveIDs = explode(',', $groupRemoved);*/

        $this->startTransaction();

        // Detect existing sub-groups (and diff with current, to see which were removed.)
        $currentGroups = $group->getMemberGroups();
        // Probably should add a function for just getting this info, but shortcut for now.
        foreach ($currentGroups as $key => $subGroup) {
            $currentGroups[$key] = $subGroup->getName();
        }

        // Remove any current sub-groups for this group.
        if (!empty($currentGroups) && !GroupUtil::removeSubGroupsForGroup($group)) {
            $this->errorRedirectToMain(sprintf(_kt('Unable to remove existing sub-groups')), sprintf('old_search=%s&do_search=1', $old_search));
        }

        // Insert submitted groups for this user.

        $groupsAdded = array();
        // TODO I am sure we can do this much better, create a single insert query instead of one per added group.
        $groups = trim(KTUtil::arrayGet($_REQUEST, 'groups_roles'), ',');
        if (!empty($groups)) {
            $groups = explode(',', $groups);
            foreach ($groups as $idString) {
                $idData = explode('_', $idString);
                $subGroup = Group::get($idData[1]);

                $res = $group->addMemberGroup($subGroup);
                if (PEAR::isError($res) || $res == false) {
                    $this->errorRedirectToMain(sprintf(_kt('Failed to add %s to %s'), $subGroup->getName(), $group->getName()), sprintf('old_search=%s&do_search=1', $old_search));
                    exit(0);
                }
                else {
                    $groupsAdded[] = $subGroup->getName();
                }
            }
        }

        $groupsRemoved = array_diff($currentGroups, $groupsAdded);
        $groupsAdded = array_diff($groupsAdded, $currentGroups);

        /*$groupsAdded = array();
        $groupsRemoved = array();

        foreach ($aGroupToAddIDs as $iMemberGroupID ) {
            if ($iMemberGroupID > 0) {
                $oMemberGroup = Group::get($iMemberGroupID);
                $res = $group->addMemberGroup($oMemberGroup);
                if (PEAR::isError($res)) {
                    $this->errorRedirectToMain(sprintf(_kt('Failed to add %s to %s'), $oMemberGroup->getName(), $group->getName()), sprintf('old_search=%s&do_search=1', $old_search));
                    exit(0);
                }
                else {
                    $groupsAdded[] = $oMemberGroup->getName();
                }
            }
        }

        foreach ($aGroupToRemoveIDs as $iMemberGroupID ) {
            if ($iMemberGroupID > 0) {
                $oMemberGroup = Group::get($iMemberGroupID);
                $res = $group->removeMemberGroup($oMemberGroup);
                if (PEAR::isError($res)) {
                    $this->errorRedirectToMain(sprintf(_kt('Failed to remove %s from %s'), $oMemberGroup->getName(), $group->getName()), sprintf('old_search=%s&do_search=1', $old_search));
                    exit(0);
                }
                else {
                    $groupsRemoved[] = $oMemberGroup->getName();
                }
            }
        }*/

        $msg = '';
        if (!empty($groupsAdded)) { $msg .= ' ' . _kt('Added') . ': ' . implode(', ', $groupsAdded) . '. '; }
        if (!empty($groupsRemoved)) { $msg .= ' '. _kt('Removed'). ': ' . implode(', ',$groupsRemoved) . '.'; }

        $this->commitTransaction();

        // Invalidate the permissions cache to force an update .
        // It is possible to update only the members of the sub groups
        // but if there are a large number of members involved this can become an expensive operation.
        // It is cheaper to invalidate the cache and force a validation of each users permissions.
        KTPermissionUtil::clearCache();

        $this->successRedirectToMain($msg, sprintf('old_search=%s&do_search=1', $old_search));
    }

    // overloaded because i'm lazy
    // FIXME we probably want some way to generalise this
    // FIXME (its a common entity-problem)
    function form_addgroup()
    {
        $oForm = new KTForm;
        $oForm->setOptions(array(
            'identifier' => 'ktcore.groups.add',
            'label' => _kt('Create a new group'),
            'submit_label' => _kt('Create group'),
            'action' => 'creategroup',
            'fail_action' => 'addgroup',
            'cancel_action' => 'main',
            'context' => $this,
        ));

        $oForm->setWidgets(array(
            array('ktcore.widgets.string',
                array(
                    'name' => 'group_name',
                    'label' => _kt('Group Name'),
                    'description' => _kt('A short name for the group.  e.g. <strong>administrators</strong>.'),
                    'value' => null,
                    'required' => true,
                )
            ),
            array('ktcore.widgets.boolean',
                array(
                    'name' => 'sysadmin',
                    'label' => _kt('System Administrators'),
                    'description' => _kt('Should all the members of this group be given <strong>system</strong> administration privileges?'),
                    'value' => null,
                )
            ),
        ));

        $oForm->setValidators(array(
            array('ktcore.validators.string', array(
                'test' => 'group_name',
                'output' => 'group_name',
            )),
            array('ktcore.validators.boolean', array(
                'test' => 'sysadmin',
                'output' => 'sysadmin',
            )),
        ));

        // if we have any units.
        $aUnits = Unit::getList();
        if (!PEAR::isError($aUnits) && !empty($aUnits)) {
            $oForm->addWidgets(array(
                array('ktcore.widgets.entityselection',
                    array(
                        'name' => 'unit',
                        'label' => _kt('Unit'),
                        'description' => _kt('Which Unit is this group part of?'),
                        'vocab' => $aUnits,
                        'label_method' => 'getName',
                        'simple_select' => false,
                        'unselected_label' => _kt('No unit'),
                    )
                ),
                array('ktcore.widgets.boolean',
                    array(
                        'name' => 'unitadmin',
                        'label' => _kt('Unit Administrators'),
                        'description' => _kt('Should all the members of this group be given <strong>unit</strong> administration privileges?'),
                        'important_description' => _kt('Note that its not possible to set a group without a unit as having unit administration privileges.'),
                        'value' => null,
                    )
                )
            ));

            $oForm->addValidators(array(
                array('ktcore.validators.entity', array(
                    'test' => 'unit',
                    'class' => 'Unit',
                    'output' => 'unit',
                )),
                array('ktcore.validators.boolean', array(
                    'test' => 'unitadmin',
                    'output' => 'unitadmin',
                )),
            ));
        }

        return $oForm;
    }

    function do_addGroup()
    {
        $this->oPage->setBreadcrumbDetails(_kt('Add a new group'));

        $aAuthenticationSources = array();
        $aAllAuthenticationSources =& KTAuthenticationSource::getList();
        foreach ($aAllAuthenticationSources as $oSource) {
            $sProvider = $oSource->getAuthenticationProvider();
            $oRegistry =& KTAuthenticationProviderRegistry::getSingleton();
            $oProvider =& $oRegistry->getAuthenticationProvider($sProvider);
            if ($oProvider->bGroupSource) {
                $aAuthenticationSources[] = $oSource;
            }
        }

        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/principals/addgroup');
        $aTemplateData = array(
            'context' => $this,
            'add_fields' => $add_fields,
            'authentication_sources' => $aAuthenticationSources,
            'form' => $this->form_addgroup(),
        );

        return $oTemplate->render($aTemplateData);
    }

    function do_creategroup()
    {
        $oForm = $this->form_addgroup();
        $res = $oForm->validate();
        $data = $res['results'];
        $errors = $res['errors'];
        $extra_errors = array();

        if (is_null($data['unit']) && $data['unitadmin']) {
            $extra_errors['unitadmin'] = _kt('Groups without units cannot be Unit Administrators.');
        }

        $oGroup = Group::getByName($data['group_name']);
        if (!PEAR::isError($oGroup)) {
            $extra_errors['group_name'][] = _kt('There is already a group with that name.');
        }

        if (preg_match('/[\!\$\#\%\^\&\*]/', $data['group_name'])) {
        	$extra_errors['group_name'][] = _kt('You have entered an invalid character.');
        }

        if ($data['group_name'] == '') {
        	$extra_errors['group_name'][] = _kt('You have entered an invalid name.');
        }

        if (!empty($errors) || !empty($extra_errors)) {
            return $oForm->handleError(null, $extra_errors);
        }

        $this->startTransaction();

        $unit = null;
        if (!is_null($data['unit'])) {
            $unit = $data['unit']->getId();
        }

        $oGroup =& Group::createFromArray(array(
             'sName' => $data['group_name'],
             'bIsUnitAdmin' => KTUtil::arrayGet($data, 'unitadmin', false),
             'bIsSysAdmin' => $data['sysadmin'],
             'UnitId' => $unit,
        ));

        if (PEAR::isError($oGroup)) {
            return $oForm->handleError(sprintf(_kt('Unable to create group: %s'), $oGroup->getMessage()));
        }

        $this->commitTransaction();

        $this->successRedirectToMain(sprintf(_kt('Group "%s" created.'), $data['group_name']));
    }

    function do_deleteGroup()
    {
        $old_search = KTUtil::arrayGet($_REQUEST, 'old_search');

        $aErrorOptions = array(
            'redirect_to' => array('main', sprintf('old_search=%s&do_search=1', $old_search)),
        );
        $oGroup = $this->oValidator->validateGroup($_REQUEST['group_id'], $aErrorOptions);
        $sGroupName = $oGroup->getName();

        $this->startTransaction();

        foreach($oGroup->getParentGroups() as $oParentGroup) {
            $res = $oParentGroup->removeMemberGroup($oGroup);
        }

        $res = $oGroup->delete();
        $this->oValidator->notError($res, $aErrorOptions);

        if (!Permission::userIsSystemAdministrator($_SESSION['userID'])) {
            $this->rollbackTransaction();
            $this->errorRedirectTo('main', _kt('For security purposes, you cannot remove your own administration priviledges.'), sprintf('old_search=%s&do_search=1', $old_search));
            exit(0);
        }

        $this->commitTransaction();

        // Invalidate the permissions cache to force an update .
        KTPermissionUtil::clearCache();

        $this->successRedirectToMain(sprintf(_kt('Group "%s" deleted.'), $sGroupName), sprintf('old_search=%s&do_search=1', $old_search));
    }

    // {{{ authentication provider stuff

    function do_addGroupFromSource()
    {
        $oSource =& KTAuthenticationSource::get($_REQUEST['source_id']);
        $sProvider = $oSource->getAuthenticationProvider();
        $oRegistry =& KTAuthenticationProviderRegistry::getSingleton();
        $oProvider =& $oRegistry->getAuthenticationProvider($sProvider);

        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('Group Management'));
        $this->aBreadcrumbs[] = array('url' => KTUtil::addQueryStringSelf('action=addGroup'), 'name' => _kt('add a new group'));
        $oProvider->aBreadcrumbs = $this->aBreadcrumbs;
        $oProvider->oPage->setBreadcrumbDetails($oSource->getName());
        $oProvider->oPage->setTitle(_kt('Modify Group Details'));

        $oProvider->dispatch();
        exit(0);
    }

    function getGroupStringForGroup($oGroup)
    {
        $aGroupNames = array();
        $aGroups = $oGroup->getMemberGroups();
        $maxGroups = 6;
        $add_elipsis = false;

        if (count($aGroups) == 0) { return _kt('Group currently has no subgroups.'); }

        if (count($aGroups) > $maxGroups) {
            $aGroups = array_slice($aGroups, 0, $maxGroups);
            $add_elipsis = true;
        }

        foreach ($aGroups as $oGroup) {
            $aGroupNames[] = $oGroup->getName();
        }

        if ($add_elipsis) {
            $aGroupNames[] = '&hellip;';
        }

        return implode(', ', $aGroupNames);
    }

}

?>
