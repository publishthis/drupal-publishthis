<?php
abstract class Publishthis_API_Common {

  protected $_api_url;
  
  abstract function _request( $url, $return_errors=false );
  abstract function _get_token();
  abstract function _log_message( $message, $level='' );
  abstract function get_client_info( $params = array() );

  /**
   * Get API url value
   *
   * @return string API url
   */
  function api_url() {
    return $this->_api_url;
  }

  /*
   * Publishthis Feeds functions
   */

  /**
   * Use this method to get all of your published feeds.
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-feeds/
   */
  function get_feeds() {
    $params = array ( 'results' => 50, 'skip' => 0, 'token' => $this->_get_token() );

    $feeds = array ();

    do {
      $url = $this->_compose_api_call_url( '/feeds/', $params );

      try {
        $response = $this->_request ( $url );

        $result_list = ( array ) $response->resultList;
        if ( empty ( $result_list ) )
          break;

        foreach ( $result_list as $feed ) {
          $_feed = array ( 'feedId' => $feed->feedId,
            'displayName' => $feed->title,
            'templateId' => $feed->templateId,
            'automatedContentOn' => isset( $feed->automatedContentOn ) ? $feed->automatedContentOn : true,
            'automatedTwitterOn' => isset( $feed->automatedTwitterOn ) ? $feed->automatedTwitterOn : true );
          $feeds [] = $_feed;
        }

        usort( $feeds, array( $this, 'compare_feeds' ) );

        $params ['skip'] += $params ['results'];
      } catch ( Exception $ex ) {
        $this->_log_message( $ex->getMessage() );
        break;
      }
    }
    while( $params['skip'] < $response->totalAvailable );

    return $feeds;
  }

  /**
   * Feeds custom sort helper
   */
  function compare_feeds( $feed_1, $feed_2 ) {
    return strcasecmp( $feed_1['displayName'], $feed_2['displayName'] );
  }

  /**
   * Saved Searches custom sort helper
   */
  function compare_searches( $search_1, $search_2 ) {
    return strcasecmp( $search_1->displayName, $search_2->displayName );
  }

  /**
   * Use this method to get published feeds by ids.
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-feedsfeedids/
   */
  function get_feeds_by_ids( $feed_ids ) {
    $feeds = array ();

    if ( !is_array( $feed_ids ) ) {
      return $feeds;
    }

    $ids = implode( ',', $feed_ids );

    $params = array ( 'results' => count( $feed_ids ), 'skip' => 0, 'token' => $this->_get_token() );

    $url = $this->_compose_api_call_url( '/feeds/' . $ids, $params );

    try {
      $response = $this->_request ( $url );

      $result_list = ( array ) $response->resultList;
      if ( empty ( $result_list ) ) {
        return $feeds;
      }

      foreach ( $result_list as $feed ) {
        $_feed = array ( 'feedId' => $feed->feedId, 'displayName' => $feed->title, 'templateId' => $feed->templateId );
        $feeds [] = $_feed;
      }

      $params ['skip'] += $params ['results'];
    } catch ( Exception $ex ) {
      $this->_log_message( $ex->getMessage() );
    }

    return $feeds;
  }


  /**
   * Allows you to return content based on the Feed Id from the PublishThis system.
   * The Auto Publishing settings of the feed are used to constrain your results.
   * It will use the Source Bundles and other Source settings for filtering, as well as
   * the search criteria set in auto publishing to return automated content.
   *
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-feedid/
   */
  function get_feed_content_by_id( $feed_id, $params = array() ) {
    $params = $params + array ( 'results' => 10, 'sort' => 'most_recent', 'token' => $this->_get_token() );

    $url = $this->_compose_api_call_url( '/content/feed/'.$feed_id, $params );

    try {
      $response = $this->_request ( $url );
      return ( array ) $response->resultList;
    } catch ( Exception $ex ) {
      $this->_log_message( $ex->getMessage() );
    }
  }

