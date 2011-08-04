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

require_once(KT_LIB_DIR . "/actions/documentviewlet.inc.php");
require_once(KT_LIB_DIR . "/workflow/workflowutil.inc.php");
require_once(KT_LIB_DIR . '/actions/documentaction.inc.php');
require_once(KT_LIB_DIR . '/subscriptions/Subscription.inc');
require_once(KT_LIB_DIR . '/subscriptions/subscriptions.inc.php');

// TODO : Give each document action loaded the ability to show and hide.

class KTDocumentStatusBlock extends KTDocumentViewlet {
    public $sName = 'ktcore.blocks.document.status';
	public $_sShowPermission = 'ktcore.permissions.read';

	public function setShowBlock($showBlock)
	{
		$this->showBlock = $showBlock;
	}

    public function getInfo()
    {
    	if (parent::getInfo() == '') {
    		return null;
    	}
		$this->oPage->requireJSResource('resources/js/newui/documents/blocks/workflowsActions.js');
        if ($this->_show() === false) {
            return null;
        }

        return true;
    }

	/**
	 * Create an actions block
	 *
	 * @return string
	 */
	public function getDocBlock($wrapper = true)
	{
		$this->oPage->requireCSSResource('resources/css/newui/documents/blocks/blockActions.css');
		$this->oPage->requireJSResource('resources/js/newui/documents/blocks/subscriptionsBlock.js');
		$workflowState = $alertState = $subscribeState = 'disabled';
        // Check if document has workflows
        if ($this->hasWorkflow()) { $workflowState = 'enabled'; }
        // Check if user is subscribed
        if ($this->hasSubscriptions()) { $subscribeState = 'enabled'; }
        // Check if document has alerts
        if($this->hasAlerts()) { $alertState = 'enabled'; }

		$templating = KTTemplating::getSingleton();
		$template = $templating->loadTemplate('ktcore/document/blocks/viewActions');
        $templateData = array(
              'context' => $this,
              'workflowState' => $workflowState,
              'alertState' => $alertState,
              'subscribeState' => $subscribeState,
              'documentId' => $this->oDocument->getId(),
              'wrapper' => $wrapper,
        );

        return $template->render($templateData);
	}

	/**
	 * Return content only.
	 *
	 */
	public function do_ajaxGetDocBlock()
	{
		echo $this->getDocBlock(false);
		exit(0);
	}

	/**
	 * Check if the document in context has alerts
	 *
	 * @return boolean
	 */
	private function hasAlerts()
	{
		$now = date('Y-m-d H:i:s');
		$query = "SELECT id, alert_date FROM document_alerts WHERE document_id = {$this->oDocument->getId()} AND alert_date > '$now' LIMIT 1";

        $results = DBUtil::getResultArray($query);
        return !empty($results);
	}

	/**
	 * Check if the document in context is in transition
	 *
	 * @return boolean
	 */
	private function hasWorkflow()
	{
		$result = KTWorkflowUtil::getTransitionsForDocumentUser($this->oDocument, $this->oUser);
		return !empty($result);
	}

	/**
	 * Check if the document in context has subscriptions
	 *
	 * @return boolean
	 */
	private function hasSubscriptions()
	{
        return Subscription::exists($this->oUser->getId(), $this->oDocument->getId(), SubscriptionEvent::subTypes('Document'));
	}
}
?>