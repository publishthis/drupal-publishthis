<?php

/**
 * @name List of Added Publishing Action
 * @method publishthis_publishingactions
 */
function publishthis_publishingactions() {
  $output = '';
  $objAPI        = new Publishthis_API();
  $current_token = $objAPI->_get_token();

  $is_token_valid = empty($current_token) ? FALSE : TRUE;
  if (!empty($current_token)) {
	  $token_status = $objAPI->validate_token($current_token);
	  if (!isset($token_status) || $token_status['valid'] != 1) {
	    $is_token_valid = FALSE;
	  }
  }

  if (!$is_token_valid) {
	  drupal_set_message('We could not authenticate your API token, please correct the error and try again.', 'error');
	  return $output;
  }

  $header = array(
	  array('data' => t('Title'), 'field' => 'title'),
	  array('data' => t('Content Type'), 'field' => 'name'),
	  'action' => array('data' => t('Action')),
  );

  $result = db_select('pt_publishactions', 'pb')
	  ->fields('pb', array())
	  ->extend('PagerDefault')
	  ->limit(10)
	  ->extend('TableSort')
	  ->orderByHeader($header)
	  ->execute();

  $rows = array();
  foreach ($result as $row) {
	  $title = l($row->title, 'admin/config/publishthis/publishing-editactions/' . $row->id, array('attributes' => array('title' => t('Edit Actions'))));
	  $edit   = l(t('Edit'), 'admin/config/publishthis/publishing-editactions/' . $row->id, array('attributes' => array('title' => t('Edit Actions'))));
	  $delete = l(t('Delete'), 'publishthis/publishing-actions/delete/' . $row->id, array('attributes' => array("onClick" => "return confirm( '" . t('Are you sure to delete this record?') . "' );")));
	  $action = $edit . ' | ' . $delete;
	  $rows[] = array(
	    $title,
	    $row->name,
	    $action,
	  );
  }

  $output .= '<div class="wrap">
          <h2><img src="/' . drupal_get_path('module', 'publishthis') . '/assets/img/ico-32x32.png" /> <span style="line-height:33px;vertical-align:top;">Publishing Actions</span></h2>
        </div>';
  $output .= '<ul class="action-links"><li>' . l(t('Add Publishing Action'), 'admin/config/publishthis/publishing-addactions') . '</li></ul>';
  $output .= theme('table', array('header' => $header, 'rows' => $rows));
  $output .= theme('pager');

  return $output;
}

/**
 * @name Entry Form of Add New Publishing Action
 * @method publishthis_addnew_publishingactions
 * @return $form
 */