  /**
   * Returns the Automated Tweets set up for a Feed from the PublishThis system.
   *
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-tweetsfeedid/
   */
  function get_tweets_by_feed_id( $feed_id, $params = array() ) {
    $params = $params + array ( 'results' => 10, 'sort' => 'most_recent', 'token' => $this->_get_token() );

    $url = $this->_compose_api_call_url( '/content/automated/tweets/feed/'.$feed_id, $params );

    try {
      $response = $this->_request ( $url );
      return ( array ) $response->resultList;
    } catch ( Exception $ex ) {
      $this->_log_message( $ex->getMessage() );
    }
  }

  /**
   * Allows you to return content based on the Feed Id from the PublishThis system.
   * The Auto Publishing settings of the feed are used to constrain your results.
   * It will use the Source Bundles and other Source settings for filtering, as well as
   * the search criteria set in auto publishing to return automated content.
   *
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-feedid/
   */
  function get_custom_data_by_feed_id( $feed_id, $params = array() ) {
    $params = $params + array ( 'token' => $this->_get_token() );

    $url = $this->_compose_api_call_url( '/feeds/'.$feed_id.'/custom-data/', $params );

    try {
      $response = $this->_request ( $url );
      return ( array ) $response->resultList;
    } catch ( Exception $ex ) {
      $this->_log_message( $ex->getMessage() );
    }
  }

  /**
   * This returns all active feed templates available for this client.
   * The feed template defines the custom fields and template sections that are available for
   * the feeds that are generated from this template.
   *
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-feedsfeed-templates/
   */
  function get_feed_templates( $params = array() ) {
    $params = $params + array ( 'token' => $this->_get_token() );

    $url = $this->_compose_api_call_url( '/feeds/feed-templates/', $params );

    try {
      $response = $this->_request ( $url );
      return ( array ) $response->resultList;
    } catch ( Exception $ex ) {
      $this->_log_message( $ex->getMessage() );
    }
  }

  /**
   * This is the primary method for developers to find newly created or published feeds.
   * Usually, developers will poll this method with a last timestamp every XX amount of
   * minutes. Depending on how many API calls you are allowed depends on how frequently
   * you will want to check for newly published content.
   *
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-feedssincetimestamp/
   */
  function get_feeds_since_timestamp( $timestamp = 0, $template_id = 0, $params = array() ) {
    if ( empty ( $timestamp ) ) {
      $timestamp = $this->_generateTimestamp();
    }
    $timestamp = number_format( $timestamp, 0, '', '' );
    $timestamp *= 1000;

    $params = $params + array ( 'results' => 20, 'skip' => 0, 'token' => $this->_get_token() );

    $feeds = array ();

    do {
      $url = $this->_compose_api_call_url( '/feeds/since/'.$timestamp, $params );

      try {     
        $response = $this->_request ( $url );

        $result_list = ( array ) $response->resultList;
        if ( empty ( $result_list ) ) {
          break;
        }

        foreach ( $result_list as $feed ) {
          $_feed = array ( 'feedId' => $feed->feedId, 'displayName' => $feed->title, 'templateId' => $feed->templateId );

          if ( $template_id && $template_id == $feed->templateId || !$template_id ) {
            $feeds[] = $_feed;
          }
        }

        $params ['skip'] += $params ['results'];

      } catch ( Exception $ex ) {
        $this->_log_message( $ex->getMessage() );
        break;
      }
    }
    while( $params['skip'] < $response->totalAvailable );

    return $feeds;
  }

  /*
   * Publishthis Topics functions
   */

  /**
   * Runs a search against our topics based on the query string name passed in.
   * This is much like if you were using our tools where we provide a Search Suggest
   * when trying to find topics.
   *
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-topicsnamename/
   */
  function get_topic_content_by_id( $topic_id, $params = array() ) {
    $params = $params + array ( 'results' => 50,
      'skip' => 0,
      'token' => $this->_get_token() );

    $url = $this->_compose_api_call_url( '/content/topic/'.$topic_id, $params );

    try {
      $response = $this->_request ( $url );
      return ( array ) $response->resultList;
    } catch ( Exception $ex ) {
      $this->_log_message( $ex->getMessage() );
    }
  }

