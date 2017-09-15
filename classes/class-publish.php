<?php
class Publishthis_Publish {
	private $pt_settings = null;
	private $obj_api = null;
	private $obj_utils = null;
	private $publishing_actions = array();

	/**
	* Publishthis_Publish constructor
	*/
	function __construct() {
		$this->pt_settings = unserialize(variable_get('pt_settings'));

		$this->obj_api  = new Publishthis_API();
		$this->obj_utils = new Publishthis_Utils();
	}

	/**
	 * returns all publishing actions that are published
	 */
	public static function get_publishing_actions() {
		// Find records
		$actions = array();
		$result = db_select('pt_publishactions', 'pb')
				->fields('pb')
				->execute();
		while($record = $result->fetchAssoc()) {
			$actions[$record['id']] = $record;
		}

		return $actions;
	}

	/**
	 * Publishes the single post with a Publishing Actions meta information
	 *
	 * @param int     $post     Publishthis Post object
	 * @param array   $action_meta   Publishthis Publish Action data
	 * @param array   $nid Node id
	 */
	function publish_post_with_publishing_action($post, $action_meta, $nid) {

    // Unique set name
    $set_name = '_publishthis_set_' . $action_meta['pta_pt_post_type'] . '_' . $post['id'];

    // Look for nid if it's not provided by API
    if (empty($nid) || ($nid <= 0)){
      $nid = $this->_get_post_by_docid ( $set_name );
    }

    //don't update existed posts if synchronization is turned off
    if ( $nid && ! $action_meta['pta_override_edits'] ) {
      return array( 'error' => true, 'errorMessage' => 'No publishing event happened, because the CMS is set to ignore any updates from PublishThis.');
    }

    try{
      // Look for template url
      $strHtmlTemplateUrl = $action_meta['pta_post_template_url'];
      if (!empty($strHtmlTemplateUrl)){
        if (! (substr($strHtmlTemplateUrl, 0, strlen("http")) === "http")){
          $strHtmlTemplateUrl = null;
        }
      }
      $arrHTMLItems = array();
      // Get post html from template url or defined template
      if (!empty($strHtmlTemplateUrl)){
        //TODO fix the webapi so it checks for template url param and not valid template id
        $arrHTMLItems = $this->obj_api->get_post_html($post['id'], "989", $strHtmlTemplateUrl);
      }else{
        $arrHTMLItems = $this->obj_api->get_post_html($post['id'], $action_meta['pta_post_template'], null);
      }

    }catch( Exception $ex ) {
      $message = array(
        'message' => 'Generate Post HTML',
        'status' => 'error',
        'details' => 'Unable to generate the HTML for PublishThis Post ID:' . $post['id'] . ', html template id:' . $action_meta['pta_post_template'] . ', html template url:' . $action_meta['pta_post_template_url'] . ' because of:' . $ex->getMessage()
      );
      $this->obj_api->_log_message( $message, "1" );
      return array( 'error' => true, 'errorMessage' => 'Unable to generate the HTML for PublishThis Post ID:' . $post['id'] . ', html template id:' . $action_meta['pta_post_template'] . ', html template url:' . $action_meta['pta_post_template_url'] . ' because of:' . $ex->getMessage());
    }

    // Run Drupal API to add/update node
    $result = $this->_update_content($nid, $arrHTMLItems, $action_meta, $post, $set_name);
    // If error
    if (isset($result['error']) && $result['error'] === true) {
      $error_message = 'Node creation exception error';
      if (isset($result['errorMessage']) && $result['errorMessage'] != '') {
        $error_message = $result['errorMessage'];
      }
      return array( 'error' => true, 'errorMessage' => $error_message );
    }
    // Published successfully
    $returnInfo = array( 'error' => false, 'successMessage' => 'Post was ' . $result['status'] . '.', 'publishedId' => $result['nid'], 'node_status' => $result['node_status']);
    return $returnInfo;
	}

