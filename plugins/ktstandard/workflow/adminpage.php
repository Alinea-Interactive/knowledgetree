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

require_once(KT_LIB_DIR . "/templating/templating.inc.php");
require_once(KT_LIB_DIR . "/dispatcher.inc.php");

require_once(KT_LIB_DIR . '/widgets/fieldWidgets.php');

class WorkflowAllocationSelection extends KTAdminDispatcher {

    var $sHelpPage = 'ktcore/admin/automatic workflows.html';
    var $bAutomaticTransaction = true;
    var $sSection = 'administration';

    function check()
    {
        $res = parent::check();
        if (!$res) {
            return false;
        }

        //$this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name'=> _kt('Automatic Workflow Assignments'));

        return true;
    }

    function do_main()
    {
        $oKTTriggerRegistry = KTTriggerRegistry::getSingleton();
        $aTriggers = $oKTTriggerRegistry->getTriggers('workflow', 'objectModification');

        $aFields = array();
        $aVocab = array();
        $aVocab[] = _kt('No automatic assignment');
        foreach ($aTriggers as $aTrigger) {
            $aVocab[$aTrigger[2]] = $aTrigger[0];
        }
        $aFields[] = new KTLookupWidget(_kt('Workflow Plugins'), _kt('Plugins providing workflow allocators.'),'selection_ns', $this->getHandler(), $this->oPage, true, null, null, array('vocab' => $aVocab));

        $oTemplate =& $this->oValidator->validateTemplate('ktstandard/workflow/allocator_selection');
        $oTemplate->setData(array(
            'context' => $this,
            'trigger_fields' => $aFields,
        ));

        return $oTemplate->render();
    }

    function getHandler()
    {
        $sQuery = 'SELECT selection_ns FROM ' . KTUtil::getTableName('trigger_selection');
        $sQuery .= ' WHERE event_ns = ?';
        $aParams = array('ktstandard.workflowassociation.handler');
        $res = DBUtil::getOneResultKey(array($sQuery, $aParams), 'selection_ns');

        return $res;
    }

    public function do_assign_handler()
    {
        $KTTriggerRegistry = KTTriggerRegistry::getSingleton();
        $triggers = $KTTriggerRegistry->getTriggers('workflow', 'objectModification');

        $selectionNamespace = KTUtil::arrayGet($_REQUEST, 'selection_ns');
        if (empty($selectionNamespace)) {
            $query = 'DELETE FROM ' . KTUtil::getTableName('trigger_selection');
            $query .= ' WHERE event_ns = ?';
            $params = array('ktstandard.workflowassociation.handler');
            DBUtil::runQuery(array($query, $params));
            
            $this->updateTriggerHandlers($triggers);
            $this->successRedirectToMain(_kt('Handler removed.'));
        }
        else {
            if (!array_key_exists($selectionNamespace, $triggers)) {
                $this->errorRedirectToMain(_kt('Invalid assignment'));
            }

            // clear
            $query = 'DELETE FROM ' . KTUtil::getTableName('trigger_selection');
            $query .= ' WHERE event_ns = ?';
            $params = array('ktstandard.workflowassociation.handler');
            DBUtil::runQuery(array($query, $params));

            // set
            $query = 'INSERT INTO ' . KTUtil::getTableName('trigger_selection');
            $query .= ' (event_ns, selection_ns)';
            $query .= ' VALUES ("ktstandard.workflowassociation.handler",?)';
            $params = array($selectionNamespace);

            DBUtil::runQuery(array($query, $params));
            
            $this->updateTriggerHandlers($triggers);
            $this->successRedirectToMain(_kt('Handler set.'));
        }
    }
    
    private function updateTriggerHandlers($triggers)
    {
        foreach ($triggers as $trigger) {
            $class = $trigger[0];
            
            if (!class_exists($class)) {
                include_once($trigger[1]);
            }
            
            $handler = new $class();
            $handler->setAssociation();
        }
        
        $pluginCache = PluginCache::getPluginCache();
        $pluginCache->clearPluginSession();
    }

    public function handleOutput($output)
    {
        print $output;
    }

}

?>