function publishthis_addnew_publishingactions($form, &$form_state) {
  global $pt_settings_value;
  // Form edit id
  $edit_id = arg(4);

  $objAPI           = new Publishthis_API();
  $template_APIdata = $objAPI->get_template_options();
  $postTypes_APIdata = $objAPI->get_post_publish_types();

  // Find first templateId value from AOI to show dropdown, if none option saved in database
  if (!empty($template_APIdata)) {
	  $default_templateId = $template_APIdata[0]->id;
  }
  if (!empty($postTypes_APIdata)) {
    $default_postTypeId = $postTypes_APIdata[0]->templateId;
  }


  // Get the list of options to populate the Post template dropdown.
  $template_options = _get_template_options($template_APIdata);
  // Get the list of options to populate the Post types dropdown.
  $postType_options = _get_post_type_options($postTypes_APIdata);

  // Edit Action
  if (arg(3) == 'publishing-editactions' && is_numeric($edit_id)) {
	$text = t('Edit Publishing Action');
	// Find record
	$result = db_select('pt_publishactions', 'pt')
	  ->fields('pt')
	  ->condition('id', $edit_id, '=')
	  ->execute()
	  ->fetchAssoc();

	// Unserialize saved form values
	if (!empty($result['value'])) {
	  // True, get form fields value
	  $edit_id  = $result['id'];
	  $pt_value = unserialize($result['value']);
	}
	else {
	  // False, initialize with empty array
	  $pt_value = array();
	}

  }
  else {
	  $text     = t('Add New Publishing Action');
	  $pt_value = array();
  }

  $form['pta_wrapper-open']   = array(
    '#markup' => '<div class="pta-form-wrapper">'
  );

  $form['pta_header']   = array(
	  '#markup' => '<div class="header"><div id="icon-edit" class="icon32 icon32-posts-publishthis_action"></div>
            <h2><span style="line-height:48px;vertical-align:top;">' . $text . '</span></h2></div>'
  );

  $form['pta_header_sublink'] = array(
	  '#markup' => '<ul class="action-links"><li>' . l('Publishing Actions', 'admin/config/publishthis/publishing-actions') . '</li></ul>'
  );

  $form['pta_action_title'] = array(
	  '#type'          => 'textfield',
	  '#title'         => t('Title'),
	  '#attributes'    => array('placeholder' => t('Enter title here')),
	  '#required'      => TRUE,
	  '#size'          => 80,
	  '#default_value' => isset($pt_value['pta_action_title']) ? $pt_value['pta_action_title'] : '',
  );

  $node_types    = node_type_get_types();
  $content_types = array();
  foreach ($node_types as $node) {
    $content_types[$node->type] = $node->name;
  }

  $form['pta_content_type'] = array(
    '#type'          => 'select',
    '#title'         => t('Your Site\'s Content Type'),
    '#options'       => $content_types,
    '#default_value' => isset($pt_value['pta_content_type']) ? $pt_value['pta_content_type'] : 'page',
  );

  // User list
  $users   = entity_load('user');
  $authors = array();
  foreach ($users as $user) {
	  unset ($authors[0]);
  	$authors[$user->uid] = $user->name;
  }
  $form['pta_publish_author'] = array(
	  '#type'          => 'select',
	  '#title'         => t('Author to Publish With'),
	  '#options'       => $authors,
	  '#default_value' => isset($pt_value['pta_publish_author']) ? $pt_value['pta_publish_author'] : '',
  );

  // If we have a value for the pt_post_type from $form_state['values'] we use
  // this both as the default value for the Post Template and also as a
  // parameter to pass to the funct that retrieves the options for the
  // PublishThis Post Type.
  if (!empty($pt_value['pta_pt_post_type'])) {
    $selected = $pt_value['pta_pt_post_type'];
  }
  else {
    $selected = isset($form_state['values']['pta_pt_post_type']) ? $form_state['values']['pta_pt_post_type'] : $default_templateId;
  }

  $form['pta_pt_post_type'] = array(
    '#type'          => 'select',
    '#title'         => t('PublishThis Post Type'),
    '#options'       => $postType_options,
    '#default_value' => isset($pt_value['pta_pt_post_type']) ? $pt_value['pta_pt_post_type'] : ''
  );

  // If we have a value for the post_template from $form_state['values'] we use
  // this both as the default value for the Post Template and also as a
  // parameter to pass to the funct that retrieves the options for the
  // Post templates.
  if (!empty($pt_value['pta_post_template'])) {
	  $selected = $pt_value['pta_post_template'];
  }
  else {
	  $selected = isset($form_state['values']['pta_post_template']) ? $form_state['values']['pta_post_template'] : $default_templateId;
  }

  $form['pta_post_template'] = array(
	  '#type'          => 'select',
	  '#title'         => t('HTML Template for Post Body'),
	  '#options'       => $template_options,
	  '#default_value' => isset($pt_value['pta_post_template']) ? $pt_value['pta_post_template'] : ''
  );
  $form['pta_post_template_url'] = array(
    '#type'          => 'textfield',
    '#title'         => t('- OR - Provide Your Own Template URL'),
    '#default_value' => isset($pt_value['pta_post_template_url']) ? $pt_value['pta_post_template_url'] : '',
    '#prefix' => '<div class="container-inline">',
    '#suffix' => '</div>',
  );

  $form['pta_content_status'] = array(
	  '#type'          => 'radios',
	  '#title'         => t('Default Published Status'),
    '#attributes'    => array('class' => array('horizontal-list')),
	  '#options'       => array('1' => t('Publish immediately'), '0' => t('Save as Draft')),
	  '#default_value' => isset($pt_value['pta_content_status']) ? $pt_value['pta_content_status'] : '1',
	  '#description'   => t(''),
  );

  $def_featured_image_option = t('Download and save Featured Section image as the Drupal "Featured Image"');
  $form['pta_featured_image'] = array(
	  '#type'          => 'checkboxes',
	  '#title'         => t('Featured Image'),
	  '#label'         => t('Post Image:'),
	  '#options'       => array(
	    'save_featured_image' => $def_featured_image_option,
	  ),
	  '#default_value' => !empty($pt_value) ? (array) $pt_value['pta_featured_image'] : array('save_featured_image' => 'save_featured_image'),
  );

  $def_ignore_img_option = "Ok to ignore original image's size?:  Original images can be smaller than your set size. If you check this, then we will ignore that original size, and resize to your set size. Smaller images will then have padding added to match the size you indicate. ";
  $form['pta_ignore_original_image'] = array(
    '#title'         => t('Featured Image Size'),
    '#type'          => 'checkboxes',
    '#options'       => array('resize_featured_image' => $def_ignore_img_option),
    '#default_value' => !empty($pt_value) ? (array) $pt_value['pta_ignore_original_image'] : array('resize_featured_image' => 'resize_featured_image'),
  );

  // Featured Image fields
  $form['pta_image_size'] = array(
    '#type'          => 'radios',
    '#options'       => array(
      'theme_default'    => t('Theme Default'),
      'custom'           => t('Width'),
      'custom_max_width' => t('Max Width'),
      'custom_up_to_max_width' => t('Up to Max Width')
    ),
    '#default_value' => !empty($pt_value) ? $pt_value['pta_image_size'] : 'theme_default',
    '#description'   => t(''),
  );

  // Width
  $form['pta_image_width'] = array(
    '#type'          => 'textfield',
    '#size'          => 5,
    '#default_value' => !empty($pt_value) ? $pt_value['pta_image_width'] : 300,
  );

  $form['pta_image_height'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Height'),
    '#size'          => 5,
    '#default_value' => !empty($pt_value) ? $pt_value['pta_image_height'] : 300,
  );

  // Max Width
  $form['pta_image_maxwidth'] = array(
    '#type'          => 'textfield',
    '#size'          => 5,
    '#default_value' => !empty($pt_value) ? $pt_value['pta_image_maxwidth'] : 300,
  );

  // Up to Max Width
  $form['pta_image_up_to_maxwidth'] = array(
    '#type'          => 'textfield',
    '#size'          => 5,
    '#default_value' => !empty($pt_value) ? $pt_value['pta_image_up_to_maxwidth'] : 0,
  );

  $form['pta_override_edits'] = array(
    '#type'          => 'radios',
    '#title'         => t('Allow PublishThis to Override Edits'),
    '#attributes'    => array('class' => array('horizontal-list')),
    '#options'       => array('0' => t('No'), '1' => 'Yes'),
    '#default_value' => isset($pt_value['pta_override_edits']) ? $pt_value['pta_override_edits'] : '1',
    '#description'   => t(''),
  );

  $form['pta_add_tags'] = array(
    '#type'          => 'radios',
    '#title'         => t('Add Tags to Content'),
    '#attributes'    => array('class' => array('horizontal-list')),
    '#options'       => array('0' => t('No'), '1' => 'Yes'),
    '#default_value' => isset($pt_value['pta_add_tags']) ? $pt_value['pta_add_tags'] : '1',
    '#description'   => t(''),
  );

  $form['pta_wrapper-close']   = array(
    '#markup' => '</div>'
  );

  $form['editId'] = array(
	  '#type'          => 'hidden',
	  '#default_value' => $edit_id,
  );

  $form['submit'] = array(
	  '#type'  => 'submit',
	  '#value' => t('Save Changes'),
  );

  return $form;
}

