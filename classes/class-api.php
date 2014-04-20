<?php
class Publishthis_API extends Publishthis_API_Common {

  /**
   * Publishthis_API constructor
   */
  function __construct() {    
    global $pt_settings_value;
   
    include drupal_get_path('module', 'publishthis') . '/publishthis-settings.php';

    switch ( $pt_settings_value['api_version'] ) {
    case '3.0' :
      $this->_api_url = PT_API_URL_3_0;
      break;
    default :
      $this->_api_url = PT_API_URL_3_0;
      break;
    }
  }
  
  /*
  * return token saved value
  */
  public function _get_token() {
    
    global $pt_settings_value;
        
    $saved_token = $pt_settings_value['api_token'];
    
    return $saved_token;
  }
  
  /*
  * desc: send message to PublishThis log
  */
  public function _log_message( $message, $level='' ) {
    if( !is_array($message) ) {
      $message = array('message' => $message, 'details' => '', 'status' => 'info');
    }
    switch ($message['status']) {
      case 'warn':
        $severity = WATCHDOG_WARNING;
        break;

      case 'error':
        $severity = WATCHDOG_ERROR;
        break;
      
      case 'info':
      default:
        $severity = WATCHDOG_NOTICE;
        break;
    }
    // Messages log
    watchdog('publishthis', '<b>'.@$message['message'].'</b><br>'.@$message['details'] , $message, $severity, $link = NULL);
  
  }

  /**
   * Returns Publishthis client info
   */
  public function get_client_info( $params = array() ) {

    $params = $params + array ( 'token' => $this->_get_token() );
    
    $url = $this->_compose_api_call_url( '/client', $params );
    
    try {
       $response = $this->_request ( $url );
     } catch ( Exception $ex ) {
       $this->_log_message( $ex->getMessage (), "7" );
       $response = null;
     }
    
    return $response;
  } 
  
  /**
   *   process API request
   * we call our API method, then return the correct JSON object or thrown an exception
   * if the API had an error, or there was an error in parsing, or there was an error in
   * the fetch call itself.
   */
  public function _request( $url, $return_errors = false ) {
    // check token setup
    $query_str = parse_url($url, PHP_URL_QUERY);
    parse_str($query_str, $query_params);
    if( empty($query_params['token']) ) {
      return null;
    }

    // process request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $data = curl_exec($ch);

    // Check HTTP Code
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close cURL Resource
    curl_close($ch);

    // check for failure
    if ( !isset($data) || $status != 200 ) {      
      $message = array(
        'message' => 'PublishThis API error',
        'status' => 'error',
        'details' => 'URL: '.$url
      );
      $this->_log_message( $message, "2" );
      throw new Exception( "PublishThis API error ({$url})." );
    }

    $json = "";

    try {
      $json = json_decode( $data );

      if ( ! $json ) {
        throw new Exception( "inner JSON conversion error ({$url})." );
      }

    } catch ( Exception $ex ) {
      // try utf encoding it and then capturing it again.
      // we have seen problems in some wordpress/server installs where the json_decode
      //doesn't actually like the utf-8 response that is returned
      $message = array(
        'message' => 'Issue in decoding the json',
        'status' => 'error',
        'details' => $ex->getMessage ()
      );
      $this->_log_message( $message, "2" );

      try {
        $tmpBody = utf8_encode( $data );
        $json = json_decode( $tmpBody );
      } catch ( Exception $exc ) {
        $message = array(
          'message' => 'Issue in utf8 encoding and then decoding the json',
          'status' => 'error',
          'details' => $ex->getMessage ()
        );
        $this->_log_message( $message, "2" );

        throw new Exception( "Your Drupal install is not correctly decoding our API response, please contact your client service representative" );
      }
    }

    if ( ! $json ) {
      throw new Exception( "JSON conversion error ({$url})." );
    }

    return $status == 200 ? $json->resp->data : null;

  }

  
  /**
   * Gets an option value by key
   *
   * @param unknown $key Option key
   * @return Option value
   */
  public function get_template_option( $params = array() ) {
    return $this->get_feed_templates( $params );
  }
}
