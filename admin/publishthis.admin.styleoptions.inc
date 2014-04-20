<?php

/**
 * @name Render Entry Form of PublishThis Style Options
 * @method publishthis_styleoptions
 */
function publishthis_styleoptions($form , &$form_state) {
  $output = '';
  
  $objAPI = new Publishthis_API();
  $current_token = $objAPI->_get_token();

  $is_token_valid = empty($current_token) ? false : true;
  if( !empty($current_token) ) {
    $token_status = $objAPI->validate_token( $current_token );
    if( !isset($token_status) || $token_status['valid'] != 1 ) {
      $is_token_valid = false;
    }
  }

  if( !$is_token_valid ) {
    drupal_set_message('We could not authenticate your API token, please correct the error and try again.', 'error');
    return $output;
  }
  
  // ColorPicker CSS
  drupal_add_css(drupal_get_path('module', 'publishthis') . '/assets/colorpicker/css/colorpicker.css', array('group' => CSS_DEFAULT, 'every_page' => TRUE));
  drupal_add_css(drupal_get_path('module', 'publishthis') . '/assets/colorpicker/css/layout.css', array('group' => CSS_DEFAULT, 'every_page' => TRUE));
  
  // ColorPicker JS
  drupal_add_js(drupal_get_path('module', 'publishthis') . '/assets/colorpicker/js/colorpicker.js', array('group' => JS_DEFAULT, 'every_page' => TRUE));
  drupal_add_js(drupal_get_path('module', 'publishthis') . '/assets/colorpicker/js/eye.js', array('group' => JS_DEFAULT, 'every_page' => TRUE));
  drupal_add_js(drupal_get_path('module', 'publishthis') . '/assets/colorpicker/js/utils.js', array('group' => JS_DEFAULT, 'every_page' => TRUE));
  drupal_add_js(drupal_get_path('module', 'publishthis') . '/assets/colorpicker/js/layout.js', array('group' => JS_DEFAULT, 'every_page' => TRUE));
  
  // custom js
  drupal_add_js(drupal_get_path('module', 'publishthis') . '/assets/js/publishthis.style.js');
  
  // Form Heading
  $form['pt_style']['header'] = array(
    '#markup' => '<div class="wrap"><h2><img src="/'.drupal_get_path('module', 'publishthis').'/assets/img/ico-32x32.png" /> <span style="line-height:33px;vertical-align:top;">Style Options</span></h2></div>'
  );

  // unserialize saved values
  $pt_value = unserialize(variable_get('pt_style_options'));
  
  // Title --
  $form['pt_style_title'] = _style_options_fields('pt_style_title','Title',$pt_value);
  
  // Summary --
  $form['pt_style_summary'] = _style_options_fields('pt_style_summary','Summary',$pt_value);
  
  // Publish Date --
  $form['pt_style_publishDate'] = _style_options_fields('pt_style_publishDate','Publish Date',$pt_value);
  
  // Annotation Body Text --
  $form['pt_style_annotation_body'] = _style_options_fields('pt_style_annotation','Annotation Body Text',$pt_value);
  
  // Annotation Title --
  $form['pt_style_annotation_title'] = _style_options_fields('pt_style_annotation_title','Annotation Title',$pt_value);
 
  // Read More --
  $form['pt_style_readmore'] = _style_options_fields('pt_style_readmore','Read More',$pt_value);
  
  // Form Submit button
  $form['submit'] = array(
    '#type' => 'submit',
    '#weight' => 3,
    '#value' => t('Save Changes'),
    '#submit' => array('publishthis_styleoptions_submit')
  );
  
  return $form;
}

/**
* Implements of hook_form_submit()
* @desc Submit form value
*/
function publishthis_styleoptions_submit($form, &$form_state) {
    
  if($form_state['clicked_button']['#value'] == "Save Changes") {
    // Serialize submit form value
    $style_formValue = serialize($form_state['values']);
    
    // Set varaible name, value pair
    variable_set('pt_style_options', $style_formValue);
    
    drupal_set_message(t('Style options changes update successfully.'));
    
    drupal_goto('admin/config/system/publishthis/style-options');
  }
}

