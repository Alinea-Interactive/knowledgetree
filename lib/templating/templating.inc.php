<?php
/**
 * $Id$
 *
 * Template factory class
 *
 * KnowledgeTree Open Source Edition
 * Document Management Made Simple
 * Copyright (C) 2004 - 2008 The Jam Warehouse Software (Pty) Limited
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
 * You can contact The Jam Warehouse Software (Pty) Limited, Unit 1, Tramber Place,
 * Blake Street, Observatory, 7925 South Africa. or email info@knowledgetree.com.
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

require_once(KT_LIB_DIR . "/templating/smartytemplate.inc.php");

class KTTemplating {
    /** Templating language registry */
    var $aTemplateRegistry;

    /** Location registry */
    var $aLocationRegistry;

    // {{{ KTTemplating
    function KTTemplating() {
        $this->aTemplateRegistry = array(
            "smarty" => "KTSmartyTemplate",
        );

        $this->aLocationRegistry = array(
            "core" => "templates",
        );
    }
    // }}}

    // {{{ _chooseTemplate
    function _chooseTemplate($templatename, $aPossibilities) {
        $aLocs = array_keys($aPossibilities);
        return $aPossibilities[$aLocs[count($aLocs) - 1]];
    }
    // }}}

    // {{{ _findTemplate
    function _findTemplate($templatename) {
        $aPossibilities = array();

        foreach ($this->aLocationRegistry as $loc => $path) {
            if (KTUtil::isAbsolutePath($path)) {
                $fulldirectory = $path . "/";
                foreach (array_keys($this->aTemplateRegistry) as $suffix) {
                    $fullpath = $fulldirectory . $templatename . "." .  $suffix;
                    if (file_exists($fullpath)) {
                        $aPossibilities[$loc] = array($suffix, $fullpath);
                    }
                }
            }
            $fulldirectory = KT_DIR . "/" . $path . "/";
            foreach (array_keys($this->aTemplateRegistry) as $suffix) {
                $fullpath = $fulldirectory . $templatename . "." .  $suffix;
                if (file_exists($fullpath)) {
                    $aPossibilities[$loc] = array($suffix, $fullpath);
                }
            }
        }

        if (count($aPossibilities) === 0) {
            return PEAR::raiseError(_kt("No template found"));
        }

        return $this->_chooseTemplate($templatename, $aPossibilities);
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
    function addLocation ($descr, $loc) {
        $this->aLocationRegistry[$descr] = $loc;
        KTPlugin::registerPluginHelper($descr, $descr, $loc, $descr.'|'.$loc, 'general', 'locations');
    }
    // }}}

    /**
     * Add the template location to the location registry
     *
     * @param unknown_type $descr
     * @param unknown_type $loc
     */
    function addLocation2 ($descr, $loc) {
        $this->aLocationRegistry[$descr] = $loc;
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
