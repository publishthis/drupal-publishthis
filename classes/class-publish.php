<?php
class Publishthis_Publish {
	private $pt_settings = null;
	private $obj_api = null;
	private $obj_render = null;
	private $obj_utils = null;
	private $publishing_actions = array();

	/**
	* Publishthis_Publish constructor
	*/
	function __construct() {
		$this->pt_settings = unserialize(variable_get('pt_settings'));

		$this->obj_api  = new Publishthis_API();
		$this->obj_render = new Publishthis_Render();
		$this->obj_utils = new Publishthis_Utils();
	}

	
	/**
	 *   Process content import
	 */
	function run_import() {
		// Return here is we want to pause polling.
		if ( $this->pt_settings['pause_polling']['Stop polling the API for new content'] ) {
			$this->obj_api->_log_message( array( 'message' => 'Pause Polling', 'status' => 'warn', 'details' => '' ), "2" );
			return;
		}

		$this->publishing_actions = $this->get_publishing_actions();

		$import_id = variable_get( 'pt_import_id' );
		if ( null === $import_id ) {
			$import_id = 0;
			variable_set( 'pt_import_id' , $import_id );
		}

		$this->obj_api->_log_message( array( 'message' => 'Import Started (#'.$import_id.')', 'status' => 'info', 'details' => 'Time: '.date( "Y-m-d H:i:s" ) ), "2" );
		
		foreach ( $this->publishing_actions as $action ) {
			$props = unserialize($action['value']);
			$action_prev_timestamp = $props['import_start'];			
			$action_curr_timestamp = time();

			if ( $action_curr_timestamp >= intval( $action_prev_timestamp ) + intval( $props['poll_interval'] ) ) { echo 'Run import';
				$this->import_content( $action['id'], $action_prev_timestamp );
			}
		}

		$this->obj_api->_log_message( array( 'message' => 'Import Finished (#'.$import_id.')', 'status' => 'info', 'details' => 'Time: '.date( "Y-m-d H:i:s" ) ), "2" );
		variable_set( 'pt_import_id' , intval( $import_id ) + 1 );
	}

	/**
	 *   Import data from Manager tool using Publishing Action settings
	 *
	 * @param unknown $action_id Publishing Action
	 * @param unknown $timestamp Used to find newly created or published content
	 * This will pull in the curated content for feeds.  Depending on the
	 * publishing action, individual posts will be created from the curated
	 * documents, or, digest posts will be created from the curated documents.
	 */
	function import_content( $action_id, $timestamp ) {
		// Collect debug info
		$message = array();
		$message['message'] = 'Content Import';
		$message['status'] = 'info';
		$message['details'] = '';
		$message['details'] .= 'Import Timestamp: '.$timestamp.'<br/>';
		$message['details'] .= 'From: '.$this->pt_settings['curated_publish'].'<br/>';
		$message['details'] .= 'Action ID: '.$action_id.'<br/>';

		// Get $action
		$action = $this->publishing_actions[$action_id];

		//save import timestamp
		$action_values = unserialize($action['value']); 
		$action_values['import_start'] = time(); 
		db_update('pt_publishactions')->fields( array( 'value' => serialize($action_values) ) )
									->condition( 'id', $action_id, '=')
									->execute();

		if ( ! $action ) {
			$message['status'] = 'error';
			$message['details'] .= 'Status: error ( action not found )';
			$this->obj_api->_log_message( $message, "2" );
			return;
		}			
		
		//get all meta data for this publishing action
		$action_meta = $action_values;

		$message['details'] .= 'Action format: '.$action['format_type'].'<br/>';

		// Get feeds
		$feeds = $this->obj_api->get_feeds_since_timestamp ( $timestamp, $action_meta['feed_template'] );

		$message['details'] .= 'Found: '.count( $feeds ).' feed(s)<br/>';

		if ( empty ( $feeds ) ) {
			$message['status'] = 'warn';
			$message['details'] .= 'Status: error ( empty feeds list )';
			$this->obj_api->_log_message( $message, "2" );
			return;
		}
		
		//loop each of our feeds, and then either create individual posts or digests from the curated documents in the feed
		$ids = array();
		$intErrorCount = 0;
		foreach ( $feeds as $feed ) {
			$ids[] = $feed['feedId'];
			try{
				$this->publish_feed_with_publishing_action( $feed, $action_meta );
			}catch( Exception $ex ) {

				//we capture individual errors and report them,
				//but we should keep trying to loop because not all feeds may have an issue
				$message = array(
					'message' => 'Import of Feed Failed',
					'status' => 'error',
					'details' => 'Time: '.date( "Y-m-d H:i:s" ).'<br/>Cron Timestamp: '.$timestamp . ' feed id:' . $feed['feedId'] . ' specific error:' . $ex->getMessage() );
				$this->obj_api->_log_message( $message, "1" );

				$intErrorCount++;
			}
		}

		if ( $intErrorCount == 0 ) {
			$message['details'] .= 'Feed IDs: '.implode( ',', $ids ).'<br/>';
			$message['details'] .= 'Status: ' . $intErrorCount . ' errors';
			$this->obj_api->_log_message( $message, "2" );

		}else {
			$message = array(
				'message' => 'Some Import of Feeds Failed',
				'status' => 'error',
				'details' => 'Total Feed Failures:' . $intErrorCount . ' out of ' . count( $feeds ) );
			$this->obj_api->_log_message( $message, "1" );
		}
	}

