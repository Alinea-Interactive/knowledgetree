<?php

/**
 * $Id$
 *
 * The contents of this file are subject to the KnowledgeTree Public
 * License Version 1.1.2 ("License"); You may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.knowledgetree.com/KPL
 * 
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied.
 * See the License for the specific language governing rights and
 * limitations under the License.
 *
 * All copies of the Covered Code must include on each user interface screen:
 *    (i) the "Powered by KnowledgeTree" logo and
 *    (ii) the KnowledgeTree copyright notice
 * in the same form as they appear in the distribution.  See the License for
 * requirements.
 * 
 * The Original Code is: KnowledgeTree Open Source
 * 
 * The Initial Developer of the Original Code is The Jam Warehouse Software
 * (Pty) Ltd, trading as KnowledgeTree.
 * Portions created by The Jam Warehouse Software (Pty) Ltd are Copyright
 * (C) 2007 The Jam Warehouse Software (Pty) Ltd;
 * All Rights Reserved.
 * Contributor( s): ______________________________________
 *
 */

require_once(KT_LIB_DIR . '/dispatcher.inc.php');
require_once(KT_LIB_DIR . "/util/sanitize.inc");

class KTInterceptor extends KTStandardDispatcher {
    var $sName;
    var $sNamespace;

    // Whether we can have multiple instances or not.  Default to yes.
    var $bSingleton = false;

    function KTInterceptor() {
        return parent::KTStandardDispatcher();
    }

    function configure($aInfo) {
        $this->aInfo = $aInfo;
    }

    function getName() {
        return sanitizeForSQLtoHTML($this->sName);
    }

    function getNamespace() {
        return $this->sNamespace;
    }

    /**
     * Return a user object if the authentication succeeds
     */
    function authenticated() {
        return null;
    }

    /**
     * Get an opportunity to take over the request.
     * Remember to exit if you take over.
     */
    function takeOver() {
        return null;
    }

    function loginWidgets() {
        return null;
    }

    function alternateLogin() {
        return null;
    }
}

class KTNoLocalUser extends PEAR_Error {
    function KTNoLocalUser($aExtra = null) {
        parent::PEAR_Error(_kt('No local user with that username'));
        $this->aExtra = $aExtra;
    }
}