  /**
   * Runs a search against our topics based on the query string name passed in.
   * This is much like if you were using our tools where we provide a Search Suggest
   * when trying to find topics.
   *
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-topicsnamename/
   */
  function get_topics( $name, $params = array() ) {
    $params = $params + array ( 'token' => $this->_get_token() );

    $url = $this->_compose_api_call_url( '/topics/name/'.$name, $params );

    try {
      $response = $this->_request ( $url );
      return ( array ) $response->resultList;
    } catch ( Exception $ex ) {
      $this->_log_message( $ex->getMessage() );
    }
  }

  /*
   * Publishthis Saved Searches functions
   */

  /**
   * Returns all Saved Searches for your client.
   *
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-savedsearches/
   */
  function get_saved_searches( $params = array() ) {
    $params = $params + array ( 'results' => 50, 'skip' => 0, 'token' => $this->_get_token() );

    $content = array();

    do {
      $url = $this->_compose_api_call_url( '/savedsearches/', $params );

      try {
        $response = $this->_request ( $url );

        $result_list = ( array ) $response->resultList;
        if ( empty ( $result_list ) )
          break;

        $content = array_merge( $content, $result_list );

        usort( $content, array( $this, 'compare_searches' ) );

        $params ['skip'] += $params ['results'];
      } catch ( Exception $ex ) {
        $this->_log_message( $ex->getMessage() );
        break;
      }
    }
    while( $params['skip'] < $response->totalAvailable );

    return $content;
  }

  /**
   * Allows you to return content based on Saved Search Ids from the PublishThis system.
   *
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-saved-searchids/
   */
  function get_saved_search_content( $bundle_ids, $params ) {
    $ids = implode( ',', $bundle_ids );

    $params = $params + array (
      'results'      => 10,
      'sort'         => 'most_recent',
      'token'        => $this->_get_token() );

    $url = $this->_compose_api_call_url( '/content/savedsearch/'.$ids, $params );

    try {
      $response = $this->_request ( $url );
      return ( array ) $response->resultList;
    } catch ( Exception $ex ) {
      $this->_log_message( $ex->getMessage() );
    }
  }

  /**
   * Returns the curated content from a feeds template section.
   *
   * http://docs.publishthis.com/edcenter/developers-and-admins/api-reference/api-method-contentcuratedmixmixid/
   */
  function get_curated_feed_content_by_id( $feed_id, $params = array() ) {

	$params = $params + array ( 'results' => 50, 'skip' => 0, 'token' => $this->_get_token() );

	$content = array ();

	do {
	  $url = $this->_compose_api_call_url( '/content/curated/mix/'.$feed_id, $params );

	  try {
		$response = $this->_request ( $url );

		$result_list = ( array ) $response->resultList;
		if ( empty ( $result_list ) )
		  break;

		$content = array_merge( $content, $result_list );

		$params ['skip'] += $params ['results'];
	  } catch ( Exception $ex ) {
		$this->_log_message( $ex->getMessage() );
		break;
	  }
	}
	while( $params['skip'] < $response->totalAvailable );

	return $content;
  }


  /**
   * Returns the curated content from a feeds template section.
   *
   * http://docs.publishthis.com/edcenter/developers-and-admins/api-reference/api-method-contentcuratedmixmixid/
   */
  function get_paged_curated_feed_content_by_id( $mix_id, $params = array() ) {
	$params = $params + array ('token' => $this->_get_token() );

	$content = array ();


	$url = $this->_compose_api_call_url( '/content/curated/mix/'.$mix_id, $params );

	try {
	  $response = $this->_request ( $url );

	  $result_list = ( array ) $response->resultList;
	  if ( empty ( $result_list ) ){
		return $content;
	  }

	  $content = array_merge( $content, $result_list );


	} catch ( Exception $ex ) {
	  $this->_log_message( $ex->getMessage() );
	  return array();
	}


	return $content;
  }

