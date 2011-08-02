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

require_once(KT_LIB_DIR . '/templating/templating.inc.php');
require_once(KT_LIB_DIR . '/dispatcher.inc.php');
require_once(KT_LIB_DIR . '/templating/kt3template.inc.php');
require_once(KT_LIB_DIR . '/widgets/fieldWidgets.php');

require_once(KT_LIB_DIR . '/authentication/authenticationprovider.inc.php');
require_once(KT_LIB_DIR . '/authentication/authenticationsource.inc.php');

require_once('LdapUserManager.inc.php');

class LdapUserDispatcher extends KTAdminDispatcher {

    private $attributes = array ('cn', 'samaccountname', 'givenname', 'sn', 'mail', 'mobile', 'userprincipalname', 'uid');
    private $source;
    private $authenticatorClass;

    public function __construct($authenticatorClass)
    {
        $this->authenticatorClass = $authenticatorClass;
        $this->source = KTAuthenticationSource::get($_REQUEST['source_id']);

        $category = KTUtil::arrayGet($_REQUEST, 'fCategory');
        $subsection = KTUtil::arrayGet($_REQUEST, 'subsection');
        $this->setCategoryDetail("$category/$subsection");

        parent::KTStandardDispatcher();
    }

    public function do_addUserFromSource()
    {
        $submit = KTUtil::arrayGet($_REQUEST, 'submit');
        if (!is_array($submit)) {
            $submit = array();
        }
        // Check if its a mass import
        $massImport = KTUtil::arrayGet($_REQUEST, 'massimport');
        $isMassImport = ($massImport == 'on') ? true : false;

        if (KTUtil::arrayGet($submit, 'chosen')) {
            $id = KTUtil::arrayGet($_REQUEST, 'id');
            if (!empty($id)) {
                if ($isMassImport) {
                    return $this->_do_massCreateUsers();
                }
                else {
                    return $this->_do_editUserFromSource();
                }
            }
            else {
                $this->oPage->addError(_kt("No valid LDAP user chosen"));
            }
        }

        if (KTUtil::arrayGet($submit, 'create')) {
            return $this->_do_createUserFromSource();
        }

        $searchResults = null;
        $users = array();
        $fields = array();

        // Get the search query
        $name = KTUtil::arrayGet($_REQUEST, 'ldap_name');
        if (!empty($name) || $isMassImport) {
            $manager = new LdapUserManager($this->source);
            try {
                $searchResults = $manager->searchUsers($name, array('cn', 'dn'));
                if ($searchResults->count()) {
                    $searchResults->rewind();
                    // get dns to check existing users and populate default user result list
                    $searchDNs = array();
                    foreach ($searchResults as $key => $result) {
                        if (is_array($result['cn'])) {
                            $result['cn'] = $result['cn'][0];
                        }
                        $searchDNs[$key] = "'{$result['dn']}'";
                        $users[] = $result;
                    }

                    $dnList = implode(',', $searchDNs);
                    $query = "SELECT id, authentication_details_s1 AS dn FROM users WHERE authentication_details_s1 IN ($dnList)";
                    $currentUsers = DBUtil::getResultArray($query);

                    // If the user has already been added, then remove from the list
                    if (!PEAR::isError($currentUsers) && !empty($currentUsers)) {
                        foreach($currentUsers as $item) {
                            $key = array_search("'{$item['dn']}'", $searchDNs);
                            unset($users[$key]);
                        }
                    }
                }
            }
            catch (Exception $e) {
                $this->addErrorMessage($e->getMessage());
            }
        }

        $fields[] = new KTStringWidget(_kt("User's name"), _kt("The user's name, or part thereof, to find the user that you wish to add"), 'ldap_name', '', $this->oPage, true);
        $fields[] = new KTCheckboxWidget(_kt('Mass import'),
        _kt('Allow for multiple users to be selected to be added (will not get to manually verify the details if selected). The list may be long and take some time to load if the search is not filtered and there are a number of users in the system.')
        , 'massimport', $isMassImport, $this->oPage, true);

        $templating = KTTemplating::getSingleton();
        $template = $templating->loadTemplate('ldap_search_user');
        $templateData = array(
            'context' => $this,
            'fields' => $fields,
            'source' => $this->source,
            'search_results' => $users,
            'identifier_field' => $identifierField,
            'massimport' => $massImport,
            'section_query_string' => $this->sectionQueryString
        );

        return $template->render($templateData);
    }

    private function _do_createUserFromSource()
    {
        $dn = KTUtil::arrayGet($_REQUEST, 'dn');
        $samaccountname = KTUtil::arrayGet($_REQUEST, 'samaccountname');

        $name = KTUtil::arrayGet($_REQUEST, 'name');
        if (empty($name)) { $this->errorRedirectToMain(_kt('You must specify a name for the user.')); }

        $username = KTUtil::arrayGet($_REQUEST, 'ldap_username');
        if (empty($username)) { $this->errorRedirectToMain(_kt('You must specify a new username.')); }

        $emailAddress = KTUtil::arrayGet($_REQUEST, 'emailAddress');
        $emailNotifications = KTUtil::arrayGet($_REQUEST, 'emailNotifications', false);
        if ($emailNotifications !== false) { $emailNotifications = true; }

        $maxSessions = KTUtil::arrayGet($_REQUEST, 'max_sessions', '3');
        // FIXME check for numeric maxSessions... db-error else?

        $user = KTUserUtil::createUser($username, $name, '', $emailAddress, $emailNotifications, '', $maxSessions, $this->source->getId(), $dn, $samaccountname);

        if (PEAR::isError($user) || ($user == false)) {
            $this->errorRedirectToMain($user->getMessage());
            exit(0);
        }

        $this->successRedirectToMain(_kt('Created new user') . ': ' . $user->getUsername());
        exit(0);
    }

