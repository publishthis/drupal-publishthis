<?php

/**
 * @name List of Added Publishing Action
 * @method publishthis_publishingactions
 */
function publishthis_rss_publishingactions() {
  $output = '';

  $header = array(
	array('data' => t('Title'), 'field' => 'title'),
	array('data' => t('Taxonomy'), ),
    array('data' => t('Number of Results to Display:'), ),
	array('data' => t('Action')),
  );

  $result = db_select('pt_rssfeed', 'pb')
	->fields('pb', array())
	->extend('PagerDefault')
	->extend('TableSort')
	->orderByHeader($header)
	->execute();

  $rows = array();
  foreach ($result as $row) {

 	$title = l($row->title, 'rss_feeds/' . $row->title .'.xml' , array('attributes' => array('title' => t('Edit Actions'))));

	$edit   = l(t('Edit'), 'admin/config/system/publishthis-rss/publishing-editactions/' . $row->id, array('attributes' => array('title' => t('Edit Actions'))));
	$delete = l(t('Delete'), 'publishthis-rss/publishing-actions/delete/' . $row->id, array('attributes' => array("onClick" => "return confirm( '" . t('Are you sure to delete this record?') . "' );")));

	$action = $edit . ' | ' . $delete;

	$rows[] = array(
	  $title,
	  $row->taxonomy_json,
      $row->number_results,
	  $action,
	);
  }

  $output .= '<div class="wrap">
          <h2><img src="/' . drupal_get_path('module', 'publishthis') . '/assets/img/ico-32x32.png" /> <span style="line-height:33px;vertical-align:top;">RSS feed</span></h2>
        </div>';
  $output .= '<ul class="action-links"><li>' . l(t('Add rss feed'), 'admin/config/system/publishthis-rss/publishing-addactions') . '</li></ul>';
  $output .= theme('table', array('header' => $header, 'rows' => $rows));
  $output .= theme('pager');

  return $output;
}

/**
 * @name Entry Form of Add New Publishing Action
 * @method publishthis_addnew_publishingactions
 * @return $form
 */
