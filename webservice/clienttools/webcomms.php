<?php

include_once('../../ktapi/ktapi.inc.php');
error_reporting(E_ERROR);

// FIXME Should we not turn this off for production?
define('COMMS_DEBUG', true);
// Be careful altering this inside the services area - it should never be set to 0 as that could cause runaway processes
define('COMMS_TIMEOUT', 60 * 3);
set_time_limit(COMMS_TIMEOUT);

/**
 * Intercept Errors and Exceptions and provide a json response in return.
 * TODO: Make the json response 1. an object of its own and 2. versionable.
 *
 * @param unknown_type $errno
 * @param unknown_type $errstr
 * @param unknown_type $errfile
 * @param unknown_type $errline
 *
 * return json Error Response
 */
function error_handler($errno, $errstr = null, $errfile = null, $errline = null)
{
    $e = new ErrorException($errstr, 0, $errno, $errfile, $errline);
    if ($GLOBALS['RET']) {
        $GLOBALS['RET']->addError($e->getmessage());
        $GLOBALS['RET']->setDebug('Exception::', $e);
        echo $GLOBALS['RET']->getJson();
        exit;
    }
}

function exception_handler($e)
{
    if ($GLOBALS['RET']) {
        $GLOBALS['RET']->addError($e->getmessage());
        $GLOBALS['RET']->setDebug('Exception::', $e);
        echo $GLOBALS['RET']->getJson();
        exit;
    }
}

$oldErrorHandler = set_error_handler('error_handler', E_ERROR);
$oldExceptionHandler = set_exception_handler('exception_handler');

/**
 * Load additional generic libaries
 */
include_once('jsonWrapper.php');
include_once('webajaxhandler.php');
include_once('serviceHelper.php');
include_once('client_service.php');
include_once('clienttools_syslog.php');
include_once('requesthandler.php');

$ret = new jsonResponseObject();
if (isset($_GET['datasource'])) {
    $ret->isDataSource = true;
}

$kt = new KTAPI(3);
//$kt->get(3);// Set it to Use Web Version 3

$session = KTAPI_UserSession::getCurrentBrowserSession($kt);
if (PEAR::isError($session)) {
    $ret->addError('Not Logged In');
    echo $ret->getJson();
    exit;
}

$kt->start_system_session($session->user->getUserName());

// get the request (or package of requests)
$requestHandler = new requestHandler();
$requests = $requestHandler->getRequests();

foreach ($requests as $request) {
    $handler = new webAjaxHandler($request, $requestHandler->getRawRequest(), $ret, $kt);
    if (!$handler->hasErrors()) {
        $handler->dispatch();
    }

    ob_start();
    $handler->render();
    $output[] = ob_get_clean();
}

if (count($output) == 1) {
    echo $output[0];
}
else {
    // Chose ~|~ because it should *hopefully* be unique :)
    echo implode('~|~', $output);
}

?>