/**
 * Implements of hook_form_validate()
 * @desc Submit form value
 */
function publishthis_addnew_publishingactions_validate($form, &$form_state) {
  // You can have only one Publishing Action per publishThis content type
  if (isset($form_state['values']['pta_pt_post_type'])) {
    $publsihing_actions = Publishthis_Publish::get_publishing_actions();
    if (count($publsihing_actions)) {
      foreach ($publsihing_actions as $pa_id => $p_action) {
        $p_action = unserialize($p_action['value']);
        if (isset($p_action['pta_pt_post_type']) && $p_action['pta_pt_post_type'] == $form_state['values']['pta_pt_post_type']) {
          form_set_error('pta_pt_post_type', t('One <a href="/admin/config/publishthis/publishing-editactions/' . $pa_id . '">Publish Action already</a> exists for this "PublishThis Post Type". You can have only one Publishing Action per "PublishThis Post Type".'));
          break;
        }
      }
    }
  }
  // Validate url
  if (isset($form_state['values']['pta_post_template_url']) && $form_state['values']['pta_post_template_url'] != '' && !valid_url($form_state['values']['pta_post_template_url'], true)) {
    form_set_error('pta_post_template_url', t('Invalid url'));
  }
}

/**
 * Implements of hook_form_submit()
 * @desc Submit form value
 */