	/**
	 * returns all publishing actions that are published
	 */
	public function get_publishing_actions() {
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
	 * Publishes the single feed with a Publishing Actions meta information
	 *
	 * @param int     $feed_id     Publishthis Feed id
	 * @param array   $feed_meta   Publishthis Feed data (display name, etc.)
	 * @param int     $action_id   Publishing Action id
	 * @param array   $action_meta Publishing Action data
	 */
	function publish_feed_with_publishing_action( $feed, $action_meta ) {
		try{
			
	
			$posts_updated = $posts_inserted = $posts_deleted = $posts_skipped = 0;

			$feed_id = $feed['feedId'];
			$feed_meta = array( "displayName" => $feed['displayName'] );

			$curated_content = $this->obj_api->get_section_content ( $feed_id, $action_meta['template_section'] );
			if ( empty ( $curated_content ) ) {
				return;
			}

			// Unique set name
			$set_name = '_publishthis_set_' . $action_meta['feed_template'] . '_' . $action_meta['template_section'] . '_' . $feed_id;

			$arrPostCategoryNames = array();

			$result_list = $this->obj_api->get_custom_data_by_feed_id ( $feed_id, array () );
			$custom_data = $managerCategories = array();
			$action_meta['ptauthors'] = null;
			foreach ( $result_list as $result ) {
				if( strtoupper( $result->type ) != 'CMS' && isset($result->value) && !empty($result->value) ) {
					$custom_data[$result->shortCode] = $result->value;

					if ( !in_array( $result->shortCode, array( 'ptauthors', 'ptcategories', 'pttags' ) ) ) {
						$managerCategories[] = $result->value;
					}
				}				
			}
					
			// Categorize
			// map categories from custom data in a Feed to categories in wordpress
			if ( !empty($action_meta['action_category']) ) {
				if ( isset( $custom_data[ $action_meta['action_category'] ] ) ) {
					$strCategoryValue = $custom_data[ $action_meta['action_category'] ];

					foreach ( explode( ',', $strCategoryValue ) as $category ) {
						$arrPostCategoryNames[] = $category;
					}
				}

				$this->obj_api->_log_message( array( 'message' => 'Trying to map to categories', 'status' => 'info', 'details' => implode( ",", $managerCategories ) ), "2" );
			}

			// Combined mode selected - all imported content in single item
			if ( $action_meta['format_type'] == 'Digest' ) {
				//don't update existed posts if synchronization is turned off
				$nid = $this->_get_post_by_docid ( $set_name ); 
				if ( $nid && $action_meta['allow_to_override'] != "1" ) {
					$posts_skipped++;
				}
				else {
					//set title
					$action_meta['digest_title'] = $feed_meta['displayName'];

					//save imported data
					//this is updating a "combined or digest post"
					$status = $this->_update_content( $nid, $feed_id, $set_name, $set_name, $arrPostCategoryNames, $curated_content, $action_meta );
					if ( $status == 'updated' ) $posts_updated++;
					if ( $status == 'inserted' ) $posts_inserted++;					
				}
			}
			else { // Individual mode selected - import content in separate WP posts
				$new_set_docids = array ();

				// make sure to reverse the array, as the order in the publish
				// this template sections have a defined order. so, the first one in the template
				// section should be marked as most recently published
				foreach ( array_reverse( $curated_content ) as $content ) {
					$new_set_docids [] = $content->docId;

					//don't update existed posts if synchronization is turned off
					$nid = $this->_get_post_by_docid ( $content->docId );
					if ( $nid && $action_meta['ind_modified_content'] != "1" ) $posts_skipped++;
					if ( $nid && $action_meta['ind_modified_content'] != "1" ) continue;

					$status = $this->_update_content( $nid, $feed_id, $set_name, $content->docId, $arrPostCategoryNames, $content, $action_meta );

					if ( $status == 'updated' ) $posts_updated++;
					if ( $status == 'inserted' ) $posts_inserted++;
					if ( $status == 'skipped' ) $posts_skipped++;					
				}

				if ( $action_meta['ind_delete_posts'] == "1" ) {
					$posts_deleted = $this->_delete_individuals( $new_set_docids, $set_name );
				}
			}

			$message = array(
				'message' => 'Import Results',
				'status' => 'info',
				'details' => ( $posts_updated+$posts_inserted+$posts_skipped+$posts_deleted ).' post(s) processed: '.
				$posts_updated.' updated, '.$posts_inserted.' inserted, '.$posts_deleted.' deleted, '.$posts_skipped.' skipped' );
			$this->obj_api->_log_message( $message, "2" );
		}catch( Exception $ex ) {
			$message = array(
				'message' => 'Import Results',
				'status' => 'error',
				'details' => 'Unable to publish the feed id:' . $feed['feedId'] . ', because of:' . $ex->getMessage() );
			$this->obj_api->_log_message( $message, "1" );

			throw $ex;
		}
	}

	/**
	 *   Save import content as a node
	 *
	 * @param unknown $nid              	Node ID
	 * @param number  $feed_id              The PublishThis Feed Id
	 * @param unknown $docid                docid linked to this post
	 * @param unknown $arrPostCategoryNames Category
	 * @param unknown $curated_content      Imported content
	 * @param unknown $content_features     Additional content info
	 */
	private function _update_content( $nid, $feed_id, $set_name, $docid, $arrPostCategoryNames, $curated_content, $content_features ) {
		$body_text = '';

		//if don't add new node
		if( $content_features['ind_add_posts']=='0' && empty($nid) && $content_features['format_type'] == 'Individual' ) return;

		$node = !empty($nid) ? node_load($nid) : new stdClass();

		//first, see if we are even allowed to do an update if it is there (for individuals only)
		if( $content_features['format_type'] == 'Individual' ) {
			if ( !empty($nid) && ( $content_features['ind_modified_content'] == '1' ) ) {
				//get node curate date
				$node_curatedate = $this->_get_curatedate_by_nid($nid);
				
				//check publishthis doc last update date
				if ( !isset($node_curatedate) || $node_curatedate == $curated_content->curateUpdateDate ) {
					if ( !isset($node_curatedate) ) {
						if( isset($curated_content) && isset($curated_content->curateUpdateDate) ) {
							$this->_set_curatedate_by_nid($nid, $curated_content->curateUpdateDate);
						}
						$message = array(
							'message' => 'Skipped Individual Doc',
							'status' => 'info',
							'details' => 'Skipped doc because it had an empty update date. set it and skipping. Node id:' . $nid . ' for feed id:' . $feed_id. ' date was:' . $curated_content->curateUpdateDate );				
						$this->obj_api->_log_message( $message, "1" );
					}
					else {
						$message = array(
							'message' => 'Skipped Individual Doc',
							'status' => 'info',
							'details' => 'Skipped doc because it was not updated. Node id:' . $nid . ' for feed id:' . $feed_id );
						$this->obj_api->_log_message( $message, "1" );
					}					
					return "skipped";
				}
			}

		}		
		
		$node->type = $content_features['content_type'];
		node_object_prepare($node);

		$node->uid = $content_features['publish_author'];
		$node->status = $content_features['content_status'];
		$node->language = LANGUAGE_NONE;
		$node->is_new = empty($nid) ? TRUE : FALSE;

		if ( $content_features['format_type'] == 'Digest' ) {
			$node->title = !empty( $content_features['digest_title'] ) ? $content_features['digest_title'] : NODE_NO_TITLE	;

			$curated_content_index = 1; //an index for usage in our template rendering. This way, we can do different things per item of content
		
			$this->obj_render->pt_is_first = true;
			$this->obj_render->pt_content_features = $content_features;

			$contentImageUrl = null;

			// Generate html output
			foreach ( $curated_content as $content ) {

				//until manager tool fixes the original vs thumbnail issue, we need to switch bookmark images to their thumbnails
				if ( !empty( $content->imageUrl ) ) {
					if ( strrpos( $content->imageUrl, "bookmark" ) > 0 ) {
						$content->imageUrl = $content->imageUrlThumbnail;
					}
				}

				//save first image url for featured
				if ( !empty( $content->imageUrl ) && $contentImageUrl == null ) {
					$this->obj_render->pt_found_featured_image = true;
					$contentImageUrl = $content->imageUrl;
				}

				$content->feedId = $feed_id;
				$content->curatedContentIndex = $curated_content_index;
				$this->obj_render->pt_content = $content;
						
				$body_text .= $this->obj_render->render_content( $content_features['format_type'] );
				
				//$this->obj_render->pt_break_page = false;
				$this->obj_render->pt_is_first = false;
				$this->obj_render->pt_found_featured_image = false;

				$curated_content_index++;
			}

			$node->body[$node->language][0]['value']   = $body_text;
          $node->body[$node->language][0]['format'] = 'full_html';
          $node->body[$node->language][0]['summary'] = $this->_build_node_summary($curated_content[0]);
		}
		else {
			$content = $curated_content;
			$node->title = !empty( $content->title ) ? $content->title : NODE_NO_TITLE;

			$content->feedId = $feed_id;

			//until manager tool fixes the original vs thumbnail issue, we need to switch bookmark images to their thumbnails
			if ( !empty( $content->imageUrl ) ) {
				if ( strrpos( $content->imageUrl, "bookmark" ) > 0 ) {
					$content->imageUrl = $content->imageUrlThumbnail;
				}
			}

			$contentImageUrl = $content->imageUrl;

			$this->obj_render->pt_content = $content;
			$this->obj_render->pt_content_features = $content_features;
    		$body_text = $this->obj_render->render_content( $content_features['format_type'] );
			$node->body[$node->language][0]['value']   = $body_text;
			$node->body[$node->language][0]['summary'] = $this->_build_node_summary($content);
          $node->body[$node->language][0]['format'] = 'full_html';
		}

		//Set content alias on insert
		if( empty($nid) ) {
			$path = $node->title!=NODE_NO_TITLE ? preg_replace("/[^a-zA-Z0-9_]/","", str_replace( ' ', '_', $node->title ) ) . '_' . uniqid() : 'pt-content-' . uniqid();
			$node->path = array('alias' => $path);
		}		

		// Download and set featured image
		$featured_image = $content_features['featured_image']['save_featured_image']==='save_featured_image' ? true : false;
	
		if ( $featured_image && !empty ( $content->imageUrl ) ) {
			$node->field_image[$node->language][0] = $this->_get_featured_image( $contentImageUrl, $content_features );	
		}
		else {
			unset( $node->field_image[$node->language][0] );
		}

		//Categorize content
		foreach( $arrPostCategoryNames as $key=>$category ) {
			$search_category = taxonomy_get_term_by_name($category, $content_features['taxonomy_group']);
			if( $search_category ) {
				$term = array_shift( $search_category );
				$node->field_tags[$node->language][$key]['tid'] = intval($term->tid);
				$node->field_tags[$node->language][$key]['vid'] = intval($term->vid);
			}
		}

		$node = node_submit($node);
		node_save($node);
      /* Add ptmetadata to node */
      $someValue = json_encode($curated_content);
        db_update('node')
          ->fields( array( 'ptmetadata' => $someValue ) )
          ->condition( 'nid', $node->nid, '=')
          ->execute();
           if( empty( $node->nid ) ) {
			$message = array(
				'message' => 'Post insert/update error',
				'status' => 'error',
				'details' => implode( ';', $result->get_error_messages() ) );
			$this->obj_api->_log_message( $message, "1" );
		}

		if( $nid == 0 ) {
			$this->_set_docid( $node->nid, $docid, $set_name );
		}

		if( isset($curated_content) && isset($curated_content->curateUpdateDate) ) {
			$this->_set_curatedate_by_nid($node->nid, $curated_content->curateUpdateDate);
		}

		return  empty($nid) ? 'inserted' : 'updated';
	}

	/**
	 *   Prepare node summary text
	 *
	 * @param string $text
	 */
	private function _build_node_summary( $content ) {
		$summary = isset($content->summary) && strlen($content->summary)>0 ? $content->summary : '';
		return text_summary( $summary );
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
	 *   Get node curate date by node id
	 *
	 * @param unknown $nid
	 */
	private function _get_curatedate_by_nid( $nid ) {
		$result = db_select('pt_docid_links', 'dl')
			->fields('dl', array('curateUpdateDate','nid'))
			->condition('dl.nid', $nid, '=')
			->range(0,1)		
			->execute()
			->fetchAssoc();
		return $result ? $result['curateUpdateDate'] : null;
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
	 *   Get remove unused nodes
	 *
	 * @param unknown $docid
	 */
	private function _delete_individuals( $new_set_docids, $set_name ) {
		$nodes_deleted = 0;

		//get nodes to remove
		$query = db_select('pt_docid_links', 'dl')
			->fields('dl', array('id','nid'))
			->condition('dl.setName', $set_name, '=');

		if( count($new_set_docids) > 0 ) {
			$query->condition('dl.docId', $new_set_docids, 'NOT IN');
		}

		$result = $query->execute();
		
		if($result->rowCount() > 0 ) {
			while($record = $result->fetchAssoc()) {
				//delete node
				echo 'Node deleted: '.$record['nid'].'<br>';
				node_delete( $record['nid'] );
				$nodes_deleted++;
			}
			
			//delete docs links
			$links_query = db_delete('pt_docid_links')->condition('setName', $set_name, '=');

			if( count($new_set_docids) > 0 ) {
				$links_query->condition('docId', $new_set_docids, 'NOT IN');
			}
			$links_query->execute();
	
		}
		
		return $nodes_deleted;
	}

	/**
	 * Generates resized featured image and link it to the node
	 */
	private function _get_featured_image( $contentImageUrl, $content_features ) {
		$file_name = uniqid() . '_' . basename($contentImageUrl);
		$ok_override_fimage_size = $content_features['ignore_original_image']['resize_featured_image']==="resize_featured_image" ? "1" : "0";

		//build the url that we would need to download the featured image for
		switch ( $content_features ['featured_image_size'] ) {
			case 'custom':
				$resize_pref = "Custom, ";
				$contentImageUrl = $this->obj_utils->getResizedPhotoUrl( $contentImageUrl, $content_features['featured_image_width'], "1", $content_features ['featured_image_height'], $ok_override_fimage_size );
				break;

			case 'custom_max_width':
				$resize_pref = "custom max, ";
				//$this->obj_api->_log_message( "custom max, ok to resize original featured image:" . $ok_override_fimage_size );
				$contentImageUrl = $this->obj_utils->getResizedPhotoUrl( $contentImageUrl, $content_features['featured_image_maxwidth'], "1", 0, $ok_override_fimage_size  );
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
		
		$file_path = drupal_realpath($file_name); // Create a File object
		
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
	 * this takes an array of feed ids, and then tries to publish each one of them
	 * using all of our helper functions.
	 * This will usually be called from our publishing endpoint
	 */

	public function publish_specific_feeds( $arrFeedIds ) {
		//use these to keep track of what published and what didn't
		//so we can report it back to the caller in an exception
		$intDidPublish = 0;
		$arrFeedsNotPublished = array();

		try{
			//to publish, we need our actual feed objects
			$arrFeeds = $this->obj_api->get_feeds_by_ids( $arrFeedIds );

			//loop feeds to publish
			foreach ( $arrFeeds as $feed ) {

				//get all publishing actions that match up with this feed template (usually 1)
				$arrPublishingActions = $this->get_publishing_actions();

				$blnDidPublish = false;

				//loop the publishing actions and it will then publish content for that feed
				foreach ( $arrPublishingActions as $pubAction ) {
					$actionId = $pubAction['id'];

					$action_meta = unserialize($pubAction['value']);

					if ( $feed['templateId'] == $action_meta['feed_template'] ) {
						try{
							$this->publish_feed_with_publishing_action( $feed, $action_meta );
						}catch( Exception $ex ) {
							//we capture individual errors and report them,
							//but we should keep trying to loop because not all feeds may have an issue
							$message = array(
								'message' => 'Import of Feed Failed',
								'status' => 'error',
								'details' => 'The Feed Id that failed:' . $feed['feedId'] . ' with the following error:' . $ex->getMessage() );
							$this->obj_api->_log_message( $message, "1" );
							continue;
						}
						$intDidPublish++;
						$blnDidPublish = true;
					}
				}

				if ( !$blnDidPublish ) {
					$arrFeedsNotPublished [] = $feed['feedId'];
				}
			}
		}catch( Exception $ex ) {
			//some other occurred while we tried publish, not sure what
			//capture this and log it and then throw it as well as what info we have

			$message = array(
				'message' => 'Import of Feed Failed',
				'status' => 'error',
				'details' => 'A general exception happened during the publishing of specific feeds. Feed Ids not published:' . implode( ',', $arrFeedsNotPublished ) . ' specific message:' . $ex->getMessage() );
			$this->obj_api->_log_message( $message, "1" );

			throw new Exception( 'General exception.  Only ' . $intDidPublish . ' of ' . count( $arrFeedIds ) . ' published. These were the Feed Ids that did not publish:' . implode( ',', $arrFeedsNotPublished ) );
		}

		if ( $intDidPublish < count( $arrFeedIds ) ) {
			throw new Exception( 'Some Feeds published.  Only ' . $intDidPublish . ' of ' . count( $arrFeedIds ) . ' published. These were the Feed Ids that did not publish:' . implode( ',', $arrFeedsNotPublished ) );
		}

	}

}
