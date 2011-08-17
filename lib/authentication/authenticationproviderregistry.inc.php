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

/**
 * This is where all authentication providers register themselves as
 * available to the system.  Only the classes are registered here, not
 * specific instances.
 *
 * For instance, an LDAP authentication provider is registered.  It
 * can't, by itself, perform any authentication, as it is not
 * configured.
 *
 * The authenticators table in the database lists specific instances
 * configured in the system.  It contains it's own name (for humans to
 * differentiate between instances), it's own namespace name (for the
 * system and plugins to be able to find it accurately), it's
 * authentication provider namespace name, and some configuration data
 * that is handed over to the authentication provider instance to
 * configure itself.
 *
 * If a user has no authenticator set up, the KnowledgeTree
 * Authentication Provider is used.  This is hard-coded to use the
 * KnowledgeTree users table to check the password against.
 */
class KTAuthenticationProviderRegistry {
    var $_aAuthenticationProvidersInfo = array();
    var $_aAuthenticationProviders = array();

    static function &getSingleton () 
    {
        if (!KTUtil::arrayGet($GLOBALS['_KT_PLUGIN'], 'oKTAuthenticationProviderRegistry')) {
            $GLOBALS['_KT_PLUGIN']['oKTAuthenticationProviderRegistry'] = new KTAuthenticationProviderRegistry;
        }
        return $GLOBALS['_KT_PLUGIN']['oKTAuthenticationProviderRegistry'];
    }


    function registerAuthenticationProvider($name, $class, $nsname, $path = '', $plugin = null) 
    {
        $this->_aAuthenticationProvidersInfo[$nsname] = array($name, $class, $nsname, $path, $plugin);
    }

    private function loadAuthHelpers()
    {
        if (!empty($this->_aAuthenticationProvidersInfo)) {
            return ;
        }
        
        $helpers = KTPluginUtil::loadPluginHelpers('authentication_provider');
        
        foreach ($helpers as $helper) {
            extract($helper);
            $params = explode('|', $object);
            
            if (isset($params[3])) {
                $params[3] = KTPluginUtil::getFullPath($params[3]);
            }
            $params[0] = _kt($params[0]);
            call_user_func_array(array($this, 'registerAuthenticationProvider'), $params);
        }
    }
    
    function getAuthenticationProviderInfo($nsname) 
    {
        $this->loadAuthHelpers();
        return $this->_aAuthenticationProviderInfo[$nsname];
    }

    function &getAuthenticationProvider($nsname) 
    {
        $this->loadAuthHelpers();
        
        $provider = KTUtil::arrayGet($this->_aAuthenticationProviders, $nsname);
        if ($provider) {
            return $provider;
        }
        
        $info = $this->_aAuthenticationProvidersInfo[$nsname];
        $class = $info[1];
        $path = $info[3];
        if ($path) {
            $path = (KTUtil::isAbsolutePath($path)) ? $path : KT_DIR .'/'. $path;
            include_once($path);
        }
        
        if(!class_exists($class)){
            return PEAR::raiseError(sprintf(_kt('Authentication provider class does not exist. %s '), $class));
        }
        
        $provider = new $class;
        $this->_aAuthenticationProviders[$nsname] =& $provider;
        
        return $provider;
    }

    function getAuthenticationProvidersInfo() 
    {
        $this->loadAuthHelpers();
        return array_values($this->_aAuthenticationProvidersInfo);
    }
}

?>
