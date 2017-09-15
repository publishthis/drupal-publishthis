<?php
abstract class Publishthis_API_Common {

	protected $_api_url;
	
	abstract function _request( $url, $return_errors=false );
	abstract function _get_token();
	abstract function _log_message( $message, $level='' );
	abstract function get_client_info( $params = array() );

	/**
	 *  Get API url value
	 *
	 * @return string API url
	 */
	function api_url() {
		return $this->_api_url;
	}

	function get_html_templates(){
	
		$params = array();
		$params = $params + array ( 'token' => $this->_get_token() );	
	
		$url = $this->_compose_api_call_url( '/render/templates/', $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response->items;
		} catch ( Exception $ex ) {
			$this->_log_message( $ex->getMessage() );
		}
	
		return null;	
	
	}

	function get_post_html($post_id, $htmlTemplateId, $htmlTemplateUrl){
	
		$params = array();
		if (empty($htmlTemplateUrl)){
			$params = $params + array ( 'htmlTemplateId' => $htmlTemplateId,  'cacheVersion' => $this->_generateTimestamp(), 'token' => $this->_get_token() );	
		}else{
			$params = $params + array ( 'htmlTemplateId' => $htmlTemplateId, 'htmlTemplateURL' => $htmlTemplateUrl, 'cacheVersion' => $this->_generateTimestamp(), 'token' => $this->_get_token() );	
		}
		
		$url = $this->_compose_api_call_url( '/render/post/'.$post_id, $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response->items;
		} catch ( Exception $ex ) {
			$this->_log_message( $ex->getMessage() );
		}
	
		return null;	
	
	}

	
	function get_basic_post_data($post_id, $params = array()){
	
		$params = $params + array ( 'includeFeatured' => true, 'includeItems' => false, 'token' => $this->_get_token() );	
	
		$url = $this->_compose_api_call_url( '/content/post/'.$post_id, $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response;
		} catch ( Exception $ex ) {
			$this->_log_message( $ex->getMessage() );
		}
	
		return null;
	}

	function get_post_data($post_id, $params = array()){
	
		$params = $params + array ( 'token' => $this->_get_token() );	
	
		$url = $this->_compose_api_call_url( '/content/post/'.$post_id, $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response;
		} catch ( Exception $ex ) {
			$this->_log_message( $ex->getMessage() );
		}
	
		return null;
	}

	function get_post_publish_types( $params = array() ) {
		$params = $params + array ( 'token' => $this->_get_token() );

		$url = $this->_compose_api_call_url( '/publishtypes/posts', $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response->items;
		} catch ( Exception $ex ) {
			$this->_log_message( $ex->getMessage() );
		}
	}


	/**
	 * Returns token status message
	 */
	function validate_token( $token ) {

		$status = array( 'valid' => true, 'message' => 'API token is valid' );

		if ( empty( $token ) ) return array( 'valid' => false, 'message' => 'Your settings are not completed yet. You will not be able to use the plugin until you complete the settings.' );

		$params = array ( 'token' => $token );

		$url = $this->_compose_api_call_url( '/client', $params );

		try {
			$response = $this->_request ( $url, $return_errors=true );

			if ( !isset( $response ) ) {
				$message = array(
					'message' => 'Invalid token',
					'status' => 'error',
					'details' => 'Token: '.$token
				);
				$this->_log_message( $message, "2" );
				return array( 'valid' => false, 'message' => 'We could not authenticate your API token, please correct the error and try again.' );
			}

		} catch ( Exception $ex ) {
			$this->_log_message( $ex->getMessage() );
		}

		return $status;
	}

	/*
	 * Protected methods
	 */

	/**
	 *  Generates timestapm value. Used for some API calls.
	 */
	protected function _generateTimestamp() {
		//$year = ( 60 * 60 * 24 * 365 );
		//$timestamp = ( time() - $year ) * 1000;
		//return $timestamp;
		
		return rand(1, 999999);
		
	}

	/**
	 *   Compose request url
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

		$url = $this->_api_url . $method . '?' . http_build_query( $params );

		// add debug message about call
		$called_from = '';
		$backtrace = debug_backtrace();
		if ( isset( $backtrace[1]['function'] ) ) $called_from = $backtrace[1]['function'];

		if ( !in_array( $called_from, array( 'validate_token' ) ) ) {
			$message = array(
				'details' => 'Called from: ' . $called_from . '<br/>URL: ' . $url,
				'message' => 'PublishThis API call',
				'status' => 'info'
			);
			$this->_log_message( $message, "2" );
		}
		return $url;
	}
}
