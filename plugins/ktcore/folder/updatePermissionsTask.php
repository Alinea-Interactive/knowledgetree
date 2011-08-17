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

$folderId = $argv[1];
$accountName = isset($argv[2]) ? $argv[2] : '';

if (!empty($accountName)) {
	define('ACCOUNT_ROUTING_ENABLED', true);
	define('ACCOUNT_NAME', $accountName);
}

$dir = dirname(__FILE__);
require_once($dir . '/../../../config/dmsDefaults.php');
require_once(KT_LIB_DIR . '/permissions/BackgroundPermissions.php');

// set errors and time out after dmsDefaults to prevent being overridden
set_time_limit(0);
error_reporting(E_ERROR | E_CORE_ERROR);

$updateTask = new BackgroundPermissions($folderId, $accountName);

register_shutdown_function(array($updateTask, 'handleShutdown'));

if (function_exists('pcntl_signal')) {

	declare(ticks=1);
	
	pcntl_signal(SIGHUP, array($updateTask, 'handleInterrupt'));
    pcntl_signal(SIGINT, array($updateTask, 'handleInterrupt'));
    pcntl_signal(SIGQUIT, array($updateTask, 'handleInterrupt'));
    pcntl_signal(SIGABRT, array($updateTask, 'handleInterrupt'));
    pcntl_signal(SIGTERM, array($updateTask, 'handleInterrupt'));
}

$updateTask->updatePermissions();

exit(0);
?>