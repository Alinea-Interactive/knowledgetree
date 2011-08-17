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

require_once(KT_LIB_DIR . '/plugins/pluginentity.inc.php');
require_once(KT_LIB_DIR . '/plugins/pluginregistry.inc.php');
require_once(KT_LIB_DIR . '/memcache/ktmemcache.php');

class KTPluginResourceRegistry {

    var $aResources = array();

    function &getSingleton()
    {
        if (!KTUtil::arrayGet($GLOBALS, 'oKTPluginResourceRegistry')) {
            $GLOBALS['oKTPluginResourceRegistry'] = new KTPluginResourceRegistry;
        }

        return $GLOBALS['oKTPluginResourceRegistry'];
    }

    function registerResource($sPath)
    {
        $this->aResources[$sPath] = true;
    }

    function isRegistered($sPath)
    {
        if (KTUtil::arrayGet($this->aResources, $sPath)) {
            return true;
        }
        $sPath = dirname($sPath);
        if (KTUtil::arrayGet($this->aResources, $sPath)) {
            return true;
        }
        return false;
    }

}

class KTPluginUtil {

    /**
     * Load the plugins for the current page
     *
     * @param unknown_type $sType
     */
    static public function loadPlugins ()
    {
        // Get enabled plugins, load each plugin
        self::loadCachedPlugins();
        return true;
        
        // Check the current page - can be extended.
        // Currently we only distinguish between the dashboard and everything else.
        if ($sType != 'dashboard') {
          $sType = 'general';
        }

        $aPlugins = array();
        $aPluginHelpers = array();
        $aDisabled = array();
        
        // Get the list of enabled plugins
        // Check that there are plugins and if not, register them
        // Create plugin objects
        // load plugin helpers into global space
        // Load the template locations - ignore disabled plugins
        // Allow for templates that don't correctly link to the plugin
        
        $sDisabled = implode(',', $aDisabled);
        $sDisabled = '';

        // load plugin helpers into global space
        $query = 'SELECT h.* FROM plugin_helper h
            INNER JOIN plugins p ON (p.namespace = h.plugin)
            WHERE p.disabled = 0 ';//WHERE viewtype='{$sType}'";
        if (!empty($sDisabled)) {
               $query .= " AND h.plugin NOT IN ($sDisabled) ";
        }
        $query .= ' ORDER BY p.orderby';

        $aPluginList = DBUtil::getResultArray($query);
        KTPluginUtil::load($aPluginList);

        // Load the template locations - ignore disabled plugins
        // Allow for templates that don't correctly link to the plugin
        $query = "SELECT * FROM plugin_helper h
            LEFT JOIN plugins p ON (p.namespace = h.plugin)
            WHERE h.classtype='locations' AND (disabled = 0 OR disabled IS NULL) AND unavailable = 0";

        $aLocations = DBUtil::getResultArray($query);

        if (!empty($aLocations)) {
            $oTemplating =& KTTemplating::getSingleton();
            foreach ($aLocations as $location) {
                $aParams = explode('|', $location['object']);
                call_user_func_array(array(&$oTemplating, 'addLocation2'), $aParams);
            }
        }

        return true;
    }
    
    static private function loadCachedPlugins()
    {
        $pluginCache = PluginCache::getPluginCache();
        $pluginsList = $pluginCache->getPlugins();
        
        foreach ($pluginsList['enabled'] as $priority => $list) {
            
            foreach ($list as $plugin) {
            
                $classname = $plugin['classname'];
                $path = $plugin['pathname'];
                
                if (class_exists($classname)) {
                    continue;
                }
    
                if (!empty($path)) {
                    if ((strpos($path, KT_DIR) === false)) {
                        $path = KT_DIR . '/' . $path;
                    }
    
                    if (file_exists($path)) {
                        require_once($path);
    
                        $pluginObject = new $classname($path);
                        $pluginObject->load();
                    }
                }
            }
        }
    }

    static public function loadPluginHelpers($classtype)
    {
        $pluginCache = PluginCache::getPluginCache();
        $helpers = $pluginCache->getPluginHelpersByType($classtype);
        
        return $helpers;
    }
    