	/**
	 *   Save import content as a node
	 *
	 * @param unknown $nid              	Node ID
   * @param unknown $arrHTMLItems      Imported html items
   * @param unknown $content_features     Additional content info
	 * @param number  $post              The PublishThis Post object
	 * @param unknown $set_name                docid linked to this post
	 * @param unknown $arrPostCategoryNames Category ???
	 */
	private function _update_content($nid, $arrHTMLItems, $content_features, $post, $set_name) {
    global $pt_settings_value;
	  try {
      $node = !empty($nid) ? node_load($nid) : new stdClass();
      $node->type = $content_features['pta_content_type'];
      node_object_prepare($node);

      // Author
      $uid = 1;
      // If author is set in post then override default one from
      // Publishing Action
      if (!empty($post['authors'][0]->id)) {
        $content_features['pta_publish_author'] = $post['authors'][0]->id;
      }

      // Handle database exceptions.
      try {
        // Check if user exists - otherwise apply uid 1
        $user_name = db_query("SELECT name FROM {users} WHERE uid = :uid;", array(':uid' => $content_features['pta_publish_author']))->fetchField();
        if (!empty($user_name)){
          $uid = $content_features['pta_publish_author'];
          $node->name = $user_name;
        }
      } catch (Exception $ex) {
        $message = array(
          'message' => 'Node creation - User',
          'status' => 'error',
          'details' => $ex->getMessage()
        );
        $this->obj_api->_log_message( $message, "1" );
      }

      $node->uid = $uid;
      $node->status = $content_features['pta_content_status'];
      // Handle workbench moderation
      if (module_exists('workbench_moderation')) {
        // Don't proceed if moderation is not enabled on this content
        if (workbench_moderation_node_moderated($node)) {
          if (!empty($nid) && isset($node->workbench_moderation)) {
            $node->workbench_moderation['updating_live_revision'] = 1;
            $node->workbench_moderation['current']->stamp = REQUEST_TIME;
            $node->workbench_moderation['current']->timestamp = REQUEST_TIME;
          }
          $node->revision = 1;
          if ($node->status > 0) {
            $node->workbench_moderation_state_new = workbench_moderation_state_published();
          }
          else {
            $node->workbench_moderation_state_new = workbench_moderation_state_none();
          }
        }
      }

      $node->language = LANGUAGE_NONE;
      $node->is_new = empty($nid) ? TRUE : FALSE;

      $curated_content = '';
      if (!empty($arrHTMLItems) && (count($arrHTMLItems) > 0)){
        $curated_content = $arrHTMLItems[0];
      }

      // Handle exceptions that may occurs during body field processing.
      try {
        $node->body[$node->language][0]['value'] = _publishthis_replace_unhandled_characters($curated_content);
        $node->body[$node->language][0]['format'] = 'full_html';
        $node->body[$node->language][0]['summary'] = $this->_build_node_summary($arrHTMLItems);
      } catch (Exception $ex) {
        $message = array(
          'message' => 'Node creation - Body',
          'status' => 'error',
          'details' => $ex->getMessage()
        );
        $this->obj_api->_log_message( $message, "1" );
      }

      $node->title = !empty($post['title']) ? $post['title'] : NODE_NO_TITLE;

      // Featured image
      $featured_image = isset($content_features['pta_featured_image']['save_featured_image']) && $content_features['pta_featured_image']['save_featured_image'] === 'save_featured_image' ? true : false;
      if ( $featured_image && !empty($post['featuredDocument']->imageUrl)) {
        // Handle featured image exceptions.
        try {
          $node->field_image[$node->language][0] = $this->_get_featured_image($post['featuredDocument']->imageUrl, $content_features);
        } catch (Exception $ex) {
          $message = array(
            'message' => 'Node creation - Featured image',
            'status' => 'error',
            'details' => $ex->getMessage()
          );
          $this->obj_api->_log_message( $message, "1" );
        }
      }
      else {
        unset( $node->field_image[$node->language][0] );
      }

      // Categories
      if (isset($pt_settings_value['taxonomy']['get_term']) && $pt_settings_value['taxonomy']['get_term'] !== 0) {
        $categry_field_name = 'pt_' . $node->type;
        $node->{$categry_field_name}[$node->language] = array();
        if (!empty($post['categories']) && count($post['categories'])) {
          foreach ($post['categories'] as $cat) {
            if (!empty($cat->id) && taxonomy_term_load($cat->id)) {
              $node->{$categry_field_name}[$node->language][] = array('tid' => $cat->id);
            }
          }
        }
        // Tags
        if (isset($content_features['pta_add_tags']) && $content_features['pta_add_tags'] == 1) {
          $node->field_tags[$node->language] = array();
          // Check if vocabulary "tags" exists
          $vocab = taxonomy_vocabulary_machine_name_load('tags');
          if (!empty($vocab->vid) && !empty($vocab->machine_name)) {
            if (!empty($post['tags']) && count($post['tags'])) {
              foreach ($post['tags'] as $tag) {
                // Process tag
                switch ($tag->type) {
                  case 'keyword':
                    $tag = $tag->text;
                    break;

                  case 'entity':
                    $tag = $tag->text;
                    break;

                  case 'topic':
                    $tag = $tag->displayName . ' (' . $tag->topicLabel . ')';
                    break;

                  case 'parentTopic':
                    $tag = $tag->displayName . ' (' . $tag->topicLabel . ')';
                    break;

                  default:
                    $tag = NULL;
                    break;
                }
                // If tag already exists then apply, otherwise create taxonomy term and apply
                if (!empty($tag)) {
                  $tid = _get_tid_from_term_name($tag, 'tags');
                  if (!empty($tid)) {
                    $node->field_tags[$node->language][] = array('tid' => $tid);
                  }
                  else {
                    $term = new stdClass();
                    $term->vid = $vocab->vid;
                    $term->name = $tag;
                    taxonomy_term_save($term);
                    if (!empty($term->tid)) {
                      $node->field_tags[$node->language][] = array('tid' => $term->tid);
                    }
                  }
                }
              }
            }
          }
        }
      }

      // Save node
      $node = node_submit($node);
      node_save($node);

      // Set info about post
      if (empty($nid) || ($nid <= 0)) {
        $this->_set_docid($node->nid, $set_name, $set_name);
      }
      // Set current update date
      $this->_set_curatedate_by_nid($node->nid, time());

      $status = empty($nid) ? 'inserted' : 'updated';
    }
    catch( Exception $ex ) {
      $message = array(
        'message' => 'Node creation',
        'status' => 'error',
        'details' => $ex->getMessage()
      );
      $this->obj_api->_log_message( $message, "1" );
      return array( 'error' => true, 'errorMessage' => $ex->getMessage());
    }

		return  array('nid' => $node->nid, 'status' => $status, 'node_status' => $node->status);
	}