function publishthis_rss_addnew_publishingactions($form, &$form_state) {
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
	$text = t('Edit RSS feed');
	// Find record
	$result = db_select('pt_rssfeed', 'pt')
	  ->fields('pt')
	  ->condition('id', $edit_id, '=')
	  ->execute()
	  ->fetchAssoc();

	// Unserialize saved form values
	if (!empty($result['id'])) {
	  // True, get form fields value
	  $edit_id  = $result['id'];
	  $pt_value = $result;
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
	'#markup' => '<ul><li>' . l('RSS feed', 'admin/config/system/publishthis-rss-feed') . '</li></ul>'
  );
  $form['action_title'] = array(
	'#type'          => 'textfield',
	'#title'         => t('Title'),
	'#attributes'    => array('placeholder' => t('Enter title here')),
	'#required'      => TRUE,
	'#size'          => 80,
	'#default_value' => isset($pt_value['title']) ? $pt_value['title'] : '',
  );
  $form['editId'] = array(
	'#type'          => 'hidden',
	'#default_value' => $edit_id,
  );
  global $pt_num_results;
  $form['pt_settings_rss']['num_results'] = array(
    '#type'          => 'select',
    '#title'         => t('Number of Results to Display:'),
    '#options'       => $pt_num_results,
    '#default_value' => isset($pt_value['number_results']) ? $pt_value['number_results'] : '5',
  );

 $vocabularies  = taxonomy_get_vocabularies();
  foreach ($vocabularies as $vocabularie) {
    $vocab = array();
    $tax_id = $vocabularie->vid;
    $terms = taxonomy_get_tree($tax_id);
    $vocabularie_name = $vocabularie->machine_name;
    $vocab['all']       = 'all';
    foreach ($terms as $term) {
      $vocab[$term->tid] = $term->name;
    }
    if(!empty($pt_value['taxonomy_json'])){
      $taxonomy = json_decode($pt_value['taxonomy_json']);
    }else{
      $taxonomy = array();
    }

    if (array_key_exists($vocabularie->machine_name, $taxonomy)) {
      $machine_name = $vocabularie->machine_name;
      $form['pt_settings_rss']['taxonomy_'.  $vocabularie_name] = array(
        '#type'          => 'select',
        '#title'         => t( $vocabularie_name),
        '#options'       => $vocab,
        '#default_value' =>  $taxonomy->$machine_name,
        '#suffix'        => '</div>',
      );
    }else{
      $form['pt_settings_rss']['taxonomy_'.  $vocabularie_name] = array(
        '#type'          => 'select',
        '#title'         => t( $vocabularie_name),
        '#options'       => $vocab,
        '#default_value' => 'all',
        '#suffix'        => '</div>',
      );
    }
  }
  $form['pt_settings_rss']['pt_post'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Use PublishThis Post Link for the RSS Feed Item URL when possible'),
    '#description'   => t(''),
    '#default_value' => isset($pt_value['pt_postlink']) ? $pt_value['pt_postlink'] : '1',
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
function publishthis_rss_addnew_publishingactions_submit($form, &$form_state) {

  if ($form_state['clicked_button']['#value'] == "Save Changes") {
    $num_results = $form_state['values']['num_results'];
	$feed_title = $form_state['values']['action_title'];
	$pt_post  = $form_state['values']['pt_post'];
    $vocabularies  = taxonomy_get_vocabularies();
    $taxonomy = array();
    foreach ($vocabularies as $vocabularie) {
      $vocabularie_name = $vocabularie->machine_name;
      if($form_state['values']['taxonomy_'. $vocabularie_name] !== 'all'){
        $taxonomy[$vocabularie_name] = $form_state['values']['taxonomy_'. $vocabularie_name];
      }
      variable_set('published_mixes_rss_'. $vocabularie_name, $form_state['values']['taxonomy_'. $vocabularie_name]);
    }
    $taxonomy_json =  json_encode($taxonomy);

//    pa($form_state['values'],1);
//	$form_state['values']['import_start'] = time();
	$pb_action_value = serialize($form_state['values']);

	// Insert data
	db_insert('pt_rssfeed')
	  ->fields(
		array(
		  'title'       => $feed_title,
		  'pt_postlink' => $pt_post,
		  'number_results'       => $num_results,
           'taxonomy_json'       => $taxonomy_json,
		)
	  )
	  ->execute();
    drupal_flush_all_caches();
	drupal_set_message(t('Publishing Action changes update successfully.'));
    drupal_goto('admin/config/system/publishthis-rss-feed');
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
 * Edit Activity
 *
 * @return edit publishing actions form value
 */
function edit_publishthis_rss_publishing_actions($form, $form_state) {

  // Render publishthis add form
  $form = publishthis_rss_addnew_publishingactions($form, $form_state);

  return $form;
}


/**
 * Implements of hook_form_submit()
 * @desc Submit Edit form value
 */
function edit_publishthis_rss_publishing_actions_submit($form, &$form_state) {

  if ($form_state['clicked_button']['#value'] == "Save Changes") {
    $num_results = $form_state['values']['num_results'];
    $feed_title = $form_state['values']['action_title'];
    $pt_post  = $form_state['values']['pt_post'];
    $vocabularies  = taxonomy_get_vocabularies();
    $taxonomy = array();
    foreach ($vocabularies as $vocabularie) {
      $vocabularie_name = $vocabularie->machine_name;
      if($form_state['values']['taxonomy_'. $vocabularie_name] !== 'all'){
        $taxonomy[$vocabularie_name] = $form_state['values']['taxonomy_'. $vocabularie_name];
      }
      variable_set('published_mixes_rss_'. $vocabularie_name, $form_state['values']['taxonomy_'. $vocabularie_name]);
    }
    $taxonomy_json =  json_encode($taxonomy);
	$edit          = $form_state['values']['editId'];

	// Update data
	db_update('pt_rssfeed')
	  ->fields(array(
          'title'       => $feed_title,
          'pt_postlink' => $pt_post,
          'number_results'       => $num_results,
          'taxonomy_json'       => $taxonomy_json,
		)
	  )
	  ->condition('id', $edit, '=')
	  ->execute();
    drupal_flush_all_caches();
	drupal_set_message(t('Publishing Action changes update successfully.'));
	drupal_goto('admin/config/system/publishthis-rss-feed');
  }

}

/**
 * Delete Activity
 *
 * Delete single publishing actionse using delete link
 */
function delete_publishthis_rss_publishing_actions() {
  $action    = arg(2);
  $action_id = arg(3);
  if (!empty($action_id) && $action == 'delete') {

	$delete = db_delete('pt_rssfeed')
	  ->condition('id', $action_id)
	  ->execute();

	drupal_set_message('One record has been deleted successfully.');
	drupal_goto("admin/config/system/publishthis-rss-feed");
  }

}