    /**
     * Load the plugins into the global space
     *
     * @param array $aPlugins
     */
    function load($aPlugins)
    {
        require_once(KT_LIB_DIR . '/actions/actionregistry.inc.php');
        require_once(KT_LIB_DIR . '/actions/portletregistry.inc.php');
        require_once(KT_LIB_DIR . '/triggers/triggerregistry.inc.php');
        require_once(KT_LIB_DIR . '/plugins/pageregistry.inc.php');
        require_once(KT_LIB_DIR . '/authentication/authenticationproviderregistry.inc.php');
        require_once(KT_LIB_DIR . "/plugins/KTAdminNavigation.php");
        require_once(KT_LIB_DIR . "/dashboard/dashletregistry.inc.php");
        require_once(KT_LIB_DIR . "/i18n/i18nregistry.inc.php");
        require_once(KT_LIB_DIR . "/help/help.inc.php");
        require_once(KT_LIB_DIR . "/workflow/workflowutil.inc.php");
        require_once(KT_LIB_DIR . "/widgets/widgetfactory.inc.php");
        require_once(KT_LIB_DIR . "/validation/validatorfactory.inc.php");
        require_once(KT_LIB_DIR . "/browse/columnregistry.inc.php");
        require_once(KT_LIB_DIR . "/browse/criteriaregistry.php");
        require_once(KT_LIB_DIR . "/authentication/interceptorregistry.inc.php");

        $oPRegistry =& KTPortletRegistry::getSingleton();
        $oTRegistry =& KTTriggerRegistry::getSingleton();
        $oARegistry =& KTActionRegistry::getSingleton();
        $oPageRegistry =& KTPageRegistry::getSingleton();
        $oAPRegistry =& KTAuthenticationProviderRegistry::getSingleton();
        $oAdminRegistry =& KTAdminNavigationRegistry::getSingleton();
        $oDashletRegistry =& KTDashletRegistry::getSingleton();
        $oi18nRegistry =& KTi18nRegistry::getSingleton();
        $oKTHelpRegistry =& KTHelpRegistry::getSingleton();
        $oWFTriggerRegistry =& KTWorkflowTriggerRegistry::getSingleton();
        $oColumnRegistry =& KTColumnRegistry::getSingleton();
        $oNotificationHandlerRegistry =& KTNotificationRegistry::getSingleton();
        $oTemplating =& KTTemplating::getSingleton();
        $oWidgetFactory =& KTWidgetFactory::getSingleton();
        $oValidatorFactory =& KTValidatorFactory::getSingleton();
        $oCriteriaRegistry =& KTCriteriaRegistry::getSingleton();
        $oInterceptorRegistry =& KTInterceptorRegistry::getSingleton();
        $oKTPluginRegistry =& KTPluginRegistry::getSingleton();

        // Loop through the loaded plugins and register them for access
        foreach ($aPlugins as $plugin) {
            $sName = $plugin['namespace'];
            $sParams = $plugin['object'];
            $aParams = explode('|', $sParams);
            $sClassType = $plugin['classtype'];

            switch ($sClassType) {
                case 'portlet':
                    $aLocation = unserialize($aParams[0]);
                    if ($aLocation != false) {
                       $aParams[0] = $aLocation;
                    }
                    if (isset($aParams[3])) {
                        $aParams[3] = KTPluginUtil::getFullPath($aParams[3]);
                    }
                    call_user_func_array(array(&$oPRegistry, 'registerPortlet'), $aParams);
                    break;

                case 'trigger':
                    if (isset($aParams[4])) {
                        $aParams[4] = KTPluginUtil::getFullPath($aParams[4]);
                    }
                    call_user_func_array(array(&$oTRegistry, 'registerTrigger'), $aParams);
                    break;

                case 'action':
                    if (isset($aParams[3])) {
                        $aParams[3] = KTPluginUtil::getFullPath($aParams[3]);
                    }
                    call_user_func_array(array(&$oARegistry, 'registerAction'), $aParams);
                    break;

                case 'page':
                    if (isset($aParams[2])) {
                        $aParams[2] = KTPluginUtil::getFullPath($aParams[2]);
                    }
                    call_user_func_array(array(&$oPageRegistry, 'registerPage'), $aParams);
                    break;

                case 'authentication_provider':
                    if (isset($aParams[3])) {
                        $aParams[3] = KTPluginUtil::getFullPath($aParams[3]);
                    }
                    $aParams[0] = _kt($aParams[0]);
                    call_user_func_array(array(&$oAPRegistry, 'registerAuthenticationProvider'), $aParams);
                    break;

                case 'admin_category':
                    $aParams[1] = _kt($aParams[1]);
                    $aParams[2] = _kt($aParams[2]);
                    call_user_func_array(array(&$oAdminRegistry, 'registerCategory'), $aParams);
                    break;

                case 'admin_page':
                    if (isset($aParams[5])) {
                        $aParams[5] = KTPluginUtil::getFullPath($aParams[5]);
                    }
                    $aParams[3] = _kt($aParams[3]);
                    $aParams[4] = _kt($aParams[4]);
                    call_user_func_array(array(&$oAdminRegistry, 'registerLocation'), $aParams);
                    break;

                case 'dashlet':
                    if (isset($aParams[2])) {
                        $aParams[2] = KTPluginUtil::getFullPath($aParams[2]);
                    }
                    call_user_func_array(array(&$oDashletRegistry, 'registerDashlet'), $aParams);
                    break;

                case 'i18nlang':
                    if (isset($aParams[2]) && $aParams[2] != 'default') {
                        $aParams[2] = KTPluginUtil::getFullPath($aParams[2]);
                    }
                    call_user_func_array(array(&$oi18nRegistry, 'registeri18nLang'), $aParams);

                case 'i18n':
                    if (isset($aParams[2])) {
                        $aParams[1] = $aParams[2];
                        unset($aParams[2]);
                    } else {
                        $aParams[1] = KTPluginUtil::getFullPath($aParams[1]);
                    }
                    call_user_func_array(array(&$oi18nRegistry, 'registeri18n'), $aParams);
                    break;

                case 'language':
                    call_user_func_array(array(&$oi18nRegistry, 'registerLanguage'), $aParams);
                    break;

                case 'help_language':
                    if (isset($aParams[2])) {
                        $aParams[2] = KTPluginUtil::getFullPath($aParams[2]);
                    }
                    call_user_func_array(array(&$oKTHelpRegistry, 'registerHelp'), $aParams);
                    break;

                case 'workflow_trigger':
                    if (isset($aParams[2])) {
                        $aParams[2] = KTPluginUtil::getFullPath($aParams[2]);
                    }
                    call_user_func_array(array(&$oWFTriggerRegistry, 'registerWorkflowTrigger'), $aParams);
                    break;

                case 'column':
                    if (isset($aParams[3])) {
                        $aParams[3] = KTPluginUtil::getFullPath($aParams[3]);
                    }
                    $aParams[0] = _kt($aParams[0]);
                    call_user_func_array(array(&$oColumnRegistry, 'registerColumn'), $aParams);
                    break;

                case 'view':
                    $aParams[0] = _kt($aParams[0]);
                    call_user_func_array(array(&$oColumnRegistry, 'registerView'), $aParams);
                    break;

                case 'notification_handler':
                    if (isset($aParams[2])) {
                        $aParams[2] = KTPluginUtil::getFullPath($aParams[2]);
                    }
                    call_user_func_array(array(&$oNotificationHandlerRegistry, 'registerNotificationHandler'), $aParams);
                    break;

                case 'template_location':
                    if (isset($aParams[1])) {
                        $aParams[1] = KTPluginUtil::getFullPath($aParams[1]);
                    }
                    call_user_func_array(array(&$oTemplating, 'addLocation2'), $aParams);
                    break;

                case 'criterion':
                    $aInit = unserialize($aParams[3]);
                    if ($aInit != false) {
                       $aParams[3] = $aInit;
                    }
                    if (isset($aParams[2])) {
                        $aParams[2] = KTPluginUtil::getFullPath($aParams[2]);
                    }
                    call_user_func_array(array(&$oCriteriaRegistry, 'registerCriterion'), $aParams);
                    break;

                case 'widget':
                    if (isset($aParams[2])) {
                        $aParams[2] = KTPluginUtil::getFullPath($aParams[2]);
                    }
                    call_user_func_array(array(&$oWidgetFactory, 'registerWidget'), $aParams);
                    break;

                case 'validator':
                    if (isset($aParams[2])) {
                        $aParams[2] = KTPluginUtil::getFullPath($aParams[2]);
                    }
                    call_user_func_array(array(&$oValidatorFactory, 'registerValidator'), $aParams);
                    break;

                case 'interceptor':
                    if (isset($aParams[2])) {
                        $aParams[2] = KTPluginUtil::getFullPath($aParams[2]);
                    }
                    call_user_func_array(array(&$oInterceptorRegistry, 'registerInterceptor'), $aParams);
                    break;

                case 'plugin':
                    if (isset($aParams[2])) {
                        $aParams[2] = KTPluginUtil::getFullPath($aParams[2]);
                    }
                    $oKTPluginRegistry->_aPluginDetails[$sName] = $aParams;
                    break;
            }
        }
    }