	/**
	 *   Prepare node summary text
	 *
	 * @param string $text
	 */
	private function _build_node_summary( $content ) {
    $summary = isset($content->summary) && strlen($content->summary)>0 ? _publishthis_replace_unhandled_characters($content->summary) : '';
    return text_summary('<p class="pt-excerpt">'.$summary. '</p>' );
	} 

	/**
	 *   Set docid for specified post ID
	 *
	 * @param unknown $docid
	 */
	private function _set_docid( $nid, $docid, $setName='' ) {			
		$query = db_insert('pt_docid_links')
			->fields( array(
						'docId' => $docid,
						'setName' => $setName,
						'nid' => $nid )
					)
			->execute();
	}

	/**
	 *   Get node ID by specified docid value
	 *
	 * @param unknown $docid
	 */
	private function _get_post_by_docid( $docid ) {
		$result = db_select('pt_docid_links', 'dl')
			->fields('dl', array('docId','nid'))
			->condition('dl.docId', $docid, '=')
			->range(0,1)		
			->execute()
			->fetchAssoc();
		return $result ? $result['nid'] : 0;
	}

	/**
	 *   Set node curate date for node id
	 *
	 * @param unknown $nid
	 */
	private function _set_curatedate_by_nid( $nid, $curateUpdateDate ) {
		$result = db_update('pt_docid_links')
			->fields( array( 'curateUpdateDate' => $curateUpdateDate ) )
			->condition( 'nid', $nid, '=')
			->execute();
	}

