<?php
/**
 * $Id: $
 *
 * The contents of this file are subject to the KnowledgeTree
 * Commercial Editions On-Premise License ("License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.knowledgetree.com/about/legal/
 * The terms of this license may change from time to time and the latest
 * license will be published from time to time at the above Internet address.
 *
 * This edition of the KnowledgeTree software
 * is NOT licensed to you under Open Source terms.
 * You may not redistribute this source code.
 * For more information please see the License above.
 *
 * (c) 2008, 2009, 2010 KnowledgeTree Inc.
 * All Rights Reserved.
 *
 */
/**
 * Load simple event modelling class queueEvent
 * Load complex even modelling class queueProcess
 */
require_once(realpath(dirname(__FILE__) . '/../queueProcess.php'));
require_once(realpath(dirname(__FILE__) . '/../queueEvent.php'));

class indexingProcess extends queueProcess {
	/**
	 * List of events
	 * @var array
	 */
	public $list_of_events = array(
									'index' => 'Indexer.run',
									'metadataInserter' => 'MetadataInserter.run',
									);
	/**
	 * List of callbacks
	 * @var array
	 */
	public $callbacks = array();
	
    /**
    * Construct document indexing process
    *
    * @author KnowledgeTree Team
    * @access public
    * @param none
    * @return
    */
	public function __construct() {
		parent::setName('indexing');
	}
	
    /**
    * Load list of events to process
    *
    * @author KnowledgeTree Team
    * @access public
    * @param none
    * @return none
    */
	public function loadEvents() {
		parent::setListOfEvents($this->list_of_events);
	}
	
    /**
    * Load list of callbacks to process
    *
    * @author KnowledgeTree Team
    * @access public
    * @param none
    * @return none
    */
	public function loadCallbacks() {
		global $default;
		$server = 'http://' . $default->serverName . ':'  . $default->server_port . '' . $default->rootUrl;
		$server = $this->callback_type . $server . $this->callback_script . '?' . $this->callback_options;
		$done = $server;
		$onQueueNextEvent = $server;
		$onReturnEvent = $server;
		$onReturnEventFailure = $server;
		$onReturnEventSuccess = $server;
		$callbacks = array(
								'done' => $done,
								'onQueueNextEvent' => $onQueueNextEvent,
								'onReturnEvent' => $onReturnEvent,
								'onReturnEventFailure' => $onReturnEventFailure,
								'onReturnEventSuccess' => $onReturnEventSuccess,
		);
		$callbacks = array();
		parent::setListOfCallbacks($callbacks);
	}
}
?>