    /**
     * Get the absolute path
     */
    function getFullPath($sPath = '')
    {
        if (empty($sPath)) {
            return '';
        }

        $sPath = (KTUtil::isAbsolutePath($sPath)) ? $sPath : KT_DIR . '/' . $sPath;
        return $sPath;
    }

    /**
     * This loads the plugins in the plugins folder. It searches for files ending with 'Plugin.php'.
     * This is called by the 'Reread plugins' action in the web interface.
     */
    function registerPlugins ()
    {
        global $default;

        // Path to lock file
        $cacheDir = $default->cacheDirectory . DIRECTORY_SEPARATOR;
        $lockFile = $cacheDir.'plugin_register.lock';

        // Check if the lock file exists
        if (KTPluginUtil::doCheck($lockFile)) {
            return true;
        }

        // Create the lock file, run through the plugin registration and then delete the lock file
        touch($lockFile);
        KTPluginUtil::doPluginRegistration();
        @unlink($lockFile);
    }

    /**
     * Check the lockfile
     */
    function doCheck($lockFile)
    {
        if (file_exists($lockFile)) {
            // If it does exist, do a stat on it to check when it was created.
            // if it was accessed more than 5 minutes ago then delete it and proceed with the plugin registration
            // otherwise wait till lock file is deleted signalling that the registration is complete and return.

            $stat = stat($lockFile);

            $time = time() - (60 * 5);
            if ($stat['mtime'] > $time) {

                $cnt = 0;

                while(file_exists($lockFile)) {
                    $cnt++;
                    sleep(2);

                    // if we've been waiting too long - typically it should only take a few seconds so 2 mins is too much time.
                    if ($cnt > 60) {
                        @unlink($lockFile);
                        return false;
                    }
                }
                return true;
            }

            @unlink($lockFile);
        }

        return false;
    }