function publishthis_addnew_publishingactions_submit($form, &$form_state) {

  if ($form_state['clicked_button']['#value'] == "Save Changes") {

	$content_type = $form_state['values']['pta_content_type'];
	$action_title = $form_state['values']['pta_action_title'];

	$form_state['values']['import_start'] = time();

	// Serialize submit form value
	$pb_action_value = serialize($form_state['values']);

	// Insert data
	db_insert('pt_publishactions')
	  ->fields(
		array(
		  'name'        => $content_type,
		  'title'       => $action_title,
		  'value'       => $pb_action_value,
		)
	  )
	  ->execute();

	drupal_set_message(t('Publishing Action changes update successfully.'));
    drupal_goto('admin/config/publishthis/publishing-actions');
  }

}


/**
 * Helper func to populate the post template dropdown. This would normally be
 * pulling data from the API.
 *
 * @return array of options
 */
function _get_template_options($api_template = array()) {
  if (!empty($api_template)) {
	  // API feed template result
	  foreach ($api_template as $tempval) {
	    // if there is no integration restrictions or restricted to drupal
      // then take it
      if (!isset($tempval->integrations) || (isset($tempval->integrations) && in_array('drupal',$tempval->integrations))) {
        $template[$tempval->id] = $tempval->templateName;
      }
	  }
  }
  return $template;
}
/**
 * Helper func to populate the post types dropdown. This would normally be
 * pulling data from the API.
 *
 * @return array of options
 */
function _get_post_type_options($api_post_type = array()) {
  if (!empty($api_post_type)) {
    // API post types result
    foreach ($api_post_type as $tempval) {
      $post_types[$tempval->templateId] = $tempval->displayName;
    }
  }
  return $post_types;
}


/**
 * Edit Activity
 *
 * @return edit publishing actions form value
 */
function edit_publishthis_publishing_actions($form, $form_state) {
  // Render publishthis add form
  $form = publishthis_addnew_publishingactions($form, $form_state);
  return $form;
}

/**
 * Implements of hook_form_validate()
 * @desc Submit form value
 */
function edit_publishthis_publishing_actions_validate($form, &$form_state) {
  // You can have only one Publishing Action per publishThis content type
  if (isset($form_state['values']['pta_pt_post_type'])) {
    $publsihing_actions = Publishthis_Publish::get_publishing_actions();
    if (count($publsihing_actions)) {
      foreach ($publsihing_actions as $pa_id => $p_action) {
        $p_action = unserialize($p_action['value']);
        if (isset($p_action['pta_pt_post_type']) && $p_action['pta_pt_post_type'] == $form_state['values']['pta_pt_post_type'] && $pa_id != $form_state['values']['editId']) {
          form_set_error('pta_pt_post_type', t('One <a href="/admin/config/publishthis/publishing-editactions/' . $pa_id . '">Publish Action already</a> exists for this "PublishThis Post Type". You can have only one Publishing Action per "PublishThis Post Type".'));
          break;
        }
      }
    }
  }
  // Validate url
  if (isset($form_state['values']['pta_post_template_url']) && $form_state['values']['pta_post_template_url'] != '' && !valid_url($form_state['values']['pta_post_template_url'], true)) {
    form_set_error('pta_post_template_url', t('Invalid url'));
  }
}

