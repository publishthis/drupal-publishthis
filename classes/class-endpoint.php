<?php

/**
 * This handles the input and response for the Publishing Endpoints from
 * the PublishThis platform
 * Current Actions
 * 1 - Verify
 * 2 - Publish
 */
class Publishthis_Endpoint {
  private $obj_api;
  private $obj_publish;

  function __construct() {
	$this->obj_api     = new Publishthis_API();
	$this->obj_publish = new Publishthis_Publish();
  }

  /**
   * Escape sprecial characters
   */
  function escapeJsonString($value) { // list from www.json.org: (\b backspace, \f formfeed)
	$escapers     = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
	$replacements = array(
	  "\\\\",
	  "\\/",
	  "\\\"",
	  "\\n",
	  "\\r",
	  "\\t",
	  "\\f",
	  "\\b"
	);
	$result       = str_replace($escapers, $replacements, $value);
	$escapers     = array('\":\"', '\",\"', '{\"', '\"}');
	$replacements = array('":"', '","', '{"', '"}');
	$result       = str_replace($escapers, $replacements, $result);
	return $result;
  }

  /**
   * Returns json response with failed status
   */
  function sendFailure($message) {
	$obj = NULL;

	$obj->success      = FALSE;
	$obj->errorMessage = $this->escapeJsonString($message);

	$this->sendJSON($obj);
  }

  /**
   * Returns json response with succeess status
   */
  function sendSuccess($message) {
	$obj = NULL;

	$obj->success      = TRUE;
	$obj->errorMessage = NULL;

	$this->sendJSON($obj);
  }

  /*
  * Send object in JSON format
  */
  private function sendJSON($obj) {
	header('Content-Type: application/json');
	echo json_encode($obj);
  }

  /**
   * Verify endpoint action
   */
  private function actionVerify() {
	//first check to make sure we have our api token
	$apiToken = $this->obj_api->_get_token('api_token');

	if (empty($apiToken)) {

	  $message = array(
		'message' => 'Verify Plugin Endpoint',
		'status'  => 'error',
		'details' => 'Asked to verify our install at: ' . date("Y-m-d H:i:s") . ' failed because api token is not filled out'
	  );
	  $this->obj_api->_log_message($message, "1");

	  $this->sendFailure("No API Key Entered");
	  return;
	}

	//then, make a easy call to our api that should return our basic info.
	$apiResponse = $this->obj_api->get_client_info();

	if (empty($apiResponse)) {
	  $message = array(
		'message' => 'Verify Plugin Endpoint',
		'status'  => 'error',
		'details' => 'Asked to verify our install at: ' . date("Y-m-d H:i:s") . ' failed because api token is not valid'
	  );
	  $this->obj_api->_log_message($message, "1");

	  $this->sendFailure("API Key Entered is not Valid");
	  return;
	}

	//if we got here, then it is a valid api token, and the plugin is installed.

	$message = array(
	  'message' => 'Verify Plugin Endpoint',
	  'status'  => 'info',
	  'details' => 'Asked to verify our install at: ' . date("Y-m-d H:i:s")
	);
	$this->obj_api->_log_message($message, "2");

	$this->sendSuccess("");
  }

  /**
   * Publish endpoint action
   * we get the information and then publish the feed
   * here is the info being passed right now
   * action: "publish",
   * feedId: 123,
   * templateId: 456,
   * clientId: 789,
   * userId: 21,
   * publishDate: Date
   *
   * @param integer $feedId
   */
  private function actionPublish($feedId) {

	if (empty($feedId)) {
	  $this->sendFailure("Empty feed id");
	  return;
	}

	$arrFeeds   = array();
	$arrFeeds[] = $feedId;

	//ok, now go try and publish the feed passed in

	try {
	  $this->obj_publish->publish_specific_feeds($arrFeeds);
	} catch (Exception $ex) {
	  //looks like there was an internal error in publish, we will need to send a failure.
	  //no need to log here, as our internal methods have all ready logged it

	  $this->sendFailure($ex->getMessage());
	  return;
	}

	$this->sendSuccess("published");
	return;
  }

  private function array_values_recursive($arr) {
	$arr = array_values($arr);
	foreach ($arr as $key => $val) {
	  if (array_values($val['subcategories']) !== $val['subcategories']) {
		$arr[$key]['subcategories'] = $this->array_values_recursive($val['subcategories']);
	  }
	}

	return $arr;
  }

  private function actionGetAuthors() {
	$authors = array();
	$obj     = new stdClass();

	$users  = entity_load('user');
	$emails = '';
	foreach ($users as $user) {
	  if (array_key_exists(3, $user->roles)) {
		if (strlen($emails) > 0) {
		  $emails .= ' ' . $user->mail;
		}
		else {
		  $emails = $user->mail;
		}
	  }
	}

	foreach ($users as $user) {
	  $authors[] = array('id' => $user->ID, 'name' => $user->display_name);
	}

	$obj->success      = TRUE;
	$obj->errorMessage = NULL;
	$obj->authors      = $authors;

	$this->sendJSON($obj);
  }

  private function actionGetCategories() {
	$categories = array();
	$terms = taxonomy_get_tree(1);
	foreach ( $terms as $term ) {
	  $category = array(
		'id' => intval( $term->tid ),
		'name' => $term->name,
		'taxonomyId' => intval( $term->tid ),
		'taxonomyName' => $term->name,
		'subcategories' => array() );
	  $categories[ $term->tid ] = $category;
	}
	$obj               = new stdClass();
	$obj->success      = TRUE;
	$obj->errorMessage = NULL;
	$obj->categories   = $this->array_values_recursive( $categories );
	$this->sendJSON($obj);
  }

  /**
   * Process request main function
   */
  function process_request() {
	global $pt_settings_value;

	try {
	  $bodyContent = file_get_contents('php://input');

	  $this->obj_api->_log_message(array(
		'message' => 'Endpoint Request',
		'status'  => 'info',
		'details' => $bodyContent
	  ), "2");

	  $arrEndPoint = json_decode($bodyContent, TRUE);
	  $action      = $arrEndPoint["action"];

	  switch ($action) {
		case "verify":
		  $this->actionVerify();
		  break;

		case "publish":
		  if ($pt_settings_value['curated_publish'] != 'publishthis_import_from_manager') {
			$this->sendFailure("Publishing through CMS is disabled");
			return;
		  }
		  $feedId = intval($arrEndPoint["feedId"], 10);
		  $this->actionPublish($feedId);
		  break;
		case "getAuthors":
		  $this->actionGetAuthors();
		  break;
		case "getCategories":
		  $this->actionGetCategories();
		  break;


		default:
		  $this->sendFailure("Empty or bad request made to endpoint");
		  break;
	  }

	} catch (Exception $ex) {
	  //we will log this to the pt logger, but we always need to send back a failure if this occurs

	  $this->sendFailure($ex->getMessage());
	}

	return;
  }
}

?>