    /* Get the priority of the plugin */
    function getPluginPriority($file)
    {
        $defaultPriority = 10;
        $priority = array(
            "ktcore" => 1,
            "ktstandard" => 2,
            "i18n" => 3
        );

        foreach($priority as $pattern => $priority) {
            if (ereg($pattern, $file)) {
                return $priority;
            }
        }
        return $defaultPriority;
    }

    /**
     * Read the plugins directory and register all plugins in the database.
     */
    function doPluginRegistration()
    {
        global $default;

        KTPluginUtil::_deleteSmartyFiles();
        require_once(KT_LIB_DIR . '/cache/cache.inc.php');
        $oCache =& KTCache::getSingleton();
        $oCache->deleteAllCaches();

        // Remove all entries from the plugin_helper table and refresh it.
        $query = "DELETE FROM plugin_helper";
        $res = DBUtil::runQuery($query);

        $files = array();
        $plugins = array();

        KTPluginUtil::_walk(KT_DIR . '/plugins', $files);
        foreach ($files as $sFile) {
            $plugin_ending = 'Plugin.php';
            if (substr($sFile, -strlen($plugin_ending)) === $plugin_ending) {
                /* Set default priority */
                $plugins[$sFile] = KTPluginUtil::getPluginPriority($sFile);
            }
        }

        /* Sort the plugins by priority */
        asort($plugins);

        /*
        Add a check to indicate that plugin registration is occuring.
        This check has been put in place to prevent the plugin being registered on every page load.
        */
        $_SESSION['plugins_registerplugins'] = true;
        foreach($plugins as $sFile => $priority) {
            require_once($sFile);
        }
        $_SESSION['plugins_registerplugins'] = false;

        $oRegistry =& KTPluginRegistry::getSingleton();
        $aRegistryList = $oRegistry->getPlugins();
        foreach ($aRegistryList as $oPlugin) {
            $res = $oPlugin->register();
            if (PEAR::isError($res)) {
                //var_dump($res);
                $default->log->debug('Register of plugin failed: ' . $res->getMessage());
            }
        }

        $aPluginList = KTPluginEntity::getList();
        foreach ($aPluginList as $oPluginEntity) {
            $sPath = $oPluginEntity->getPath();
            if (!KTUtil::isAbsolutePath($sPath)) {
                $sPath = sprintf("%s/%s", KT_DIR, $sPath);
            }
            // Check that the file exists at the given path
            // If it doesn't set it as unavailable and disabled
            // else set it as available and enabled.
            // We'll document this in case they've specifically disabled certain plugins
            if (!file_exists($sPath)) {
                $oPluginEntity->setUnavailable(true);
                $oPluginEntity->setDisabled(true);
                $res = $oPluginEntity->update();
            } else if ($oPluginEntity->getUnavailable()) {
                $oPluginEntity->setUnavailable(false);
                $oPluginEntity->setDisabled(false);
                $res = $oPluginEntity->update();
            }
        }

        KTPluginEntity::clearAllCaches();
        KTPluginUtil::_deleteSmartyFiles();
        require_once(KT_LIB_DIR . '/cache/cache.inc.php');
        $oCache =& KTCache::getSingleton();
        $oCache->deleteAllCaches();

        //KTPluginUtil::removePluginCache();
    }

    function _deleteSmartyFiles()
    {
        $oConfig =& KTConfig::getSingleton();
        $dir = sprintf('%s/%s', $oConfig->get('urls/varDirectory'), 'tmp');

        $dh = @opendir($dir);
        if (empty($dh)) {
            return;
        }

        $aFiles = array();
        while (false !== ($sFilename = readdir($dh))) {
            if (substr($sFilename, -10) == "smarty.inc") {
               $aFiles[] = sprintf('%s/%s', $dir, $sFilename);
            }
            if (substr($sFilename, -10) == "smarty.php") {
               $aFiles[] = sprintf('%s/%s', $dir, $sFilename);
            }
        }

        foreach ($aFiles as $sFile) {
            @unlink($sFile);
        }
    }

