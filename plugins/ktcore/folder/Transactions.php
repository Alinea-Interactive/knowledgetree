<?php
/**
 * $Id$
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
 *
 */

require_once(KT_LIB_DIR . '/actions/folderaction.inc.php');

require_once(KT_LIB_DIR . "/widgets/fieldsetDisplay.inc.php");
require_once(KT_LIB_DIR . "/widgets/FieldsetDisplayRegistry.inc.php");
require_once(KT_LIB_DIR . "/foldermanagement/folderutil.inc.php");
require_once(KT_LIB_DIR . "/documentmanagement/observers.inc.php");

require_once(KT_LIB_DIR . "/documentmanagement/documentutil.inc.php");

class KTFolderTransactionsAction extends KTFolderAction {
    var $sName = 'ktcore.actions.folder.transactions';
    var $_sShowPermission = "ktcore.permissions.folder_details";

    function getDisplayName() {
        return _kt('Folder transactions');
    }
    
    function do_main() {
        $this->oPage->setBreadcrumbDetails(_kt("transactions"));
        $this->oPage->setTitle(_kt('Folder transactions'));

        $folder_data = array();
        $folder_data["folder_id"] = $this->oFolder->getId();

        $this->oPage->setSecondaryTitle($this->oFolder->getName());

        $aTransactions = array();
        // FIXME do we really need to use a raw db-access here?  probably...
        $sQuery = "SELECT DTT.name AS transaction_name, U.name AS user_name, FT.comment AS comment, FT.datetime AS datetime " .
            "FROM " . KTUtil::getTableName("folder_transactions") . " AS FT LEFT JOIN " . KTUtil::getTableName("users") . " AS U ON FT.user_id = U.id " .
            "LEFT JOIN " . KTUtil::getTableName("transaction_types") . " AS DTT ON DTT.namespace = FT.transaction_namespace " .
            "WHERE FT.folder_id = ? ORDER BY FT.datetime DESC";
        $aParams = array($this->oFolder->getId());
        $res = DBUtil::getResultArray(array($sQuery, $aParams));
        if (PEAR::isError($res)) {
           var_dump($res); // FIXME be graceful on failure.
           exit(0);
        }

        // FIXME roll up view transactions
        $aTransactions = $res;

        // render pass.
        $this->oPage->title = _kt("Folder History");
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("kt3/view_folder_history");
        $aTemplateData = array(
              "context" => $this,
              "folder_id" => $folder_id,
              "folder" => $this->oFolder,
              "transactions" => $aTransactions,
        );
        return $oTemplate->render($aTemplateData);
    }


}

?>
