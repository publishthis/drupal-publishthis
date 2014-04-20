<?php

/**
 * Render Form for the publishthis log messages. 
 */
function publishthis_log_messages() {
  $output = drupal_get_form('publishthis_log_messages_form');
  return $output;

}

/**
 * Form for publishthis log messages form.
 * 
 * Implements hook_form 
 * @see publishthis_menu()
 * @see publishthis_log_messages_form_validate()
 * @see publishthis_log_messages_form_submit()
 * @ingroup forms
 */
function publishthis_log_messages_form() {
  
  global $pt_settings_value;
  
  // Watchdog Severity Level
  $severity = watchdog_severity_levels();  

  $header_text = '';
  $header_text .= '<div class="wrap"><h2>
                    <img src="/'.drupal_get_path('module', 'publishthis').'/assets/img/ico-32x32.png" />
                    <span style="line-height:33px;vertical-align:top;">Publishing Log Messages</span></h2>
                  </div>';
  
  // Settings Logging Options Value
  if( !empty($pt_settings_value['debug']) ) {
    
    if( $pt_settings_value['debug'] == 3 ) {
      $logging_val = 3;
      $cond = '=';
    }
    else{
      $logging_val = 0;
      $cond = '!=';
    }
    $header_text .= '<ul class="action-links"><li>'.l('Export Logs', 'publishthis/log-messages/export/'.$logging_val).'</li></ul>';

  }else{
    $logging_val = 0;
    $cond = '=';
  }
  
  $header = array(
    'wid' => array( 'data' => t('Log Id'), 'field' => 'wid', 'sort'=>'desc' ),
    'severity' => array( 'data' => t('Status'), 'field' => 'severity', 'sort'=>'desc' ),
    'message' => array( 'data' => t('Message'), 'field' => 'message', 'sort'=>'desc' ),
    'timestamp' => array( 'data' => t('Date'), 'field' => 'timestamp', 'sort'=>'desc' ),
    'action'  => array( 'data'=>t('Action') ),
  );
 
  $rows = array();
  
  $logs = db_select('watchdog', 'w')
    ->fields('w')
    ->condition('w.type', 'publishthis')
    ->condition('w.severity', $logging_val, $cond)
    ->extend('PagerDefault')
    ->limit(20)
    ->extend('TableSort')
    ->orderByHeader($header)
    ->execute();
  
  foreach($logs as $watchLog) {

    $rows[$watchLog->wid] = array(
      'wid'       => $watchLog->wid,
      'severity'  => $severity[$watchLog->severity],
      'message'   => $watchLog->message . '<br/>' . l( 'view details', 'admin/reports/event/'.$watchLog->wid, array('attributes' => array('target' => "_blank")) ),
      'timestamp' => date( "m/d/Y - H:m:s", $watchLog->timestamp ),
      'action'    => l('Delete','publishthis/log-messages/delete/'.$watchLog->wid , array( 'attributes' => array( "onClick" => "return confirm( '".t( 'Are you sure to delete this record?' )."' );" )))
    );   
  
  }
 
  $form['messagelogs'] = array(
    '#type' => 'tableselect',
    '#header' => $header,
    '#options' => $rows,
    '#empty' => t('No Records available.'),
    '#prefix' => $header_text,
    '#attributes'=>'',
  );
  $form['pager'] = array('#theme' => 'pager','#weight' =>2,);

  $form['delete'] = array(
    '#type' => 'submit',
    '#value' => t('Delete Selected')
  );

  return $form;
}


/**
 * Implements hook_form_validate
 * 
 * Form validation handler for publishthis_log_messages_form()
 * @desc Validate checkbox activity 
 */
function publishthis_log_messages_form_validate($form, &$form_state) {
  
  if($form_state['clicked_button']['#value'] =='Delete Selected') {
    // Set flag
    $selected = false;  
    
    if( !empty($form_state['values']['messagelogs']) ) {
      
      foreach($form_state['values']['messagelogs'] as $val){
        if($val !=0 ){
          $selected = true;
          break;
        }
      }      
      if(!$selected){
        form_set_error('messagelogs', t("Please select checkbox for delete message."));
      }
    
    }    
  }
    
}