    function _walk ($path, &$files)
    {
        if (!is_dir($path)) {
            return;
        }

        $dirh = opendir($path);
        // skip '.', '..', and hidden directories - i.e. anything which begins with a '.'
        while (($entry = readdir($dirh)) !== false) {
            if (preg_match('/^\./', $entry)) {
                continue;
            }
            $newpath = $path . '/' . $entry;
            if (is_dir($newpath)) {
                KTPluginUtil::_walk($newpath, $files);
            }
            if (!is_file($newpath)) {
                continue;
            }
            $files[] = $newpath;
        }
    }

    function resourceIsRegistered($path)
    {
        $oRegistry =& KTPluginResourceRegistry::getSingleton();
        return $oRegistry->isRegistered($path);
    }

    function registerResource($path)
    {
        $oRegistry =& KTPluginResourceRegistry::getSingleton();
        $oRegistry->registerResource($path);
    }

    function readResource($sPath)
    {
        global $default;
        $php_file = ".php";
        if (substr($sPath, -strlen($php_file)) === $php_file) {
            require_once($php_file);
        } else {
            $pi = pathinfo($sPath);
            $mime_type = "";
            $sExtension = KTUtil::arrayGet($pi, 'extension');
            if (!empty($sExtension)) {
                $mime_type = DBUtil::getOneResultKey(array("SELECT mimetypes FROM " . $default->mimetypes_table . " WHERE LOWER(filetypes) = ?", $sExtension), "mimetypes");
            }
            if (empty($mime_type)) {
                $mime_type = "application/octet-stream";
            }
            $sFullPath = KT_DIR . '/plugins' . $sPath;
            header("Content-Type: $mime_type");
            header("Content-Length: " . filesize($sFullPath));
            readfile($sFullPath);
        }
    }

    /**
     * Enable/disable a plugin Or set it to display or be hidden in the admin interface
     *
     * @param string $plugin The namespace of the plugin to update
     * @param boolean $list_admin Default null. true = display, false = hide
     * @param boolean $disable Default null. true = disable, false = enable
     */
    static function setPluginVisibility($plugin, $list_admin = null, $disable = null)
    {
        $fieldValues = array();

        if (is_bool($list_admin)) {
            $fieldValues['list_admin'] = $list_admin ? 1 : 0;
        }

        if (is_bool($disable)) {
            $fieldValues['disabled'] = $disable ? 1 : 0;
        }

        $whereValues = array('namespace' => $plugin);
        $res = DBUtil::whereUpdate('plugins', $fieldValues, $whereValues);
    }

    /**
     * Get the full path to the plugin
     *
     * @param string $sNamespace The namespace of the plugin
     * @param bool $relative Whether the path should be relative or full
     * @return string
     */
    static function getPluginPath($sNamespace, $relative = false)
    {
        $oEntity = KTPluginEntity::getByNamespace($sNamespace);

        if (PEAR::isError($oEntity)) {
            return $oEntity;
        }
        $dir = dirname($oEntity->getPath()) . '/';

        if (!$relative && (strpos($dir, KT_DIR) === false)) {
            $dir = KT_DIR . '/' . $dir;
        }

        return $dir;
    }

    // utility function to detect if the plugin is loaded and active.
    static function pluginIsActive($sNamespace)
    {

        $oReg = KTPluginRegistry::getSingleton();
        $plugin = $oReg->getPlugin($sNamespace);

        if (is_null($plugin) || PEAR::isError($plugin)) { return false; }  // no such plugin
        else { // check if its active
            $ent = KTPluginEntity::getByNamespace($sNamespace);

            if (PEAR::isError($ent)) { return false; }

            // we now can ask
            return (!$ent->getDisabled());
        }
    }

    static function getPluginFiles(&$files, $directory = '')
    {
        $directory or $directory = KT_DIR . '/plugins' . $directory;
        
        $newfiles = glob($directory . '/*Plugin.php');
        $files = array_merge($files, $newfiles);
        
        $dirs = glob($directory . '/*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            self::getPluginFiles($files, $dir);
        }
        return ;
    }
    
