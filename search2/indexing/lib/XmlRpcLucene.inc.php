<?php

/**
 * $Id:$
 *
 * KnowledgeTree Open Source Edition
 * Document Management Made Simple
 * Copyright (C) 2004 - 2007 The Jam Warehouse Software (Pty) Limited
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

require_once('xmlrpc.inc');

class XmlRpcLucene
{
	/**
	 * Reference to the xmlrpc client
	 *
	 * @var xmlrpc_client
	 */
	var $client;

	/**
	 * Identifier for the KT instance
	 *
	 * @var string
	 */
	var $ktid;

	/**
	 * Identifier for the lucene server
	 *
	 * @var string
	 */
	var $authToken;

	/**
	 * The constructoor for the lucene XMLRPC client.
	 *
	 * @param string $url
	 * @param int $port
	 */
	public function __construct($url)
	{
		$this->client=new xmlrpc_client("$url/xmlrpc");
		$this->client->request_charset_encoding = 'UTF-8';
		$GLOBALS['xmlrpc_internalencoding'] = 'UTF-8';

		$config = KTConfig::getSingleton();
		$this->authToken = $config->get('indexer/luceneAuthToken','');
		$this->ktid = $config->get('indexer/luceneID','');
	}

	/**
	 * Set a level for debugging.
	 *
	 * @param int $level
	 */
	function debug($level)
	{
		$this->client->setDebug($level);
	}

	/**
	 * Logs errors to the log file
	 *
	 * @param xmlrpcresult $result
	 * @param string $function
	 */
	function error($result, $function)
	{
		global $default;
		$default->log->error('XMLRPC Lucene - ' . $function . ' - Code: ' . htmlspecialchars($result->faultCode()));
		$default->log->error('XMLRPC Lucene - ' . $function . ' - Reason: ' . htmlspecialchars($result->faultString()));
	}

	/**
	 * Optimise the lucene index.
	 *
	 * @return boolean
	 */
	function optimise()
	{
		$function=new xmlrpcmsg('indexer.optimise',
			array(
				php_xmlrpc_encode((string) $this->ktid),
				php_xmlrpc_encode((string) $this->authToken)
			));

		$result=&$this->client->send($function);
		if($result->faultCode())
		{
			$this->error($result, 'optimise');
			return false;
		}
		return php_xmlrpc_decode($result->value()) == 0;
	}

	/**
	 * Add a document to lucene
	 *
	 * @param int $documentid
	 * @param string $contentFile
	 * @param string $discussion
	 * @param string $title
	 * @param string $version
	 * @return boolean
	 */
	function addDocument($documentid, $contentFile, $discussion, $title, $version)
	{
		$function=new xmlrpcmsg('indexer.addDocument',
			array(
				php_xmlrpc_encode((string) $this->ktid),
				php_xmlrpc_encode((string) $this->authToken),
				php_xmlrpc_encode((int) $documentid),
				php_xmlrpc_encode((string) $contentFile),
				php_xmlrpc_encode((string) $discussion),
				php_xmlrpc_encode((string) $title),
				php_xmlrpc_encode((string) $version)
			));

		$result=&$this->client->send($function);
		if($result->faultCode())
		{
			$this->error($result, 'addDocument');
			return false;
		}
		return php_xmlrpc_decode($result->value()) == 0;
	}

	/**
	 * Remove the document from the index.
	 *
	 * @param int $documentid
	 * @return boolean
	 */
	function deleteDocument($documentid)
	{
		$function=new xmlrpcmsg('indexer.deleteDocument',array(
				php_xmlrpc_encode((string) $this->ktid),
				php_xmlrpc_encode((string) $this->authToken),
				php_xmlrpc_encode((int) $documentid)));

		$result=&$this->client->send($function);
		if($result->faultCode())
		{
			$this->error($result, 'deleteDocument');
			return false;
		}
		return php_xmlrpc_decode($result->value()) == 0;
	}

	/**
	 * Does the document exist?
	 *
	 * @param int $documentid
	 * @return boolean
	 */
	function documentExists($documentid)
	{
		$function=new xmlrpcmsg('indexer.documentExists',array(
				php_xmlrpc_encode((string) $this->ktid),
				php_xmlrpc_encode((string) $this->authToken),
				php_xmlrpc_encode((int) $documentid)));

		$result=&$this->client->send($function);
		if($result->faultCode())
		{
			$this->error($result, 'deleteDocument');
			return false;
		}
		return php_xmlrpc_decode($result->value());
	}

	/**
	 * Get statistics from the indexer
	 *
	 * @return array
	 */
	function getStatistics()
	{
		$function=new xmlrpcmsg('indexer.getStatistics',array(
				php_xmlrpc_encode((string) $this->ktid),
				php_xmlrpc_encode((string) $this->authToken)));


		$result=&$this->client->send($function);
		if($result->faultCode())
		{
			$this->error($result, 'getStatistics');
			return false;
		}

		$result = php_xmlrpc_decode($result->value());

		//print $result;

		return json_decode($result);
	}

	/**
	 * Run a query on the lucene index
	 *
	 * @param string $query
	 * @return boolean
	 */
	function query($query)
	{
		$function=new xmlrpcmsg('indexer.query',array(
				php_xmlrpc_encode((string) $this->ktid),
				php_xmlrpc_encode((string) $this->authToken),
				php_xmlrpc_encode((string) $query)));

		$result=&$this->client->send($function);
		if($result->faultCode())
		{
			$this->error($result, 'query');
			return false;
		}

		$result = php_xmlrpc_decode($result->value());
		return json_decode($result);
	}

	/**
	 * Updates the discussion text on a given document.
	 *
	 * @param int $docid
	 * @param string $discussion
	 * @return boolean
	 */
	function updateDiscussion($docid, $discussion)
	{
		$function=new xmlrpcmsg('indexer.updateDiscussion',array(
				php_xmlrpc_encode((string) $this->ktid),
				php_xmlrpc_encode((string) $this->authToken),
				php_xmlrpc_encode((int) $docid),
				php_xmlrpc_encode((string) $discussion)));

		$result=&$this->client->send($function);
		if($result->faultCode())
		{
			$this->error($result, 'updateDiscussion');
			return false;
		}
		return php_xmlrpc_decode($result->value()) == 0;
	}

	function shutdown()
	{
		$function=new xmlrpcmsg('indexer.shutdown',array(
				php_xmlrpc_encode((string) $this->ktid),
				php_xmlrpc_encode((string) $this->authToken)));

		$result=&$this->client->send($function);
		if($result->faultCode())
		{
			$this->error($result, 'shutdown');
			return false;
		}
		return true;
	}


}

?>