/**
 * @name Entry Form of PublishThis Style Options
 * @method _style_options_fields
*/
function _style_options_fields($form_type,$title,$pt_value) {  

  // Font Size Custom options
  $font_size_custom = array();
  for($f=6; $f<=54; $f++) {
    $font_size_custom[$f.'pt'] = $f.'pt';
  }

  // Border Size Custom options
  $border_size_custom = array();
  for($b=1; $b<=25; $b++) {
    $border_size_custom[$b] = $b;
  }

  // Margin, Padding Custom options
  $options = array();
  for($option=0; $option<=50; $option++) {
    $options[$option] = $option;
  }
  
  $form[$form_type] = array(
    '#type' =>'fieldset',
    '#title' => t($title),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
    '#tree' =>TRUE,
  );
  $form[$form_type]['font'] = array(
    '#type' => 'radios',
    '#title' => t('Font'),
    '#attributes' => array('class' => array('horizontal-list')),
    '#options' => array('default' => t('Default'), 'custom' => ''),
    '#default_value' => isset($pt_value[$form_type]['font'])?$pt_value[$form_type]['font']:'default',
    '#description' => t(''),
    '#prefix' => '<div class ="field_inline font">',
  );
  $form[$form_type]['font_custom'] = array(
    '#type' => 'select',
    '#options' => array('Arial' => 'Arial', 'Arial Black' => 'Arial Black',  'Comic Sans MS' => 'Comic Sans MS',
        'Courier New' => 'Courier New', 'Georgia' => 'Georgia',  'Times New Roman' => 'Times New Roman',
        'Trebuchet MS' => 'Trebuchet MS', 'Verdana' => 'Verdana','Andale Mono' => 'Andale Mono',
        'Helvetica' => 'Helvetica'),
    '#default_value' => isset($pt_value[$form_type]['font_custom'])?$pt_value[$form_type]['font_custom']:'',
    '#suffix' => '</div>',
  );
  $form[$form_type]['font_size'] = array(
    '#type' => 'radios',
    '#title' => t('Font Size'),
    '#attributes' => array('class' => array('horizontal-list')),
    '#options' => array('default' => t('Default'), 'custom' => ''),
    '#default_value' => isset($pt_value[$form_type]['font_size'])?$pt_value[$form_type]['font_size']:'default',   
    '#description' => t(''),
    '#prefix' => '<div class ="field_inline font_size">',
  );
  $form[$form_type]['font_size_custom'] = array(
    '#type' => 'select',
    '#options' => $font_size_custom,
    '#default_value' => isset($pt_value[$form_type]['font_size_custom'])?$pt_value[$form_type]['font_size_custom']:'',
    '#suffix' => '</div>',
  );
  $form[$form_type]['font_color'] = array(
    '#type' => 'radios',
    '#title' => t('Font Color'),
    '#attributes' => array('class' => array('horizontal-list ')),    
    '#options' => array('default' => t('Default'), 'custom' => ''),
    '#default_value' => isset($pt_value[$form_type]['font_color'])?$pt_value[$form_type]['font_color']:'default',
    '#description' => t(''),
    '#prefix' => '<div class ="field_inline">'

  );
  $form[$form_type]['font_color_custom'] = array(
    '#type' => 'textfield',
    '#title' => t('Select Color'),
    '#size' => 20,
    '#attributes' => array('id' => array('colorpickerField1')),
    '#default_value' => isset($pt_value[$form_type]['font_color_custom'])?$pt_value[$form_type]['font_color_custom']:'00ff00',
    '#suffix' => '</div>',
  );
  $form[$form_type]['font_style'] = array(
    '#type' => 'radios',
    '#title' => t('Font Style'),
    '#attributes' => array('class' => array('horizontal-list')),    
    '#options' => array('default' => t('Default'), 'custom' => ''),
    '#default_value' => isset($pt_value[$form_type]['font_style'])?$pt_value[$form_type]['font_style']:'default',
    '#description' => t(''),
    '#prefix' => '<div class ="field_inline font_style">',
  );
  $form[$form_type]['font_style_custom'] = array(
    '#type' => 'checkboxes',
    '#options' => array('bold' => t('Bold'), 'italic' => 'Italic', 'underline' => 'Underline'),
    '#default_value' =>  !empty($pt_value)? (array) $pt_value[$form_type]['font_style_custom'] : array(),
    '#suffix' => '</div>',
  );
  $form[$form_type]['font_border_size'] = array(
    '#type' => 'radios',
    '#title' => t('Border Size'),
    '#attributes' => array('class' => array('horizontal-list')),
    '#options' => array('default' => t('Default'), 'custom' => ''),    
    '#default_value' => isset($pt_value[$form_type]['font_border_size'])?$pt_value[$form_type]['font_border_size']:'default',
    '#description' => t(''),
    '#prefix' => '<div class="field_inline font_border_size">'
  );
  $form[$form_type]['font_border_size_custom'] = array(
    '#type' => 'select',
    '#options' => $border_size_custom,
    '#default_value' => isset($pt_value[$form_type]['font_border_size_custom'])?$pt_value[$form_type]['font_border_size_custom']:'',
    '#suffix' => '</div>',
  );
  
  $form[$form_type]['border_color'] = array(
    '#type' => 'radios',
    '#title' => t('Border Color'),    
    '#options' => array('default' => t('Default'), 'custom' => ''),
    '#default_value' => isset($pt_value[$form_type]['border_color'])?$pt_value[$form_type]['border_color']:'default',
    '#attributes' => array('class' => array('horizontal-list')),
    '#description' => t(''),
    '#prefix' => '<div class ="field_inline">'
  );
  $form[$form_type]['border_color_custom'] = array(
    '#type' => 'textfield',
    '#title' => 'Select Color',
    '#size' => 20,
    '#attributes' => array('id' => array('colorpickerField2')),
    '#default_value' => isset($pt_value[$form_type]['border_color_custom'])?$pt_value[$form_type]['border_color_custom']:'00ff00',
    '#suffix' => '</div>',
  );

  $form[$form_type]['bg_color'] = array(
    '#type' => 'radios',
    '#title' => t('Background Color'),
    '#attributes' => array('class' => array('horizontal-list')),
    '#options' => array('default' => t('Default'), 'custom' => ''),
    '#default_value' => isset($pt_value[$form_type]['bg_color'])?$pt_value[$form_type]['bg_color']:'default',
    '#description' => t(''),
    '#prefix' => '<div class ="field_inline">'
  );
  $form[$form_type]['bg_color_custom'] = array(
    '#type' => 'textfield',
    '#title' => 'Select Color',
    '#size' => 20,
    '#attributes' => array('id' => array('colorpickerField3')),
    '#default_value' => isset($pt_value[$form_type]['bg_color_custom'])?$pt_value[$form_type]['bg_color_custom']:'00ff00',
    '#suffix' => '</div>',
  );

  $form[$form_type]['margins'] = array(
    '#type' => 'radios',
    '#title' => t('Margins'),
    '#attributes' => array('class' => array('horizontal-list')),
    '#options' => array('default' => t('Default'), 'custom' => ''),
    '#default_value' => isset($pt_value[$form_type]['margins'])?$pt_value[$form_type]['margins']:'default',
    '#description' => t(''),
    '#prefix' => '<div class ="field_inline margins">'
  );
  $form[$form_type]['margin_left'] = array(
    '#type' => 'select',
    '#title' => t('Left'),
    '#options' => $options,
    '#default_value' => isset($pt_value[$form_type]['margin_left'])?$pt_value[$form_type]['margin_left']:'',
  );
  $form[$form_type]['margin_right'] = array(
    '#type' => 'select',
    '#title' => t('Right'),
    '#options' => $options,
    '#default_value' => isset($pt_value[$form_type]['margin_right'])?$pt_value[$form_type]['margin_right']:'',
  );
  $form[$form_type]['margin_top'] = array(
    '#type' => 'select',
    '#title' => t('Top'),
    '#options' => $options,
    '#default_value' => isset($pt_value[$form_type]['margin_top'])?$pt_value[$form_type]['margin_top']:'',
  );
  $form[$form_type]['margin_bottom'] = array(
    '#type' => 'select',
    '#title' => t('Bottom'),
    '#options' => $options,
    '#suffix' => '</div>',
    '#default_value' => isset($pt_value[$form_type]['margin_bottom'])?$pt_value[$form_type]['margin_bottom']:'',
  );

  $form[$form_type]['paddings'] = array(
    '#type' => 'radios',
    '#title' => t('Paddings'),
    '#attributes' => array('class' => array('horizontal-list')),
    '#options' => array('default' => t('Default'), 'custom' => ''),
    '#default_value' => isset($pt_value[$form_type]['paddings'])?$pt_value[$form_type]['paddings']:'default',
    '#description' => t(''),
    '#prefix' => '<div class ="field_inline paddings">'
  );
  $form[$form_type]['padding_left'] = array(
    '#type' => 'select',
    '#title' => t('Left'),
    '#options' => $options,
    '#default_value' => isset($pt_value[$form_type]['padding_left'])?$pt_value[$form_type]['padding_left']:'',
  );
  $form[$form_type]['padding_right'] = array(
    '#type' => 'select',
    '#title' => t('Right'),
    '#options' => $options,
    '#default_value' => isset($pt_value[$form_type]['padding_right'])?$pt_value[$form_type]['padding_right']:'',
  );
  $form[$form_type]['padding_top'] = array(
    '#type' => 'select',
    '#title' => t('Top'),
    '#options' => $options,
    '#default_value' => isset($pt_value[$form_type]['padding_top'])?$pt_value[$form_type]['padding_top']:'',
  );
  $form[$form_type]['padding_bottom'] = array(
    '#type' => 'select',
    '#title' => t('Bottom'),
    '#options' => $options,
    '#default_value' => isset($pt_value[$form_type]['padding_bottom'])?$pt_value[$form_type]['padding_bottom']:'',
    '#suffix' => '</div>',
  );
  
  return $form;
}