    static function updatePlugins()
    {
        $files = array();
        self::getPluginFiles($files);
        
        
        // Session variable informs the plugin to register itself - prevents them being registered on every page load
        $_SESSION['plugins_registerplugins'] = true;
        foreach ($files as $file) {
            require_once($file);
        }
        $_SESSION['plugins_registerplugins'] = false;
        
        DBUtil::startTransaction();
        
        $pluginRegistry = KTPluginRegistry::getSingleton();
        $registeredPlugins = $pluginRegistry->getPlugins();
        foreach ($registeredPlugins as $plugin) {
            $result = $plugin->register();
            
            if (PEAR::isError($result)) {
                $default->log->debug('Register of plugin failed: ' . $result->getMessage());
            }
        }
        
        DBUtil::commit();

        $pluginList = KTPluginEntity::getList();
        foreach ($pluginList as $plugin) {
            $path = $plugin->getPath();
            if (!KTUtil::isAbsolutePath($path)) {
                $path = sprintf("%s/%s", KT_DIR, $path);
            }
            
            // file_exists is slower but should only evaluate when not in the array.
            if (!in_array($path, $files) && !file_exists($path)) {
                $plugin->setUnavailable(true);
                $plugin->setDisabled(true);
                $res = $plugin->update();
            } 
            else if ($plugin->getUnavailable()) {
                $plugin->setUnavailable(false);
                $plugin->setDisabled(false);
                $res = $plugin->update();
            }
        }


        KTPluginEntity::clearAllCaches();
        KTPluginUtil::_deleteSmartyFiles();
        require_once(KT_LIB_DIR . '/cache/cache.inc.php');
        $cache = KTCache::getSingleton();
        $cache->deleteAllCaches();
    }
    
}

/**
 * Provides access to the plugins and their helpers (triggers, etc).
 * Everything is stored in Memcache.
 * If Memcache is not available then the plugin_helper table is used.
 */
class PluginCache {

    private static $pluginCache;
    private $memcache;
    private $namespace;
    private $namespaceKey;
    private $pluginHelperCacheKey;
    private $pluginHelperDynamicKey;
    private $pluginsCacheKey;
    private $lockedCacheKey;
    
    private function __construct()
    {
        $this->memcache = KTMemcache::getKTMemcache();

        if ($this->memcache->isEnabled() === false) {
            $this->memcache = false;
            
            global $default;
            $default->log->info('Plugin Cache: Memcache not enabled - using DB');
        }
        
        // Create the key for the namespace using the account name
        $this->namespaceKey = 'plugins-key';
        $this->lockedCacheKey = 'cache-lock';
        if (defined('ACCOUNT_NAME')) {
            $this->namespaceKey = ACCOUNT_NAME . '-' . $this->namespaceKey;
            $this->lockedCacheKey = ACCOUNT_NAME . '-' . $this->lockedCacheKey;
        }
        $this->namespace = $this->getNamespace();
        
        // Create all keys
        $this->pluginHelperCacheKey = 'plugin-helper-cache';
        $this->pluginHelperDynamicKey = 'plugin-helper-dynamic';
        $this->pluginsCacheKey = 'plugins-cache';
    }
    
    public static function getPluginCache()
    {
        if (empty(self::$pluginCache)) {
            self::$pluginCache = new PluginCache();
        }

        return self::$pluginCache;
    }
  
    public function clearPluginSession()
    {
        unset($_SESSION['plugin-list']);
        unset($_SESSION['plugin-helper-list']);
    }
    
    public function getPlugins()
    {
        if (!$this->validatePlugins()) {
            $this->clearPluginSession();
        }
        
        if (isset($_SESSION['plugin-list']) && !empty($_SESSION['plugin-list'])) {
            return $_SESSION['plugin-list'];
        }
        
        if ($this->memcache !== false) {
            $pluginList = $this->memcache->get($this->namespace . '-' . $this->pluginsCacheKey);
        }
        
        if (!$pluginList) {
            $helpers = $this->getPluginHelpersByType('plugin');
            
            if (!$helpers || count($helpers) <= 1) {
                $this->updatePlugins();
                $helpers = $this->getPluginHelpersByType('plugin');
            }
            
            $pluginList = $this->getPluginsList($helpers);
            
            if ($this->memcache !== false) {
                $this->memcache->set($this->namespace . '-' . $this->pluginsCacheKey, $loaded);
            }
            
        }
        
        $_SESSION['plugin-list'] = $pluginList;
        
        return $pluginList;
    }
     
    /**
     * @deprecated 
     */
    private function getPluginsFromDB()
    {
        global $default;
        $default->log->info('Plugin Cache: using the DB');
        
        $query = "SELECT h.classname, h.pathname, h.plugin FROM plugin_helper h
            INNER JOIN plugins p ON (p.namespace = h.plugin)
           WHERE p.disabled = 0 AND h.classtype='plugin' ORDER BY p.orderby";
        $plugins = DBUtil::getResultArray($query);

        if (PEAR::isError($plugins)) {
            $default->log->error('Plugin Cache: '.$plugins->getMessage());
            return $plugins;
        }

        // Check that there are plugins and if not, register them
        if (empty($plugins) || (isset($_POST['_force_plugin_truncate']))) {
            $default->log->error('Plugin Cache: updating DB plugins');
            
            KTPluginUtil::updatePlugins();

            $query = "SELECT h.classname, h.pathname, h.plugin FROM plugin_helper h
               INNER JOIN plugins p ON (p.namespace = h.plugin)
               WHERE p.disabled = 0 AND h.classtype='plugin' ORDER BY p.orderby";
            $plugins = DBUtil::getResultArray($query);
        }
        
        return $plugins;
    }
    