	/**
	 * Generates resized featured image and link it to the node
	 */
  private function _get_featured_image( $contentImageUrl, $content_features ) {
		$file_name = uniqid() . '_' . basename($contentImageUrl);
		$ok_override_fimage_size = isset($content_features['pta_ignore_original_image']['resize_featured_image']) && $content_features['pta_ignore_original_image']['resize_featured_image']==="resize_featured_image" ? "1" : "0";

		//build the url that we would need to download the featured image for
		switch ( $content_features ['pta_image_size'] ) {
			case 'custom':
				$resize_pref = "Custom, ";
				$contentImageUrl = $this->obj_utils->getResizedPhotoUrl( $contentImageUrl, $content_features['pta_image_width'], "1", $content_features ['pta_image_height'], $ok_override_fimage_size, "0" );
			break;

			case 'custom_max_width':
				$resize_pref = "custom max, ";
				//$this->obj_api->_log_message( "custom max, ok to resize original featured image:" . $ok_override_fimage_size );
				$contentImageUrl = $this->obj_utils->getResizedPhotoUrl( $contentImageUrl, $content_features['pta_image_maxwidth'], "1", 0, $ok_override_fimage_size, "0" );
      break;

      case 'custom_up_to_max_width':
        $resize_pref = "Custom up to max, ";
        $contentImageUrl = $this->obj_utils->getResizedPhotoUrl( $contentImageUrl, $content_features['pta_image_up_to_maxwidth'], "1", 0, $ok_override_fimage_size, "1" );
      break;

			case 'theme_default':
			default:
				$resize_pref = "";
			break;
		}

		$message = array(
			'message' => 'Featured images resizing',
			'status' => 'info',
			'details' => $resize_pref . "ok to resize original featured image:" . $ok_override_fimage_size . "; url:" . $contentImageUrl );
		$this->obj_api->_log_message( $message, "1" );

		$ch = curl_init();
		$timeout = 5;
		curl_setopt($ch, CURLOPT_URL, $contentImageUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$data = curl_exec($ch);
		curl_close($ch);			

        $fp = fopen($file_name, 'w');
		fwrite($fp, $data);
		fclose($fp);

	  $file_path = system_retrieve_file($contentImageUrl, NULL, FALSE, FILE_EXISTS_REPLACE);
		$file = (object) array(
				'uid' => 1,
				'uri' => $file_path,
				'filemime' => file_get_mimetype($contentImageUrl),
				'status' => 1,
			);

		$file = file_copy($file, 'public://'); // Save the file to the root of the files directory. You can specify a subdirectory, for example, 'public://images'
		@unlink($file_path);
		return (array)$file;
	}

	/**
	 * this takes post id, and then tries to publish it
	 * using all of our helper functions.
	 * This will usually be called from our publishing endpoint
	 */

	public function publish_specific_post($postId, $nid) {


		try{
			//to publish, we need our actual post object
			$post = $this->obj_api->get_basic_post_data($postId);
      // If empty post then return error message
      if (empty($post)) {
        return array( 'error' => true, 'errorMessage' => 'That was either an error in calling the PublishThis api to get the published data, or there was no data in the Post. Please check your logs on your CMS');
      }
			//get all publishing actions that match up with this feed template (usually 1)
			$arrPublishingActions = $this->get_publishing_actions();
      $published = false;
			//loop the publishing actions and it will then publish content for that post
			foreach ( $arrPublishingActions as $pubAction ) {

				$action_meta = unserialize($pubAction['value']);

				if ( $post['publishTypeId'] == $action_meta['pta_pt_post_type'] ) {
					try{
						$result = $this->publish_post_with_publishing_action($post, $action_meta, $nid);
            if (isset($result['error']) && $result['error'] === true) {
              return $result;
            }
            $published = true;
					}catch( Exception $ex ) {
						//we capture individual errors and report them,
						$message = array(
							'message' => 'Import of Post Failed',
							'status' => 'error',
							'details' => 'The Post Id that failed:' . $post['postId'] . ' with the following error:' . $ex->getMessage() );
						$this->obj_api->_log_message( $message, "1" );
					}
				}
			}
			// If not published, means that publishing action was not found.
			if (!$published) {
			  return array( 'error' => true, 'errorMessage' => 'There was no matching Publishing Action for this PublishThis Post Type found on your CMS. Please check your PublishThis settings on the CMS.');
      }
		}catch( Exception $ex ) {
			//some other occurred while we tried publish, not sure what
			//capture this and log it and then throw it as well as what info we have
			$message = array(
				'message' => 'Import of Post Failed',
				'status' => 'error',
				'details' => 'A general exception happened during the publishing of specific post. Post Id not published:' . $post['postId'] . ' specific message:' . $ex->getMessage() );
			$this->obj_api->_log_message( $message, "1" );
      return array( 'error' => true, 'errorMessage' => 'A general exception happened during the publishing of specific post. Post Id not published:' . $post['postId']);
		}
		// Success
		if (!empty($result)) {
      return $result;
    }
    // Failed
    else {
      return array( 'error' => true, 'errorMessage' => 'Post #'.$post['postId'].' was not published.');
    }
	}

}
