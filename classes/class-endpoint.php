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
  function sendSuccess($obj = NULL) {

	  $obj->success      = TRUE;
	  $obj->errorMessage = NULL;

	  $this->sendJSON($obj);
	}

  /*
  * Send object in JSON format
  */
	private function sendJSON($obj) {
		$response = json_encode( $obj );
		$message = array  (
			'message' => 'Endpoint Response',
			'status'  => 'info',
			'details' => $response
		);
		$this->obj_api->_log_message($message, "1");

		/* we have to set the header to json. On some clients, they may have theme code that has all
		  ready submitted the headers. if they did, then we can not use the endpoint, they would need
		  to see an error occur and and work with our cs team to get things fixed.
		  outputting in our debug area where the culprit is will be helpful though
		 */
		$ptfilename = new stdClass();
		$ptlinenum = new stdClass();
		if (!headers_sent($ptfilename, $ptlinenum)) {
			header( 'Content-Type: application/json' );
		} else {
			$message = array  (
				'message' => 'Headers all ready sent.',
				'status'  => 'error',
				'details' => 'Headers are all ready sent in file:' . $ptfilename . ' at line num:' .$ptlinenum . '.'
			);
			$this->obj_api->_log_message($message, "1");
		}
		echo $response;
		exit();
  }

  /**
   * Verify endpoint action
   */
  private function actionVerify() {
	  //first check to make sure we have our api token
	  $apiToken = $this->obj_api->_get_token('api_token');

	  if (empty($apiToken)) {
	    $message = array  (
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
	  	  'detai  ls' => 'Asked to verify our install at: ' . date("Y-m-d H:i:s") . ' failed because api token is not valid'
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

	  $this->sendSuccess();
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
  private function actionPublish($postId, $nid) {
	  if (empty($postId)) {
	    $this->sendFailure("Empty post id");
	    return;
	  }

	  //ok, now go try and publish the post passed in
	  try {
	    $result = $this->obj_publish->publish_specific_post($postId, $nid);
			// Handle publishing errors and send back the message
			if (empty($result)) {
				$this->sendFailure('Post #'.$postId.' was not published.');
				return;
			}
			elseif (isset($result['error']) && $result['error'] === true){
				if (isset($result['errorMessage']) && $result['errorMessage'] != '') {
					$this->sendFailure($result['errorMessage']);
				}
				else {
					$this->sendFailure('Post #'.$postId.' was not published.');
				}
			}
	  } catch (Exception $ex) {
	    //looks like there was an internal error in publish, we will need to send a failure.
	    //no need to log here, as our internal methods have all ready logged it
	    $this->sendFailure($ex->getMessage());
	    return;
	  }

		$res = new stdClass();
		if (!empty($result['publishedId'])) {
			// Drupal base url
			global $base_url;
			// Prepare additional info to reply to publishThis
      $res->publishedId = $result['publishedId'];
			if (!empty($result['node_status']) && $result['node_status'] == 1) {
				$res->publishedUrl = $base_url . '/' . drupal_get_path_alias('node/' . $result['publishedId']);
			}
			$res->previewUrl = $res->draftUrl = $base_url.'/node/'.$result['publishedId'].'/edit';
		}

	  $this->sendSuccess($res);
	  return;
  }

  private function actionGetAuthors() {
	  $authors = array();
	  $obj     = new stdClass();

	  $users  = entity_load('user');
	  $emails = '';
	  foreach ($users as $user) {
	    if (in_array('authenticated user', $user->roles)) {
	  	  if (strlen($emails) > 0) {
	  	    $emails .= ' ' . $user->mail;
	  	  }
	  	  else {
	  	    $emails = $user->mail;
	  	  }
	  	  $authors[] = array('id' => $user->uid, 'name' => $user->name);
	    }
	  }
	  $obj->success      = TRUE;
	  $obj->errorMessage = NULL;
	  $obj->authors      = $authors;

	  $this->sendJSON($obj);
  }

	function get_subcategories_recursively($parent_id, $vid) {
		$children = taxonomy_get_children($parent_id, $vid);
		$subcategories = array();
		if(count($children)) {
			# It has children, let's get them.
			foreach ($children as $child_term){
				# Add the child to the list of children, and get its subchildren
				$subcategories[] = array(
					'id' => $child_term->tid,
					'name' => $child_term->name,
					'subcategories' => $this->get_subcategories_recursively($child_term->tid, $vid)
				);
			}
		}
		return $subcategories;
	}

  private function actionGetCategories() {
	  global $pt_settings_value;
		$categories = array();
		if ($pt_settings_value['taxonomy']['get_term'] !== 0) {
			$taxonomies = taxonomy_vocabulary_get_names();
			foreach ($taxonomies as $taxonomie) {
				if ($taxonomie->machine_name == $pt_settings_value['taxonomy_group']) {
					if (!$pt_settings_value['taxonomy_group'] !== 'default') {
						$terms = taxonomy_get_tree($taxonomie->vid);
						$tax_name = taxonomy_vocabulary_load($taxonomie->vid);
						foreach ($terms as $term) {
							if (isset($term->parents[0]) && $term->parents[0] == 0) {
								$category = array(
									'id' => intval($term->tid),
									'name' => $term->name,
									'taxonomyId' => intval($term->tid),
									'taxonomyName' => $tax_name->machine_name,
									'subcategories' => $this->get_subcategories_recursively($term->tid, $taxonomie->vid)
								);
								$categories[] = $category;
							}
						}
					}
					break;
				}
			}
		}
	  $obj               = new stdClass();
	  $obj->success      = TRUE;
	  $obj->errorMessage = NULL;
	  $obj->categories   = $categories;
	  $this->sendJSON($obj);
  }

  /**
   * Process request main function
   */
  function process_request($token) {
	  global $pt_settings_value;

		// Check if token from request matches Drupal token
		if ($token != substr($pt_settings_value['endpoint'], strrpos($pt_settings_value['endpoint'], '/') + 1)) {
			$message = array(
				'message' => 'Verify Plugin Endpoint',
				'status' => 'error',
				'details' => 'Asked to verify our install at: ' . date("Y-m-d H:i:s") . ' failed because request token mismatch Drupal token'
			);
			$this->obj_api->_log_message($message, "1");

			$this->sendFailure("Request token mismatch Drupal token");
			return;
		}

	  try {
	    $bodyContent = file_get_contents('php://input');
	    $this->obj_api->_log_message(array(
	  	  'message' => 'Endpoint Request',
	  	  'status'  => 'info',
	  	  'details' => $bodyContent
	    ), "2");

	    $arrEndPoint = json_decode($bodyContent, TRUE);
	    $action      = $arrEndPoint["action"];

			// If it's not "verify" action Validate API token
			if ($action != 'verify') {
				$current_token = $this->obj_api->_get_token();

				$is_token_valid = empty($current_token) ? FALSE : TRUE;
				if (!empty($current_token)) {
					$token_status = $this->obj_api->validate_token($current_token);
					if (!isset($token_status) || $token_status['valid'] != 1) {
						$is_token_valid = FALSE;
					}
				}

				if (!$is_token_valid) {
					$message = array(
						'message' => 'API tokem mismatch',
						'status' => 'error',
						'details' => 'We could not authenticate your API token, please correct the error and try again.'
					);
					$this->obj_api->_log_message($message, "1");

					$this->sendFailure("We could not authenticate your API token, please correct the error and try again.");
					return;
				}
			}

	    switch ($action) {
	  	  case "verify":
	  	    $this->actionVerify();
	  	  break;
	  	  case "publish":
	  	    $postId = intval($arrEndPoint["postId"], 10);
			  	$nid = intval( $arrEndPoint["publishedId"], 10 );
	  	    $this->actionPublish($postId, $nid);
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