    /**
     * Check whether the memcache plugins have been updated.
     * Store the plugins namespace in session to ensure that an update will be propogated.
     *
     * @return bool True if valid | False if invalid
     */
    public function validatePlugins()
    {
        $sessionNamespace = (isset($_SESSION['plugin-namespace']) && !empty($_SESSION['plugin-namespace'])) ? $_SESSION['plugin-namespace'] : '';

        if (empty($sessionNamespace)) {
            $_SESSION['plugin-namespace'] = $this->getNamespace();
            return false;
        }

        $namespace = $this->getNamespace();
        if ($sessionNamespace != $namespace) {
            $_SESSION['plugin-namespace'] = $namespace;
            return false;
        }

        return true;
    }

    /**
     * Invalidate the plugin helpers stored in memcache by updating the namespace.
     * Note: the namespace is unique per account so this will invalidate plugins for the current account
     *
     * @access public
     */
    public function invalidatePlugins()
    {
        if ($this->memcache === false) {
            $this->clearDBHelpers();
        }
        else {
            $this->setNamespace();
            $this->clearPluginHelpers();
        }
        
        unset($_SESSION['plugin-namespace']);
        $this->clearPluginSession();
    }

    private function getNamespace()
    {
        if ($this->memcache === false) {
            return $this->namespaceKey;
        }
        
        $namespace = $this->memcache->get($this->namespaceKey);

        // If the key doesn't exist or has expired then set a new one.
        if (empty($namespace)) {
            $this->setNamespace();
            $namespace = $this->namespace;
        }

        return $namespace;
    }

    /**
     * Set a new unique namespace.
     * The namespace is a combination of the key and the current timestamp to make it unique.
     * Expiration is left as the default 30 days.
     *
     * @access private
     */
    private function setNamespace()
    {
        $namespace = $this->namespaceKey . '-' . time();
        $namespace = base64_encode($namespace);
        $this->memcache->set($this->namespaceKey, $namespace);
        $this->namespace = $namespace;
    }

    private function getPluginsList($helpers)
    {
        global $default;
        $default->log->info('Plugin Cache: updating cached plugin list');
        
        $query = "SELECT * FROM plugins p ORDER BY p.orderby";
        $plugins = DBUtil::getResultArray($query);
        
        if (PEAR::isError($plugins)) {
            $default->log->error('Plugin Cache: '.$plugins->getMessage());
            return $plugins;
        }
        
        $loaded = array('enabled' => array(), 'disabled' => array());
        
        foreach ($plugins as $plugin) {
            
            $object = $helpers[$plugin['namespace']];
            
            if ($plugin['disabled'] == 1 || $plugin['unavailable'] == 1) {
                $loaded['disabled'][$plugin['orderby']][] = $object;
            }
            else {
                $loaded['enabled'][$plugin['orderby']][] = $object;
            }
        }
        
        return $loaded;
    }
    
    private function updatePlugins()
    {
        $lock = $this->updateLock('get');
        
        if ($lock == 'in-progress') {
            global $default;
            $default->log->info('Plugins: lock in place, waiting for update to complete.');
            
            $cnt = 0;
            while ($lock == 'in-progress' && $cnt < 15) {
                sleep(2);
                $cnt++;
                $lock = $this->updateLock('get');
            }
            
            if ($lock == 'in-progress') {
                $default->log->warn('Plugins: lock still in place after 30 seconds, exiting.');
                return false;
            }
            
            return true;
        }
        
        $this->updateLock('set');

        $this->invalidatePlugins();
        
        KTPluginUtil::updatePlugins();

        $this->removeDisabledPluginHelpers();
        $this->clearPluginSession();
        
        $this->updateLock('delete');
    }
    
    private function updateLock($getOrSet = 'get') 
    {
        if ($this->memcache === false) {
            return $this->updateDBLock($getOrSet);
        }
        
        switch ($getOrSet) {
            case 'set':
                $this->memcache->set($this->lockedCacheKey, 'in-progress');
                break;
            case 'delete':
                $this->memcache->delete($this->lockedCacheKey);
                break;
            case 'get':
            default:
                return $this->memcache->get($this->lockedCacheKey);
        }
        
        return true;
    }
    
