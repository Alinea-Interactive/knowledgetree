<?php

/**
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

require_once(KT_LIB_DIR . '/actions/dashboardviewlet.inc.php');
require_once(KT_LIB_DIR . '/util/ktutil.inc');

require_once(KT_DIR . '/plugins/ktcore/KTDocumentViewlets.php');

require_once(KT_PLUGIN_DIR . '/GraphicalAnalytics/GraphicalAnalytics.php');

class KTDashboardActivityFeedViewlet extends KTDashboardViewlet {

    private $documentActivityFeedAction;
    private $start = 0;
    private $limit = 10;
    private $displayMax = 20;
    private $preloaded = 0;

    public $sName = 'ktcore.viewlet.dashboard.activityfeed';
    public $bShowIfReadShared = true;
    public $bShowIfWriteShared = true;
    public $order = 2;

    public function __construct($user = null, $plugin = null)
    {
        parent::__construct($user, $plugin);
        $this->documentActivityFeedAction = new KTDocumentActivityFeedAction();
    }

    public function setLimits($preloaded = 0, $start = 0)
    {
        $this->preloaded = $preloaded;
        $this->start = $start;
    }

    public function getCSSName()
    {
        return 'activityfeed';
    }

    public function displayViewlet()
    {
        // FIXME There is some duplication here.
        //       The mime icon stuff for instance can be abstracted to
        //       a third file and used both here and in the browse view.

        $transactions = $this->getTransactions();
        $comments = $this->getComments();

        $activityFeed = array_merge($transactions, $comments);
        $activityFeed = $this->setMimeIcons($activityFeed);

        usort($activityFeed, array($this->documentActivityFeedAction, 'sortTable'));

        $templating =& KTTemplating::getSingleton();
        $template = $templating->loadTemplate('ktcore/dashboard/viewlets/global_activity_feed_content');
        $templateData = array(
            'context' => $this,
            'documentId' => $documentId,
            'versions' => $activityFeed,
            'displayMax' => $this->displayMax,
            'commentsCount' => $transactionCount + $commentCount,
            'preloaded' => $this->preloaded + count($activityFeed),
            'nextBatch' => $this->start + $this->limit
        );

        $activityFeedContent = $template->render($templateData);

        if ($this->preloaded > 0) {
            return $activityFeedContent;
        }

        $template = $templating->loadTemplate('ktcore/dashboard/viewlets/global_activity_feed');
        $templateData = array('activityFeed' => $activityFeedContent);

        return $template->render($templateData);
    }

    private function getTransactions()
    {
        $transactions = array();

        $filter = array(
            'ktcore.transactions.create',
            'ktcore.transactions.delete',
            'ktcore.transactions.check_in'
        );

        $transactionCount = $this->getTransactionCount($filter);

        if ($transactionCount > 0) {
            $transactions = $this->documentActivityFeedAction->getActivityFeed($this->getAllTransactions($filter));
        }

        return $transactions;
    }

    private function getTransactionCount($filter = array())
    {
        $query = "SELECT count(DT.id) as transactions
            FROM " . KTUtil::getTableName('document_transactions') . " AS DT
            INNER JOIN " . KTUtil::getTableName('users') . " AS U ON DT.user_id = U.id
            LEFT JOIN " . KTUtil::getTableName('transaction_types') . "
            AS DTT ON DTT.namespace = DT.transaction_namespace,
            documents D
            INNER JOIN document_metadata_version DMV ON DMV.id = D.metadata_version_id
            INNER JOIN document_content_version DCV ON DCV.id = DMV.content_version_id
            {$this->getPermissionsQuery()}
            DT.transaction_namespace != 'ktcore.transactions.view'
            {$this->buildFilterQuery($filter)}
            AND DT.document_id = D.id";

        $res = DBUtil::getOneResult($query);
        if (PEAR::isError($res)) {
            global $default;
            $default->log->error('Error getting the transactions - ' . $res->getMessage());
            $res = array();
        }

        return $res['transactions'];
    }

    private function getAllTransactions($filter = array(), $start = 0)
    {
        $query = "SELECT D.id as document_id, DMV.name as document_name,
            DCV.mime_id,
            DTT.name AS transaction_name, DT.transaction_namespace,
            U.name AS user_name, U.email as email,
            DT.version AS version, DT.comment AS comment, DT.datetime AS datetime
            FROM " . KTUtil::getTableName('document_transactions') . " AS DT
            INNER JOIN " . KTUtil::getTableName('users') . " AS U ON DT.user_id = U.id
            LEFT JOIN " . KTUtil::getTableName('transaction_types') . "
            AS DTT ON DTT.namespace = DT.transaction_namespace,
            documents D
            INNER JOIN document_metadata_version DMV ON DMV.id = D.metadata_version_id
            INNER JOIN document_content_version DCV ON DCV.id = DMV.content_version_id
            {$this->getPermissionsQuery()}
            DT.transaction_namespace != 'ktcore.transactions.view'
            {$this->buildFilterQuery($filter)}
            AND DT.document_id = D.id
            ORDER BY DT.id DESC
            LIMIT {$this->start}, {$this->limit}";

        return $this->documentActivityFeedAction->getTransactionResult(array($query));
    }

    // FIXME Lots of duplication, see comments plugin.
    public function getPermissionsQuery()
    {
        if ($this->inAdminMode()) {
            return 'WHERE';
        }
        else {
            $user = User::get($_SESSION['userID']);
            $permission = KTPermission::getByName('ktcore.permissions.read');
            $permId = $permission->getID();
            $permissionDescriptors = KTPermissionUtil::getPermissionDescriptorsForUser($user);
            $permissionDescriptors = empty($permissionDescriptors) ? -1 : implode(',', $permissionDescriptors);

            $query = "INNER JOIN permission_lookups AS PL ON D.permission_lookup_id = PL.id
                INNER JOIN permission_lookup_assignments AS PLA ON PL.id = PLA.permission_lookup_id
                AND PLA.permission_id = $permId
                WHERE PLA.permission_descriptor_id IN ($permissionDescriptors) AND";

            return $query;
        }
    }

    private function inAdminMode()
    {
        return isset($_SESSION['adminmode'])
            && ((int)$_SESSION['adminmode'])
            && Permission::adminIsInAdminMode();
    }

    private function buildFilterQuery($filter = array())
    {
        $filterQuery = '';

        if (!empty($filter)) {
            foreach ($filter as $namespace) {
                $filterQueries[] = "DT.transaction_namespace = '$namespace'";
            }
            $filterQuery = 'AND (' . implode(' OR ', $filterQueries) . ')';
        }

        return $filterQuery;
    }

    private function getComments()
    {
        $comments = array();

        $commentCount = $this->getCommentCount();
        if ($commentCount > 0) {
            $comments = $this->getAllComments();
        }

        return $comments;
    }

    private function getCommentCount()
    {
        $comments = 0;

        try {
            $comments = Comments::getCommentCount();
        }
        catch (Exception $e) {
            global $default;
            $default->log->error('Error getting the comments - ' . $e->getMessage());
            $comments = 0;
        }

        return $comments;
    }

    private function getAllComments()
    {
        $comments = array();

        try {
            $result = Comments::getAllComments('DESC', array($this->start, $this->limit));
            $comments = $this->documentActivityFeedAction->formatCommentsResult($result);
        }
        catch (Exception $e) {
            global $default;
            $default->log->error('Error getting the comments - ' . $e->getMessage());
            $comments = array();
        }

        return $comments;
    }

    private function getTemplateName()
    {
        $prefix = 'ktcore/dashboard/viewlets';
        return "$prefix/global_activity_feed";
        // return $this->preloaded > 0 ? "$prefix/global_activity_feed" : "$prefix/global_activity_feed_ajax";
    }

    private function setMimeIcons($activityFeed)
    {
        foreach ($activityFeed as $key => $item) {
            $iconFile = 'resources/mimetypes/newui/' . KTMime::getIconPath($item['mime_id']) . '.png';
            $item['icon_exists'] = file_exists(KT_DIR . '/' . $iconFile);
            $item['icon_file'] = $iconFile;

            if ($item['icon_exists']) {
                $item['mimeicon'] = str_replace('\\', '/', $GLOBALS['default']->rootUrl . '/' . $iconFile);
                $item['mimeicon'] = 'background-image: url(' . $item['mimeicon'] . ')';
            }
            else {
                $item['mimeicon'] = '';
            }

            $activityFeed[$key] = $item;
        }

        return $activityFeed;
    }

}

class KTGraphicalAnalyticsViewlet extends KTDashboardViewlet {

    public $sName = 'ktcore.viewlet.dashboard.analytics';
    public $bShowIfReadShared = true;
    public $bShowIfWriteShared = true;
    public $order = 1;

    public function getCSSName()
    {
        return 'graphicalanalytics';
    }

    public function displayViewlet()
    {
        $ktAnalytics = new GraphicalAnalytics();

        $templateData = array(
               'context' => $this,
               'userAccessPerWeek' => $ktAnalytics->getUserAccessPerWeekDashlet(),
               'uploadsPerWeek' => $ktAnalytics->getUploadsPerWeekDashlet(),
               'documentRating' => $ktAnalytics->getDocumentsByRatingTemplate(true), // true for Dashlet
               'topFiveDocuments' => $ktAnalytics->getTop5DocumentsDashlet(),
               'topFiveUsers' => $ktAnalytics->getTop5UsersDashlet(),
               'mostViewedDocuments' => $ktAnalytics->getMostViewedDocumentsDashlet(),
        );

        $templating = KTTemplating::getSingleton();
        $template = $templating->loadTemplate('ktcore/dashboard/viewlets/graphical_analytics');

        return $template->render($templateData);
    }

}

?>