  /*
   * Publishthis Sections functions
   */

  /**
   * Returns the curated content from a feeds template section.
   *
   * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-contentfeedfeedidsectionsectionid/
   */
  function get_section_content( $feed_id, $section_id, $params = array() ) {
    $params = $params + array ( 'results' => 50, 'skip' => 0, 'token' => $this->_get_token() );

    $content = array ();
    
    do {
      $url = $this->_compose_api_call_url( '/content/feed/'.$feed_id.'/section/'.$section_id, $params );

      try {
        $response = $this->_request ( $url );

        $result_list = ( array ) $response->resultList;
        if ( empty ( $result_list ) )
          break;

        $content = array_merge( $content, $result_list );

        $params ['skip'] += $params ['results'];
      } catch ( Exception $ex ) {
        $this->_log_message( $ex->getMessage() );
        break;
      }
    }
    while( $params['skip'] < $response->totalAvailable );

    return $content;
  }

  /**
   * Returns token status message
   */
  function validate_token( $token ) {
    $token = str_replace(array("+","&","#"), array("\+", "\&", "\#"), $token);
    $status = array( 'valid' => true, 'message' => 'API token is valid.' );

    if ( empty( $token ) ) return array( 'valid' => false, 'message' => 'Your settings are not completed yet. You will not be able to use the plugin until you complete the settings.' );

    $params = array ( 'token' => $token );

    $url = $this->_compose_api_call_url( '/client', $params );
    
    try {
      $response = $this->_request ( $url, $return_errors=true );

      if ( !is_object( $response ) &&  empty($response) ) {
        $message = array(
          'message' => 'Invalid token',
          'status' => 'error',
          'details' => 'Token: '.$token
        );
        
        $this->_log_message( $message, "3" );
        $result = array( 'valid' => false, 'message' => 'We could not authenticate your API token, please correct the error and try again.' );
      }else {
        $result = $status;
      }
    } catch ( Exception $ex ) { 
        $message = array(
          'message' => 'Invalid token',
          'status' => 'error',
          'details' => 'Token: '.$token
        );
        
        $this->_log_message( $message, "3" );
        $result = array( 'valid' => false, 'message' => 'We could not authenticate your API token, please correct the error and try again.' );
    }   
    
    return $result;
  }

/**
 * Private methods
 */

  /**
   *  Generates timestapm value. Used for some API calls.
   */
  private function _generateTimestamp() {
    $year = ( 60 * 60 * 24 * 365 );
    $timestamp = ( time() - $year ) * 1000;
    return $timestamp;
  }


  /**
   * Compose request url
   *
   * @param string  $method API call-specific url part
   * @param array   $params Additional params to append to url
   * @return API request URL
   */
  protected function _compose_api_call_url( $method, $params=array() ) {
    
    if ( empty( $params ) ) {
      $params = array();
      $params['results'] = 50;
      $params['skip'] = 0;
      $params['token'] = $this->_get_token();
    }
    $url_params = array();
    foreach($params as $k=>$v) $url_params[] = $k.'='.$v;
    $url = $this->_api_url . $method . '?' . implode('&', $url_params );
  
    // add debug message about call
    $called_from = '';
    $backtrace = debug_backtrace();
    if ( isset( $backtrace[1]['function'] ) ) $called_from = $backtrace[1]['function'];
    
    if ( !in_array( $called_from, array( 'validate_token' ) ) ) {
      $message = array(
        'details' => 'Called from: ' . $called_from . '<br>URL: ' . $url,
        'message' => t('PublishThis API call'),
        'status' => 'info'
      );
      $this->_log_message( $message, "6" );     
    }
    return $url;
  }
}
