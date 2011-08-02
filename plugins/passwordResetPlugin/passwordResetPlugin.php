<?php
/**
 * $Id: $
 *
 * This page handles logging a user into the dms.
 * This page displays the login form, and performs the business logic login processing.
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
 */

require_once(KT_LIB_DIR . '/plugins/plugin.inc.php');
require_once(KT_LIB_DIR . '/plugins/pluginregistry.inc.php');
require_once(KT_LIB_DIR . '/templating/templating.inc.php');
require_once(KT_LIB_DIR . '/authentication/interceptor.inc.php');
require_once(KT_LIB_DIR . '/authentication/interceptorinstances.inc.php');

class PasswordResetInterceptor extends KTInterceptor {

    var $sNamespace  = 'password.reset.login.interceptor';

    function authenticated() {}

    function takeOver()
    {
    	// Skip take over if authentication is through google
    	if(KTUtil::arrayGet($_GET, 'auth', 'singlesignon') == 'google') return ;
        $pluginRegistry =& KTPluginRegistry::getSingleton();
        $plugin =& $pluginRegistry->getPlugin('password.reset.plugin');

        $KTConfig = KTConfig::getSingleton();
        if ($KTConfig->get('user_prefs/useEmailLogin', false)) {
            $dispatcherURL = $plugin->getURLPath('loginResetEmailDispatcher.php');
        }
        else {
            $dispatcherURL = $plugin->getURLPath('loginResetDispatcher.php');
        }

        $queryString = $_SERVER['QUERY_STRING'];
        $redirect = KTUtil::arrayGet($_REQUEST, 'redirect');
        $redirect = urlencode($redirect);

        $url = KTUtil::kt_url() . $dispatcherURL;
        $url .= (!empty($queryString)) ? '?'.$queryString : '';
        redirect($url);
        exit(0);
    }

}


class PasswordResetPlugin extends KTPlugin {

    var $sNamespace = 'password.reset.plugin';
    var $autoRegister = false;

    function PasswordResetPlugin($sFilename = null)
    {
        $res = parent::KTPlugin($sFilename);
        $this->sFriendlyName = _kt('Password Reset Plugin');
        return $res;
    }

    function setup()
    {
        // Check if interceptor instance exists
        $interceptorNamespace = 'password.reset.login.interceptor';
        $interceptor = KTInterceptorInstance::getByInterceptorNamespace($interceptorNamespace);
        // Register the interceptor
        $this->registerInterceptor('PasswordResetInterceptor', $interceptorNamespace, __FILE__);
        // Add templates directory to list
        $dir = dirname(__FILE__);
        $templating =& KTTemplating::getSingleton();
        $templating->addLocation('passwordResetPlugin', $dir . '/templates');
    	if (!($interceptor instanceof KTEntityNoObjects)) { return ; }
        // Interceptor has to be added to the DB to be found
        $options = array(
            'sName' => 'Password Reset Interceptor',
            'sInterceptorNamespace' => $interceptorNamespace,
            'sConfig' => ''
        );
        KTInterceptorInstance::createFromArray($options);
    }

}

$pluginRegistry =& KTPluginRegistry::getSingleton();
$pluginRegistry->registerPlugin('PasswordResetPlugin', 'password.reset.plugin', __FILE__);
?>