/** 
 * Form submission handler for publishthis_log_messages_form()
 * 
 * Implements hook_form_submit
 * @desc Delete Log messages using checkboxs selection
 */
function publishthis_log_messages_form_submit($form, &$form_state) {
  
  if($form_state['clicked_button']['#value'] =='Delete Selected') {        
    
    if(!empty($form_state['values']['messagelogs'])) {
        
      $wid = $form_state['values']['messagelogs'];  
     
      foreach($wid as $delID) {
       if( $delID > 0 ) { 
          
         $delete = db_delete('watchdog') -> condition('wid',$delID) -> execute();
         // Set flag
         $delmsg ='delete';
          
        }           
      }
      if($delmsg =='delete'){
        drupal_set_message( t("Selected messages have been deleted successfully.") );
      }
     
      drupal_goto("admin/config/system/publishthis/log-messages");      
    
    }
    
  } 
}

/**
 * Delete Activity
 *  
 * Delete single log message using delete link 
 */
function publishthis_log_messages_delete() {
  
  $wid = arg(3);
  
  if(!empty($wid)){
    
    $delete =db_delete('watchdog') ->condition('wid', $wid) ->execute();      
    drupal_set_message('One record has been deleted successfully.');
    drupal_goto("admin/config/system/publishthis/log-messages");
    
  }
  
}

/**
 * Export Publishthis log activity
 */
function publishthis_log_messages_export() {
  global $pt_settings_value;
  // Watchdog Severity Level
  $severity = watchdog_severity_levels();   
  // Severity Type
  $sev_type = arg(3);
  if( $sev_type == 3 ) {
    $cond = '=';
  }
  else{
    $sev_type = 0;
    $cond = '!=';
  }
  // File name
  $file = "PublishingLogs";

  $separator = "\t"; // separate column in txt

    $output = "";
    // Create header
    $output .= "Log Id";
    $output .= $separator;
    $output .= "Status";
    $output .= $separator;
    $output .= "Message";
    $output .= $separator;
    $output .= "Date";
    $output .= "\n";
    
    
    $logs = db_select('watchdog', 'w')
      ->fields('w')
      ->condition('w.type', 'publishthis')
      ->condition('w.severity', $sev_type, $cond)
      ->orderBy('wid', 'asc')
      ->execute();
    
    foreach($logs as $watchLog) {

      $output .= $watchLog->wid;
      $output .= $separator;

      $output .= $severity[$watchLog->severity];
      $output .= $separator;

      $output .= $watchLog->message;
      $output .= $separator;

      $output .= date( "m/d/Y - H:m:s", $watchLog->timestamp );
      $output .= "\n";

    }

    $output .= "\n\n";
    $output .= "Settings:\n";
    foreach($pt_settings_value as $setting_key => $setting_val) { 
      $output .= $setting_key . ": " . $setting_val . "\n";
    }

    $output .= "\n\n";
    $output .= "Publishing Actions:\n";

    $objPublish = new Publishthis_Publish();
    $actions = $objPublish->get_publishing_actions();
    foreach( $actions as $pubAction) {
      $output .= "ID: " . $pubAction['id'] . "\n";
      $action_meta = unserialize($pubAction['value']);

      foreach( $action_meta as $action_meta_key => $action_meta_val) {
        $output .= $action_meta_key . ": " . $action_meta_val . "\n";
      }
      $output .= "\n";
    }

    $output .= "\n\n";
    $output .= "Style Options:\n";
    $pt_styles = unserialize(variable_get('pt_style_options'));
    $styles_log = '';
    if($pt_styles) {
      foreach($pt_styles as $k=>$style) {
        $styles_log .= "\n" . $k . ":\n";

        foreach($style as $k_style=>$v_style) {
          $styles_log .= $k_style."=".$v_style."\n";
        }
      }
    }
    else {
      $styles_log .= "\n\nNo style options saved\n";
    }
    $output .= $styles_log;
  
  $filename = $file."-".time();
  // header
  header("Content-type: application/octet-stream");
  header( "Content-disposition: filename=".$filename.".txt");
  // Generate output
  print $output;
  exit;
}