    private function updateDBLock($getOrSet = 'get')
    {
        switch ($getOrSet) {
            case 'set':
                KTUtil::setSystemSetting($this->lockedCacheKey, 'in-progress');
                break;
            case 'delete':
                KTUtil::setSystemSetting($this->lockedCacheKey, false);
                break;
            case 'get':
            default:
                return KTUtil::getSystemSetting($this->lockedCacheKey, false);
        }
        
        return true;
    }
    
    private function removeDisabledPluginHelpers()
    {
        if ($this->memcache === false) {
            return ;
        }
        
        unset($_SESSION['plugin-helper-list']);
        
        $helpers = $this->getPluginHelpersByType('plugin');
        $pluginList = $this->getPluginsList($helpers);
        $helpers = $this->getPluginHelpers();

        // Unset all helpers for disabled plugins
        foreach ($pluginsList['disabled'] as $plugins) {
            
            foreach ($plugins as $plugin) {
            
                foreach ($helpers as $classtype => $list) {
                    unset($helpers[$classtype][$plugin['namespace']]);
                }
            }
        }
        
        $this->setPluginHelpers($helpers);
    }
    
    public function getPluginHelpersByType($classtype)
    {
        $helpers = $this->getPluginHelpers();
        
        if (!$helpers) {
            $this->updatePlugins();
            $helpers = $this->getPluginHelpers();
        }
            
        return $helpers[$classtype];
    }
    
    private function getPluginHelpers()
    {
        if (isset($_SESSION['plugin-helper-list']) && !empty($_SESSION['plugin-helper-list'])) {
            return $_SESSION['plugin-helper-list'];
        }
        
        if ($this->memcache === false) {
            $helpers = $this->getDBHelpers();
        }
        else {
            $helpers = $this->memcache->get($this->namespace . '-' . $this->pluginHelperDynamicKey);
        }
        
        $_SESSION['plugin-helper-list'] = $helpers;
        
        return $helpers;
    }
    
    private function getDBHelpers()
    {
        $sql = 'SELECT h.* FROM plugin_helper h, plugins p WHERE h.plugin = p.namespace AND p.disabled = 0 AND p.unavailable = 0';
        
        $result = DBUtil::getResultArray($sql);
        
        if (PEAR::isError($result) || empty($result)) {
            return false;
        }
        
        $helpers = array();
        
        foreach ($result as $item) {
            $helpers[$item['classtype']][$item['namespace']] = $item;
        }
        
        return $helpers;
    }
    
    private function setPluginHelpers($helpers)
    {
        $this->memcache->set($this->namespace . '-' . $this->pluginHelperDynamicKey, $helpers);
    }
    
    private function clearPluginHelpers()
    {
        $this->memcache->delete($this->namespace . '-' . $this->pluginHelperDynamicKey);
    }
    
    public function addPluginHelper($options)
    {
        if ($this->memcache === false) {
            return $this->addDBHelper($options);
        }
        
        $helpers = $this->getPluginHelpers();
        
        extract($options);
        $helpers[$classtype][$namespace] = $options;
        $this->setPluginHelpers($helpers);
        $_SESSION['plugin-helper-list'] = $helpers;
        
        return true;
    }
    
    public function removePluginHelper($namespace, $classtype)
    {
        if ($this->memcache === false) {
            return $this->removeDBHelper($namespace, $classtype);
        }
        
        $helpers = $this->getPluginHelpers();
        
        unset($helpers[$classtype][$namespace]);
        $helpers = $this->setPluginHelpers($helpers);
        $_SESSION['plugin-helper-list'] = $helpers;
        
        return true;
    }
    
    private function addDBHelper($options)
    {
        extract($options);
        
        $sql = "SELECT id FROM plugin_helper WHERE namespace = '{$namespace}' AND classtype = '{$classtype}'";
        $res = DBUtil::getOneResult($sql);

        // if record exists - ignore it.
        if (!empty($res)) {
            return true;
        }

        $values = array();
        $values['namespace'] = $namespace;
        $values['plugin'] = $plugin;
        $values['classname'] = $classname;
        $values['pathname'] = $pathname;
        $values['object'] = $object;
        $values['viewtype'] = $viewtype;
        $values['classtype'] = $classtype;

        // Insert into DB
        $res = DBUtil::autoInsert('plugin_helper', $values);
        if (PEAR::isError($res)) {
            return $res;
        }
        return true;
    }
    
    private function removeDBHelper($namespace, $classtype)
    {
        $where = array();
        $where['namespace'] = $namespace;
        $where['classtype'] = $classtype;
        $res = DBUtil::whereDelete('plugin_helper', $where);
        return $res;
    }
    
    private function clearDBHelpers()
    {
        $sql = 'DELETE FROM plugin_helper';
        DBUtil::runQuery($sql);
    }
}

?>