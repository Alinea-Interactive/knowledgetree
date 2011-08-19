<?php
/**
 * $Id$
 *
 * Template factory class
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

require_once(KT_LIB_DIR . '/templating/smartytemplate.inc.php');
require_once( KT_LIB_DIR . '/plugins/plugin.inc.php');

class KTTemplating {
    /** Templating language registry */
    var $aTemplateRegistry;

    /** Location registry */
    var $aLocationRegistry;

    // {{{ KTTemplating
    function KTTemplating() {
        $this->aTemplateRegistry = array(
            'smarty' => 'KTSmartyTemplate'
        );

        $this->aLocationRegistry = array(
            'core' => KT_DIR . '/templates'
        );
    }
    // }}}

    // {{{ _chooseTemplate
    function _chooseTemplate($templatename, $aPossibilities) {
        $aLocs = array_keys($aPossibilities);
        return $aPossibilities[$aLocs[count($aLocs) - 1]];
    }
    // }}}

    private function loadTemplateHelpers()
    {
        if (count($this->aLocationRegistry) > 1) {
            return ;
        }

        $helpers = KTPluginUtil::loadPluginHelpers('locations');

        foreach ($helpers as $helper) {
            extract($helper);
            $params = explode('|', $object);

            $path = str_replace('\\', '/', $params[1]);
            if (strpos($path, KT_DIR) === false) {
                $path = KT_DIR . '/' . $path;
            }

            $this->addLocation2($params[0], $path);
        }

    }

    // {{{ _findTemplate
    function _findTemplate($templateName)
    {
        $this->loadTemplateHelpers();

        $possibilities = array();

        foreach ($this->aLocationRegistry as $location => $path) {

            $path .= '/';

            $templateTypes = array_keys($this->aTemplateRegistry);
            foreach ($templateTypes as $suffix) {
                $fullPath = $path . $templateName . '.' .  $suffix;
                if (file_exists($fullPath)) {
                    $possibilities[$location] = array($suffix, $fullPath);
                }
            }
        }

        if (count($possibilities) === 0) {
            return PEAR::raiseError(_kt("No template found"));
        }

        return $this->_chooseTemplate($templateName, $possibilities);
    }
    // }}}

    // {{{ loadTemplate
    /**
     * Create an object that conforms to the template interface, using
     * the correct template system for the given template.
     *
     * KTI: Theoretically, this will do path searching in multiple
     * locations, allowing the user and possibly third-parties to
     * replace templates.
     */
    function &loadTemplate($templatename) {
        $res = $this->_findTemplate($templatename);
        if (PEAR::isError($res)) {
            return $res;
        }
        list($sLanguage, $sTemplatePath) = $res;
        $sClass = $this->aTemplateRegistry[$sLanguage];
        if (!class_exists($sClass)) {
            return PEAR::raiseError(_kt("Could not find template language"));
        }

        $oTemplate =new $sClass($sTemplatePath);
        return $oTemplate;
    }
    // }}}

    // {{{ addLocation
    /**
     * Register a new location in the database
     *
     * @param unknown_type $descr
     * @param unknown_type $loc
     */
    function addLocation ($description, $location, $pluginNamespace = NULL) {
        //$this->aLocationRegistry[$description] = $location;

        if(!empty($pluginNamespace)){
            $plugin = $pluginNamespace;
        }else{
            $plugin = $this->getPluginName();
            $plugin = (!empty($plugin)) ? $plugin : $description;
        }
        
        // Workaround for those plugins using /plugins/path - the fixFilename sets these to empty strings.
        if (strpos($location, '/plugins') === 0) {
            $location = substr($location, 1);
        }
        $location = KTPlugin::_fixFilename($location);

        KTPlugin::registerPluginHelper($plugin, $plugin, $location, $description.'|'.$location, 'general', 'locations');
    }
    // }}}

    function getPluginName(){
        $class = 'kttemplating';
        $function = 'addlocation';
        $function2 = 'setup';
        $bIsPlugin = false;
        $file = false;
        $plugin = false;

        $trace = debug_backtrace();

        if(empty($trace)){
            return '';
        }

        foreach($trace as $call){
            if(strtolower($call['class']) == $class && strtolower($call['function']) == $function){
                $file = $call['file'];
            }
            if($file && strtolower($call['function']) == $function2){
                $plugin = $call['class'];
            }
            if(strtolower($call['class']) == 'ktplugin' && strtolower($call['function']) == 'register'){
                $bIsPlugin = true;
                break;
            }
            if(strtolower($call['class']) == 'ktplugindispatcher' && strtolower($call['function']) == 'do_update'){
                $bIsPlugin = true;
                break;
            }
        }

        if($bIsPlugin && $file !== false && $plugin !== false){
            include_once($file);
            $oPlugin = new $plugin;
            $sPluginName = $oPlugin->sNamespace;

            if(!empty($sPluginName)){
                return $sPluginName;
            }
        }

        return '';
    }

    /**
     * Add the template location to the location registry
     *
     * @param string $description
     * @param string $location
     */
    function addLocation2 ($description, $location) {
        $this->aLocationRegistry[$description] = $location;
    }

    // {{{ getSingleton
    static function &getSingleton () {
		if (!KTUtil::arrayGet($GLOBALS['_KT_PLUGIN'], 'oKTTemplating')) {
			$GLOBALS['_KT_PLUGIN']['oKTTemplating'] = new KTTemplating;
		}
		return $GLOBALS['_KT_PLUGIN']['oKTTemplating'];
    }
    // }}}

    function renderTemplate($sTemplate, $aOptions) {
	$oTemplating =& KTTemplating::getSingleton();
	$oTemplate =& $oTemplating->loadTemplate($sTemplate);
	return $oTemplate->render($aOptions);
    }

}

?>