    private function _do_editUserFromSource()
    {
        $template = $this->oValidator->validateTemplate('ldap_add_user');
        $id = KTUtil::arrayGet($_REQUEST, 'id');

        $manager = new LdapUserManager($this->source);
        $result = $manager->getUser($id);
        $errorOptions = array(
            'message' => _kt('Could not find user in LDAP server'),
        );
        $this->oValidator->notError($result);

        $userData = $this->extractUserData($result, $id);
        extract($userData);

        $fields = array();
        $fields[] =  new KTStaticTextWidget(_kt('LDAP DN'), _kt('The location of the user within the LDAP directory.'), 'dn', $id, $this->oPage);
        $fields[] =  new KTStringWidget(_kt('Username'), sprintf(_kt('The username the user will enter to gain access to %s.  e.g. jsmith'), APP_NAME), 'ldap_username', $userName, $this->oPage, true);
        $fields[] =  new KTStringWidget(_kt('Name'), _kt('The full name of the user.  This is shown in reports and listings.  e.g. John Smith'), 'name', $name, $this->oPage, true);
        $fields[] =  new KTStringWidget(_kt('Email Address'), _kt('The email address of the user.  Notifications and alerts are mailed to this address if email notifications is set below. e.g. jsmith@acme.com'), 'emailAddress', $emailAddress, $this->oPage, false);
        $fields[] =  new KTCheckboxWidget(_kt('Email Notifications'), _kt('If this is specified then the user will have notifications sent to the email address entered above.  If it is not set, then the user will only see notifications on the Dashboard'), 'emailNotifications', true, $this->oPage, false);
        $fields[] =  new KTStringWidget(_kt('Mobile Number'), _kt('The mobile phone number of the user.  e.g. 999 9999 999'), 'mobile_number', $phone, $this->oPage, false);
        $fields[] =  new KTStringWidget(_kt('Maximum Sessions'), _kt('As a safety precaution, it is useful to limit the number of times a given account can log in, before logging out.  This prevents a single account being used by many different people.'), 'max_sessions', '3', $this->oPage, true);

        $templateData = array(
            'context' => &$this,
            'fields' => $fields,
            'source' => $this->source,
            'search_results' => $aSearchResults,
            'dn' => $id,
            'samaccountname' => $result['samaccountname'],
            'section_query_string' => $this->sectionQueryString
        );

        return $template->render($templateData);
    }

    private function _do_massCreateUsers()
    {
        $ids = KTUtil::arrayGet($_REQUEST, 'id');
        $names = array();
        $manager = new LdapUserManager($this->source);

        foreach ($ids as $id) {
            $result = $manager->getUser($id);
            $userData = $this->extractUserData($result, $id, true);
            extract($userData);
            $user = KTUserUtil::createUser($userName, $name, '', $emailAddress, true, '', 3, $this->source->getId(), $id, $userName);
            $names[] = $name;
        }

        $this->successRedirectToMain(_kt('Added users') . ': ' . join(', ', $names));
    }

    /**
     * Extract user data from the ldap result
     *
     * @param ldap result $result
     * @param boolean $append Whether to append _DUPLICATE to the user name if one already exists;
     *                        This would usually only apply to a mass import.
     * @return array
     */
    private function extractUserData($result, $dn = null, $append = false)
    {
        $user = array();

        $userName = $result[$this->attributes[1]];

        // If the SAMAccountName is empty then try alternate sources
        if (empty($userName)) {
            // try userprincipalname
            if (!empty($result[$this->attributes[6]])) {
                // use the UserPrincipalName (UPN) to find the username.
                // The UPN is normally the username @ the internet domain
                $upn = $result[$this->attributes[6]];
                $upn = explode('@', $upn);
                $userName = $upn[0];
            }
            // try uid - this will usually be the same as the dn, but may not be in dn form
            // (ref: http://publib.boulder.ibm.com/infocenter/db2luw/v8/index.jsp?topic=/com.ibm.db2.udb.doc/admin/t0006021.htm)
            else if (!empty($result[$this->attributes[7]])) {
                $userName = $result[$this->attributes[7]];
                // if in dn form, extract the cn attribute, otherwise it can be used as is.
                if (preg_match('/^cn=([^,]*),/', $userName, $matches)) {
                    $userName = $matches[1];
                }
            }
            // try dn
            else if (!empty($dn)) {
                $dnParts = ldap_explode_dn($dn, 0);
                $userName = end(explode('=', $dnParts[0]));;
            }
            // try 'givenname'
            else if (($this->authenticatorClass == 'LdapAuthenticator') && empty($userName)) {
                $userName = strtolower($result[$this->attributes[2]]);
            }
        }

        $name = $result[$this->attributes[0]];
        if (empty($name)) {
            if (!empty($dn)) {
                $dnParts = ldap_explode_dn($dn, 0);
                $name = end(explode('=', $dnParts[0]));
            }
            else {
                $name = trim("{$result[$this->attributes[2]]} {$result[$this->attributes[3]]}");
            }
        }

        $emailAddress = $result[$this->attributes[4]];

        // If the user already exists append some text so the admin can see the duplicates.
        if ($append) {
            while (!PEAR::isError(User::getByUserName($userName))) {
                $userName = $userName . '_DUPLICATE';
            }
        }

        $phone = $result[$this->attributes[5]];

        $user = compact('userName', 'name', 'emailAddress', 'phone');

        return $user;
    }

    public function handleOutput($output)
    {
        print $output;
    }

}
?>
