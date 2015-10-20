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
	array('data' => t('Content Type Format'), 'field' => 'format_type'),
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

	$title = l($row->title, 'admin/config/system/publishthis/publishing-editactions/' . $row->id, array('attributes' => array('title' => t('Edit Actions'))));

	$edit   = l(t('Edit'), 'admin/config/system/publishthis/publishing-editactions/' . $row->id, array('attributes' => array('title' => t('Edit Actions'))));
	$delete = l(t('Delete'), 'publishthis/publishing-actions/delete/' . $row->id, array('attributes' => array("onClick" => "return confirm( '" . t('Are you sure to delete this record?') . "' );")));

	$action = $edit . ' | ' . $delete;

	$rows[] = array(
	  $title,
	  $row->format_type,
	  $row->name,
	  $action,
	);
  }

  $output .= '<div class="wrap">
          <h2><img src="/' . drupal_get_path('module', 'publishthis') . '/assets/img/ico-32x32.png" /> <span style="line-height:33px;vertical-align:top;">Publishing Actions</span></h2>
        </div>';
  $output .= '<ul class="action-links"><li>' . l(t('Add Publishing Action'), 'admin/config/system/publishthis/publishing-addactions') . '</li></ul>';
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
  $edit_id = arg(5);

  $objAPI           = new Publishthis_API();
  $template_APIdata = $objAPI->get_template_option();

  // Find first templateId value from AOI to show dropdown, if none option saved in database
  if (!empty($template_APIdata)) {
	$default_templateId = $template_APIdata[0]->templateId;
  }

  // Get the list of options to populate the Feed template dropdown.
  $template_options = _get_feed_template_options($template_APIdata);

  // Edit Action
  if (arg(4) == 'publishing-editactions' && is_numeric($edit_id)) {
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

  $form['#prefix']  = '<div id="form-wrapper">';
  $form['#suffix '] = '</div>';
  $form['header']   = array(
	'#markup' => '<div class="wrap"><div id="icon-edit" class="icon32 icon32-posts-publishthis_action"></div>
            <h2><span style="line-height:48px;vertical-align:top;">' . $text . '</span></h2></div>'
  );

  $form['header_sublink'] = array(
	'#markup' => '<ul><li>' . l('Publishing Actions', 'admin/config/system/publishthis/publishing-actions') . '</li></ul>'
  );

  $form['action_title'] = array(
	'#type'          => 'textfield',
	'#title'         => t('Title'),
	'#attributes'    => array('placeholder' => t('Enter title here')),
	'#required'      => TRUE,
	'#size'          => 80,
	'#default_value' => isset($pt_value['action_title']) ? $pt_value['action_title'] : '',
  );

  $group        = 'options';
  $form[$group] = array(
	'#type'        => 'fieldset',
	'#title'       => t('Options'),
	'#collapsible' => TRUE,
	'#attributes'  => array('class' => array('pt-options')),
  );

  $interval = array(
	'60'    => '1 min',
	'300'   => '5 min',
	'600'   => '10 min',
	'900'   => '15 min',
	'1800'  => '30 min',
	'2700'  => '45 min',
	'3600'  => '60 min',
	'7200'  => '2 hrs',
	'21600' => '6 hrs',
	'43200' => '12 hrs',
	'86400' => '24 hrs'
  );

  $poll_int_type = $pt_settings_value['curated_publish'] == 'publishthis_import_from_manager' ? 'hidden' : 'select';

  $form[$group]['poll_interval'] = array(
	'#type'          => $poll_int_type,
	'#title'         => t('Poll Interval'),
	'#options'       => $interval,
	'#visible'       => FALSE,
	'#default_value' => isset($pt_value['poll_interval']) ? $pt_value['poll_interval'] : '60',
  );
  // User list
  $users   = entity_load('user');
  $authors = array();
  foreach ($users as $user) {
	unset ($authors[0]);
	$authors[$user->uid] = $user->name;
  }
  $form[$group]['publish_author'] = array(
	'#type'          => 'select',
	'#title'         => t('Publish Author'),
	'#options'       => $authors,
	'#default_value' => isset($pt_value['publish_author']) ? $pt_value['publish_author'] : '',
  );

  // If we have a value for the feed_template from $form_state['values'] we use
  // this both as the default value for the Feed Template and also as a
  // parameter to pass to the funct that retrieves the options for the
  // Template Section.
  if (!empty($pt_value['feed_template'])) {
	$selected = $pt_value['feed_template'];
  }
  else {
	$selected = isset($form_state['values']['feed_template']) ? $form_state['values']['feed_template'] : $default_templateId;
  }
// pa($pt_value,1);
  $form[$group]['feed_template'] = array(
	'#type'          => 'select',
	'#title'         => 'Feed Template',
	'#options'       => $template_options,
	'#default_value' => $selected,
	// Bind an ajax callback to the change event (which is the default for the
	// select form type) of the first dropdown. It will replace the second
	// dropdown when rebuilt
	'#ajax'          => array(
	  'event'    => 'change',
	  'callback' => 'ajax_dependent_dropdown_callback',
	  'wrapper'  => 'form-wrapper',
	),
  );

  $form[$group]['template_section'] = array(
	'#type'          => 'select',
	'#title'         => t('Template Section'),
	// The entire enclosing div created here gets replaced when feed_template
	// is changed.
	'#prefix'        => '<div id="template-section-replace">',
	'#suffix'        => '</div>',
	// when the form is rebuilt during ajax processing, the $selected variable
	// will now have the new value and so the options will change
	'#options'       => _ajax_get_template_section_dropdown_options($selected, $template_APIdata),
	'#default_value' => isset($pt_value['template_section']) ? $pt_value['template_section'] : ''
  );

  $node_types    = node_type_get_types();
  $content_types = array();
  foreach ($node_types as $node) {
	$content_types[$node->type] = $node->name;
  }

  $form[$group]['content_type'] = array(
	'#type'          => 'select',
	'#title'         => t('Content Type'),
	'#options'       => $content_types,
	'#default_value' => isset($pt_value['content_type']) ? $pt_value['content_type'] : 'page',
  );

  $form[$group]['format_type'] = array(
	'#type'          => 'radios',
	'#title'         => t('Content Type Format'),
	'#options'       => drupal_map_assoc(array(t('Individual'), t('Digest'))),
	'#default_value' => isset($pt_value['format_type']) ? $pt_value['format_type'] : 'Individual',
  );

  $form[$group]['content_status'] = array(
	'#type'          => 'radios',
	'#title'         => t('Content Status'),
	'#options'       => array('1' => t('Publish'), '0' => 'Not Publish'),
	'#default_value' => isset($pt_value['content_status']) ? $pt_value['content_status'] : '0',
	'#description'   => t(''),
  );

  // Category
  // Form sate defualt value
  if (!empty($pt_value['action_category'])) {
	$cate_selected = $pt_value['action_category'];
  }
  else {
//    $cate_selected = !empty($form_state['values']['feed_template']) ? $form_state['values']['action_category'] : '';
	$cate_selected = $pt_settings_value['taxonomy_group'];
  }
  $categories = array();
  global $pt_settings_value;
  if ($pt_settings_value['taxonomy']['get_term'] !== 0) {
	$taxonomies = taxonomy_vocabulary_get_names();
	foreach ($taxonomies as $taxonomie) {
	  if ($taxonomie->machine_name == $pt_settings_value['taxonomy_group']) {
		$tax_id = $taxonomie->vid;
	  }
	  if ($pt_settings_value['taxonomy_group'] == 'default') {
		$tax_id = 1;
	  }
	}
	$terms = taxonomy_get_tree($tax_id);
	foreach ($terms as $term) {
	  $categories[$term->name] = $term->name;
	}
	$form[$group]['action_category'] = array(
	  '#type'          => 'select',
	  '#title'         => t('Category'),
	  '#prefix'        => '<div id="template-category-replace">',
	  '#suffix'        => '</div>',
	  '#options'       => $categories,
	  '#ajax'          => array(
		'event'    => 'change',
		'callback' => '_ajax_taxonomies_dropdown_callback',
		'wrapper'  => 'template-taxonomies-replace',
	  ),
	  '#default_value' => $cate_selected,
	);
  }
  /*  $form[$group]['taxonomy_group'] = array(
	'#type' => 'select',
	'#title' => t('Taxonomy'),
	'#prefix' => '<div id="template-taxonomies-replace">',
	'#suffix' => '</div>',
	'#options' => _ajax_get_taxonomy_dropdown_options($cate_selected),
	'#default_value' => isset($pt_value['taxonomy_group'])?$pt_value['taxonomy_group']: '',
  );*/

  $def_featured_image_option = 'Post Image: Include document image in template? (uncheck this if your Featured image gets inserted into your post by your theme)';

  $form[$group]['featured_image'] = array(
	'#type'          => 'checkboxes',
	'#title'         => t('Featured Image'),
	'#label'         => t('Post Image:'),
	'#options'       => array(
	  'save_featured_image' => t('Download and save content image as the "Featured Image"'),
	  'html_body_image'     => $def_featured_image_option
	),
	'#default_value' => !empty($pt_value) ? (array) $pt_value['featured_image'] : array('html_body_image' => 'html_body_image'),
  );

  // Featured Image fields
  $form[$group]['images'] = _image_size_fields('Featured Image Size', 'featured_', $pt_value);

  $def_ignore_img_option = "Ok to ignore original image's size?:  Original images can be smaller than your set size. If you check this, then we will ignore that original size, and resize to your set size. Smaller images will then have padding added to match the size you indicate. ";

  $form[$group]['ignore_original_image'] = array(
	'#type'          => 'checkboxes',
	'#options'       => array('resize_featured_image' => $def_ignore_img_option),
	'#default_value' => !empty($pt_value) ? (array) $pt_value['ignore_original_image'] : array('resize_featured_image' => $def_ignore_img_option),
  );

  // Individual
  $form[$group]['ind_add_posts'] = array(
	'#type'          => 'radios',
	'#title'         => t('Add Posts from new content'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#options'       => array('0' => t('No'), '1' => 'Yes'),
	'#default_value' => isset($pt_value['ind_add_posts']) ? $pt_value['ind_add_posts'] : '1',
	'#states'        => array(
	  'visible' => array(
		':input[name="format_type"]' => array('value' => t('Individual')),
	  ),
	),
  );

  $form[$group]['ind_delete_posts'] = array(
	'#type'          => 'radios',
	'#title'         => t('Delete Posts when deleted in PublishThis'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#options'       => array('0' => t('No'), '1' => 'Yes'),
	'#default_value' => isset($pt_value['ind_delete_posts']) ? $pt_value['ind_delete_posts'] : '0',
	'#states'        => array(
	  'visible' => array(
		':input[name="format_type"]' => array('value' => t('Individual')),
	  ),
	),
  );

  $form[$group]['ind_modified_content'] = array(
	'#type'          => 'radios',
	'#title'         => t('Modified content in PublishThis updates Posts'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#options'       => array('0' => t('No'), '1' => 'Yes'),
	'#default_value' => isset($pt_value['ind_modified_content']) ? $pt_value['ind_modified_content'] : '1',
	'#states'        => array(
	  'visible' => array(
		':input[name="format_type"]' => array('value' => t('Individual')),
	  ),
	),
  );

  // Digest
  $form[$group]['allow_to_override'] = array(
	'#type'          => 'radios',
	'#title'         => t('Allow PublishThis to Override Edits'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#options'       => array('0' => t('No'), '1' => 'Yes'),
	'#default_value' => isset($pt_value['allow_to_override']) ? $pt_value['allow_to_override'] : '0',
	'#states'        => array(
	  'visible' => array(
		':input[name="format_type"]' => array('value' => t('Digest')),
	  ),
	),
  );


  // Layout

  $layout        = 'layout';
  $form[$layout] = array(
	'#type'        => 'fieldset',
	'#title'       => t('Layout Options'),
	'#collapsible' => TRUE,
	'#collapsed'   => FALSE,
  );

  // -----------------------------------------------
  // Image Fields
  // ----------------------------------------------- 
  $form[$layout]['image'] = array(
	'#type'          => 'checkbox',
	'#title'         => t('Image'),
	'#title_display' => 'before',
	'#field_suffix'  => 'Include',
	'#attributes'    => array('class' => array('disabled')),
	'#default_value' => !empty($pt_value) ? $pt_value['image'] : '1'
  );

  $form[$layout]['image_customize'] = array(
	'#title'       => t('Customize'),
	'#type'        => 'fieldset',
	'#collapsible' => TRUE,
	'#collapsed'   => TRUE,
	'#attributes'  => array('class' => array('pt-customize')),
	'#states'      => array(
	  // Only show this field when the 'Image' checkbox is enabled.
	  'disabled'  => array(
		':input[name="image"]' => array('disabled' => TRUE),
	  ),
	  'enabled'   => array(
		':input[name="image"]' => array('checked' => TRUE),
	  ),
	  'collapsed' => array(
		':input[name="image"]' => array('checked' => FALSE),
	  ),
	),
  );

  // Image layout fields
  $form[$layout]['image_customize']['fields'] = _customize_image_layout($pt_value);


  // -----------------------------------------------
  // Title Fields
  // -----------------------------------------------

  // Find default val of content type format
  $format_value = $form['options']['format_type']['#default_value'];

  if ($format_value == 'Individual') {
	$checked = '';
  }
  else {
	$checked = '1';
  }
  $form[$layout]['title'] = array(
	'#type'          => 'checkbox',
	'#title'         => t('Title'),
	'#title_display' => 'before',
	'#field_suffix'  => 'Include',
	'#default_value' => isset($pt_value['title']) ? $pt_value['title'] : $checked,
	'#states'        => array(
	  'disabled' => array(
		':input[name="format_type"]' => array('value' => t('Individual')),
	  ),
	  'checked'  => array(
		':input[name="format_type"]' => array('value' => t('Digest')),
	  )
	),
  );

  $form[$layout]['title_customize'] = array(
	'#title'       => t('Customize'),
	'#type'        => 'fieldset',
	'#collapsible' => TRUE,
	'#collapsed'   => TRUE,
	'#tree'        => TRUE,
	'#states'      => array(
	  // Only show this field when the 'Title' checkbox is enabled.
	  'disabled' => array(
		':input[name="title"]' => array('checked' => FALSE),
	  ),
	),
  );
  // Title layout fields 
  $form[$layout]['title_customize']['fields'] = _customize_title_layout($pt_value);

  // -----------------------------------------------
  // Summary 
  // -----------------------------------------------

  $form[$layout]['summary'] = array(
	'#type'          => 'checkbox',
	'#title'         => t('Summary'),
	'#title_display' => 'before',
	'#field_suffix'  => 'Include',
	'#default_value' => !empty($pt_value) ? $pt_value['summary'] : '1',
  );

  // -----------------------------------------------
  // Publish Date 
  // -----------------------------------------------

  $form[$layout]['publish_date'] = array(
	'#type'          => 'checkbox',
	'#title'         => t('Publish Date'),
	'#title_display' => 'before',
	'#field_suffix'  => 'Include',
	'#default_value' => !empty($pt_value) ? $pt_value['publish_date'] : '0',
  );


  // -----------------------------------------------
  // Annotation Fields
  // -----------------------------------------------

  $form[$layout]['annotation'] = array(
	'#type'          => 'checkbox',
	'#title'         => t('Annotation'),
	'#title_display' => 'before',
	'#field_suffix'  => 'Include',
	'#default_value' => !empty($pt_value) ? $pt_value['annotation'] : '1',
  );

  $form[$layout]['annotation_customize'] = array(
	'#title'       => t("Customize"),
	'#type'        => 'fieldset',
	'#collapsible' => TRUE,
	'#collapsed'   => TRUE,
	'#states'      => array(
	  // Only show this field when the 'Annotation' checkbox is enabled.
	  'disabled'  => array(
		':input[name="annotation"]' => array('checked' => FALSE),
	  ),
	  'collapsed' => array(
		':input[name="annotation"]' => array('checked' => FALSE),
	  ),
	),
  );

  // Annotation customize fields
  $form[$layout]['annotation_customize']['fields'] = _customize_annotation_layout($pt_value);


  // -----------------------------------------------
  // Read more Fields
  // -----------------------------------------------

  $form[$layout]['readmore'] = array(
	'#type'          => 'checkbox',
	'#title'         => t('Read More'),
	'#title_display' => 'before',
	'#field_suffix'  => 'Include',
	'#default_value' => !empty($pt_value) ? $pt_value['readmore'] : '1',
  );

  $form[$layout]['readmore_customize'] = array(
	'#title'       => t("Customize"),
	'#type'        => 'fieldset',
	'#collapsible' => TRUE,
	'#collapsed'   => TRUE,
	'#states'      => array(
	  // Only show this field when the 'Readmore' checkbox is enabled.
	  'disabled'  => array(
		':input[name="readmore"]' => array('checked' => FALSE),
	  ),
	  'collapsed' => array(
		':input[name="readmore"]' => array('checked' => FALSE),
	  ),
	),
  );

  // Read more customize fields
  $form[$layout]['readmore_customize']['fields'] = _customize_readmore_layout($pt_value);

  // Embed Fields 
  $form[$layout]['embed'] = array(
	'#type'          => 'checkbox',
	'#title'         => t('Embed'),
	'#title_display' => 'before',
	'#field_suffix'  => 'Include',
	'#default_value' => !empty($pt_value) ? $pt_value['embed'] : '1',
  );

  $form[$layout]['embed_customize'] = array(
	'#title'       => t("Customize"),
	'#type'        => 'fieldset',
	'#collapsible' => TRUE,
	'#collapsed'   => TRUE,
	'#attributes'  => array('class' => array('pt-customize')),
	'#states'      => array(
	  // Only show this field when the 'Embed' checkbox is enabled.
	  'disabled'  => array(
		':input[name="embed"]' => array('checked' => FALSE),
	  ),
	  'collapsed' => array(
		':input[name="embed"]' => array('checked' => FALSE),
	  ),
	),
  );

  // Embed Image customize fields
  $form[$layout]['embed_customize']['fields'] = _image_size_fields('Size', 'embed_', $pt_value);

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
 * Implements of hook_form_submit()
 * @desc Submit form value
 */
function publishthis_addnew_publishingactions_submit($form, &$form_state) {

  if ($form_state['clicked_button']['#value'] == "Save Changes") {

	$content_type = $form_state['values']['content_type'];
	$action_title = $form_state['values']['action_title'];
	$format_type  = $form_state['values']['format_type'];

	$form_state['values']['import_start'] = time();

	// Serialize submit form value
	$pb_action_value = serialize($form_state['values']);

	// Insert data
	db_insert('pt_publishactions')
	  ->fields(
		array(
		  'name'        => $content_type,
		  'title'       => $action_title,
		  'format_type' => $format_type,
		  'value'       => $pb_action_value,
		)
	  )
	  ->execute();

	drupal_set_message(t('Publishing Action changes update successfully.'));

	drupal_goto('admin/config/system/publishthis/publishing-actions');
  }

}

/**
 * Selects template_section dropdown to be returned for re-rendering
 *
 * @return renderable array (the second dropdown)
 */
function ajax_dependent_dropdown_callback($form, $form_state) {
  return $form;//['options']['template_section'];
}

/**
 * Selects category dropdown to be returned for re-rendering
 *
 * @return renderable array (the second dropdown)
 */
function ajax_category_dropdown_callback($form, $form_state) {
  return $form['options']['action_category'];
}


/**
 * Helper func to populate the feed template dropdown. This would normally be
 * pulling data from the API.
 *
 * @return array of options
 */
function _get_feed_template_options($api_template = array()) {

  if (!empty($api_template)) {
	// API feed template result

	foreach ($api_template as $tempval) {
	  // feed template
	  $template[$tempval->templateId] = $tempval->displayName;
	}
  }

  return $template;
}


/**
 * Helper func to populate the second dropdown.
 *
 * @param $key
 *   This will determine which set of options is returned.
 *
 * @return array of options
 */
function _ajax_get_template_section_dropdown_options($key = '', $template = array()) {

  if (!empty($template)) {
	// API feed template result

	foreach ($template as $tempval) {

	  // template section
	  if (($tempval->templateId == $key)) {
		foreach ($tempval->templateSections as $temSection) {
		  $template_section[$temSection->sectionId] = $temSection->displayName;
		}
	  }
	}
	return $template_section;

  }
  else {
	return array();
  }
}

/**
 * Find Vocabulary list options
 *
 * @param $key
 *  This will determine which set of options is returned.
 *
 * @return array of options
 */
function _ajax_get_taxonomy_dropdown_options($key = '') {
  if (!empty($key)) {

	// Get taxonomy
	$vocabularies = taxonomy_get_vocabularies();
	foreach ($vocabularies as $voc) {
	  $categories[$voc->machine_name] = $voc->name;
	}
	return $categories;

  }
  else {
	return array('default_category' => 'Drupal Default');
  }
}

/**
 * Select category option dropdown to be returned for re-rendering
 *
 * @return renderable array (the second dropdown)
 */
function _ajax_taxonomies_dropdown_callback($form, $form_state) {
  return $form['options']['taxonomy_group'];
}

/**
 * Helper func to populate the category dropdown.
 *
 * @param $key
 *   This will determine which set of options is returned.
 *
 * @return array of options
 */
function _ajax_get_template_category_dropdown_options($key = '', $template = array()) {

  if (!empty($template)) {
	// API feed template result
	$template_category = array('' => 'Do not categorize');
	foreach ($template as $tempval) {

	  // template section
	  if (($tempval->templateId == $key) && isset($tempval->templateFields)) {
		foreach ($tempval->templateFields as $temSection) {
		  $template_category[$temSection->shortCode] = $temSection->displayName;
		}
	  }
	}
	return $template_category;

  }
  else {
	return array();
  }
}

/**
 * @name Customize Image layout fields of Publishing Action
 */
function _customize_image_layout($pt_value = array()) {

  $form['image_customize']['image_alignment'] = array(
	'#type'          => 'radios',
	'#title'         => t('Alignment'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#options'       => array(
	  'default' => t('Default'),
	  'left'    => 'Left',
	  'center'  => 'Center',
	  'right'   => 'Right'
	),
	'#default_value' => isset($pt_value['image_alignment']) ? $pt_value['image_alignment'] : 'left',
  );

  // Image fields
  $form['image_customize']['image'] = _image_size_fields('Image Size', '', $pt_value);

  $form['image_customize']['custom_images'] = array(
	'#type'          => 'checkboxes',
	'#title'         => t('Custom Images'),
	'#options'       => array('resize_custom_image' => 'Override user uploaded widths and heights?'),
	'#default_value' => !empty($pt_value) ? (array) $pt_value['custom_images'] : array(),
  );

  $form['image_customize']['preview_images'] = array(
	'#type'          => 'checkboxes',
	'#title'         => t('Preview Images'),
	'#options'       => array('resize_preview' => 'Resize preview images to max width'),
	'#default_value' => !empty($pt_value) ? (array) $pt_value['preview_images'] : array(),
  );

  return $form;
}

/**
 * @name Customize Title layout fields of Publishing Action
 */
function _customize_title_layout($pt_value = array()) {

  $form['clickable'] = array(
	'#type'          => 'radios',
	'#title'         => t('Make Title Clickable'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#options'       => array('1' => t('Yes'), '0' => 'No'),
	'#default_value' => isset($pt_value) && isset($pt_value['title_customize']) ? $pt_value['title_customize']['fields']['clickable'] : '1',
  );

  $form['wraplink'] = array(
	'#type'          => 'radios',
	'#title'         => t('Wrap Link with "No Follow"'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#options'       => array('1' => t('Yes'), '0' => 'No'),
	'#default_value' => isset($pt_value) && isset($pt_value['title_customize']) ? $pt_value['title_customize']['fields']['wraplink'] : '1',
  );

  $form['whatisthis'] = array(
	'#markup' => '<span class="whatisthis">' . l(t("What's this?"), 'http://support.google.com/webmasters/bin/answer.py?hl=en&answer=96569', array('attributes' => array('target' => "_blank"))) . '</span>'
  );

  return $form;
}

/**
 * @name Customize Annotation layout fields of Publishing Action
 */
function _customize_annotation_layout($pt_actions = array()) {

  $form['text_annotation'] = array(
	'#type'  => 'item',
	'#title' => '<h4>Annotation</h4>',
  );

  $form['alignment'] = array(
	'#type'          => 'radios',
	'#title'         => t('Alignment'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#options'       => array(
	  '0' => t('Above the Content'),
	  '1' => 'Below the Content'
	),
	'#default_value' => isset($pt_actions['alignment']) ? $pt_actions['alignment'] : '0',
  );

  $form['text_title'] = array(
	'#type'  => 'item',
	'#title' => '<h4>Title</h4>',
  );

  $form['annot_displaytext'] = array(
	'#type'          => 'textfield',
	'#title'         => t('Display Text'),
	'#default_value' => isset($pt_actions['annot_displaytext']) ? $pt_actions['annot_displaytext'] : 'Our Take',
  );

  $form['text_alignment'] = array(
	'#type'  => 'item',
	'#title' => '<strong>Text Alignment</strong>',
  );
  $form['vertical']       = array(
	'#type'          => 'select',
	'#options'       => drupal_map_assoc(array(
	  'top'    => 'Top',
	  'middle' => 'Middle',
	  'bottom' => 'Bottom'
	)),
	'#default_value' => isset($pt_actions['vertical']) ? $pt_actions['vertical'] : '',
  );

  $form['horizontal'] = array(
	'#type'          => 'select',
	'#options'       => drupal_map_assoc(array('left'  => 'Left',
											   'right' => 'Right'
	)),
	'#default_value' => isset($pt_actions['horizontal']) ? $pt_actions['horizontal'] : '',
  );

  return $form;
}

/**
 * @name Customize Read more layout fields of Publishing Action
 */
function _customize_readmore_layout($pt_actions = array()) {

  $form['rm_displaytext'] = array(
	'#type'          => 'textfield',
	'#title'         => t('Display Text'),
	'#default_value' => isset($pt_actions['rm_displaytext']) ? $pt_actions['rm_displaytext'] : 'Read More',
  );

  $form['include_publisher'] = array(
	'#type'          => 'radios',
	'#title'         => t('Include Publisher'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#options'       => array('1' => t('Yes'), '0' => 'No'),
	'#default_value' => isset($pt_actions['include_publisher']) ? $pt_actions['include_publisher'] : '1',
  );

  $form['opennewwindow'] = array(
	'#type'          => 'radios',
	'#title'         => t('Open New Window'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#options'       => array('1' => t('Yes'), '0' => 'No'),
	'#default_value' => isset($pt_actions['opennewwindow']) ? $pt_actions['opennewwindow'] : '0',
  );

  $form['nofollow'] = array(
	'#type'          => 'radios',
	'#title'         => t('Wrap Link with "No Follow"'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#options'       => array('1' => t('Yes'), '0' => 'No'),
	'#default_value' => isset($pt_actions['nofollow']) ? $pt_actions['nofollow'] : '1',
  );

  $form['whatisthis'] = array(
	'#markup' => '<span class="whatisthis">' . l(t("What's this?"), 'http://support.google.com/webmasters/bin/answer.py?hl=en&answer=96569', array('attributes' => array('target' => "_blank"))) . '</span>'
  );

  return $form;
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
 * Implements of hook_form_submit()
 * @desc Submit Edit form value
 */
function edit_publishthis_publishing_actions_submit($form, &$form_state) {

  if ($form_state['clicked_button']['#value'] == "Save Changes") {

	$content_type = $form_state['values']['content_type'];
	$action_title = $form_state['values']['action_title'];
	$format_type  = $form_state['values']['format_type'];

	$form_state['values']['import_start'] = time();

	// Serialize submit form value
	$action_values = serialize($form_state['values']);
	$edit          = $form_state['values']['editId'];

	// Update data
	db_update('pt_publishactions')
	  ->fields(array(
		  'name'        => $content_type,
		  'title'       => $action_title,
		  'format_type' => $format_type,
		  'value'       => $action_values,
		)
	  )
	  ->condition('id', $edit, '=')
	  ->execute();

	drupal_set_message(t('Publishing Action changes update successfully.'));
	drupal_goto('admin/config/system/publishthis/publishing-actions');
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
	drupal_goto("admin/config/system/publishthis/publishing-actions");

  }

}