/**
 * Implements of hook_form_submit()
 * @desc Submit Edit form value
 */
function edit_publishthis_publishing_actions_submit($form, &$form_state) {
  if ($form_state['clicked_button']['#value'] == "Save Changes") {
	  $content_type = $form_state['values']['pta_content_type'];
	  $action_title = $form_state['values']['pta_action_title'];

	  $form_state['values']['import_start'] = time();

	  // Serialize submit form value
	  $action_values = serialize($form_state['values']);
	  $edit          = $form_state['values']['editId'];

	  // Update data
	  db_update('pt_publishactions')
	    ->fields(array(
	  	  'name'        => $content_type,
	  	  'title'       => $action_title,
	  	  'value'       => $action_values,
	  	)
	    )
	    ->condition('id', $edit, '=')
	    ->execute();

	  drupal_set_message(t('Publishing Action changes update successfully.'));
	  drupal_goto('admin/config/publishthis/publishing-actions');
  }
}

/**
 * Delete Activity
 *
 * Delete single publishing actionse using delete link
 */
function delete_publishthis_publishing_actions() {

  $action    = arg(2);
  $action_id = arg(3);

  if (!empty($action_id) && $action == 'delete') {
	  $delete = db_delete('pt_publishactions')
	    ->condition('id', $action_id)
	    ->execute();
	  drupal_set_message('One record has been deleted successfully.');
	  drupal_goto("admin/config/publishthis/publishing-actions");
  }

}

// ############### THEMES #################

/**
 * Implements theme_edit_publishthis_publishing_actions().
 */
function theme_edit_publishthis_publishing_actions($variables) {
  return theme_publishthis_addnew_publishingactions($variables);
}
/**
 * Implements theme_publishthis_addnew_publishingactions().
 */
function theme_publishthis_addnew_publishingactions($variables) {
  $table_data = array();
  $output = '';

  foreach ($variables['form'] as $k => $element) {
    if (strpos($k, 'pta_') === 0) {
      //dpm($k);
      if ($k == 'pta_image_size') {
        foreach (element_children($variables['form'][$k]) as $name) {
          $table_data[$name] = array(
            $name => drupal_render($variables['form'][$k][$name]),
          );
          switch ($name) {
            case 'custom':
              if (!empty($variables['form']['pta_image_width'])) {
                $table_data[$name][$name] .= drupal_render($variables['form']['pta_image_width']);
              }
              if (!empty($variables['form']['pta_image_height'])) {
                $table_data[$name][$name] .= drupal_render($variables['form']['pta_image_height']);
              }
            break;
            case 'custom_max_width':
              if (!empty($variables['form']['pta_image_maxwidth'])) {
                $table_data[$name][$name] .= drupal_render($variables['form']['pta_image_maxwidth']);
              }
            break;
            case 'custom_up_to_max_width':
              if (!empty($variables['form']['pta_image_up_to_maxwidth'])) {
                $table_data[$name][$name] .= drupal_render($variables['form']['pta_image_up_to_maxwidth']);
              }
            break;
          }
        }
        $headers = array();
        $table = '<div class="form-item pta-image-size">'.theme_table(array('header' => $headers, 'rows' => $table_data, 'attributes' => array(), 'caption' => '', 'colgroups' => array(), 'sticky' => 0, 'empty' => '')).'</div>';
        $output .= $table;
        unset($variables['form'][$k]);
      }
      else {
        $output .= drupal_render($variables['form'][$k]);
      }
    }
  }


  $output .= drupal_render_children($variables['form']);
  return $output;
}