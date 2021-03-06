<?php

/**
 * @name Entry Form of PublishThis Settings
 * @method publishthis_settings
 */
function publishthis_settings($form, &$form_state) {
  global $pt_client_info;

  global $base_url;

  $objAPI   = new Publishthis_API();
  $objUtils = new Publishthis_Utils();

  // Find client info
  $client = $pt_client_info;

  // Curatedby Logo
  $allowDisableLogo = '';
  if (is_object($client) && !empty($client)) {
	$allowDisableLogo = $client->allowDisableLogo;
  }

  // Unserialize saved values
  $pt_value = unserialize(variable_get('pt_settings'));

  $form['header'] = array(
	'#markup' => '<div class="wrap">
			<h2><img src="/' . drupal_get_path('module', 'publishthis') . '/assets/img/ico-32x32.png" /> <span style="line-height:33px;vertical-align:top;">Settings</span></h2>
		  </div>'
  );

  try {
	if (intval(ini_get('output_buffering')) == 0) {
	  drupal_set_message('Output Buffering is OFF.<br>What does this mean? It means that some of the features of this plugin will not work. Please contact your PublishThis Client Services representative and they can help you with a solution to fix this.', 'error');
	}
  } catch (Exception $ex) {
  }

  $form['pt_settings']['curated_publish'] = array(
	'#type'          => 'radios',
	'#title'         => t('PublishThis Curated Publish Options'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#default_value' => 'publishthis_import_from_manager',
	'#options'       => array(
	  'publishthis_import_from_manager' => t('PublishThis pushes to this CMS'),
	  'import_with_cron'                => t('This CMS polls PublishThis (with cron)'),
	  'import_without_cron'             => t('This CMS polls PublishThis (without cron)')
	),
	'#default_value' => isset($pt_value['curated_publish']) ? $pt_value['curated_publish'] : 'publishthis_import_from_manager',
	'#description'   => t('')
  );
  $form['pt_settings']['pause_polling']   = array(
	'#type'          => 'checkboxes',
	'#title'         => t('Pause Polling'),
	'#options'       => drupal_map_assoc(array(t('Stop polling the API for new content'))),
	'#default_value' => !empty($pt_value) ? (array) $pt_value['pause_polling']['Stop polling the API for new content'] : array(),
	'#description'   => t('')
  );
  if (isset($pt_value['endpoint']) && !empty($pt_value['endpoint'])) {
	$pt_endpoint = $pt_value['endpoint'];
  }
  else {
	$pt_endpoint = getRandomWord();
  }
  $form['pt_settings']['endpoint'] = array(
	'#type'          => 'textfield',
	'#default_value' => isset($pt_value['endpoint']) ? $pt_value['endpoint'] : $pt_endpoint,
	'#required'      => TRUE,
	'#description'   => t(''),
	'#prefix'        => '<div class="form-item form-type-checkboxes">
			  <label for="edit-cms-url">CMS URL</label>
			<div id="edit-cms-url">
			  <div class="form-item form-type-checkbox">' . $base_url . '/?q=' . $pt_endpoint . '
			  </div>
			</div>
		  </div><div id="endpoint_input">',
	'#suffix'        => '</div>',
  );
  $form['pt_settings']['taxonomy'] = array(
	'#type'          => 'checkboxes',
	'#title'         => t('Taxonomy'),
	'#options'       => array('get_term' => "I want to use taxonomy set from PublishThis Content Mixes"),
	'#default_value' => isset($pt_value['taxonomy']) ? $pt_value['taxonomy'] : array('get_term'),
	'#description'   => t(''),
	'#prefix'        => '<div class="taxonomy-publish-block"><div class="taxonomy-publish">',
	'#suffix'        => '<span></span></div>',
  );

  $node_taxonomy             = taxonomy_get_vocabularies();
  $taxonomy_types            = array();
  $taxonomy_types['default'] = 'Default';
  foreach ($node_taxonomy as $node) {
	$taxonomy_types[$node->machine_name] = $node->name;
  }
  $form['pt_settings']['taxonomy_group'] = array(
	'#type'          => 'select',
	'#title'         => t('Taxonomy list'),
	'#options'       => $taxonomy_types,
	'#default_value' => isset($pt_value['taxonomy_group']) ? $pt_value['taxonomy_group'] : 'Default',
	'#prefix'        => '<div class="taxonomy-group-block">',
	'#suffix'        => '</div></div>',
  );
  $form['pt_settings']['api_version']    = array(
	'#type'          => 'hidden',
	'#default_value' => '3.0'
  );

  $form['pt_settings']['styling'] = array(
	'#type'          => 'checkboxes',
	'#title'         => t('Styling'),
	'#options'       => array('enable_styling' => "Enable PublishThis CSS styles"),
	'#default_value' => !empty($pt_value) ? (array) $pt_value['styling'] : array('enable_styling'),
	'#description'   => t('')
  );
  $form['pt_settings']['debug']   = array(
	'#type'          => 'select',
	'#title'         => t('Logging Options'),
	'#options'       => array(0 => 'None', 3 => 'Errors Only', 7 => 'Debug'),
	'#default_value' => isset($pt_value['debug']) ? $pt_value['debug'] : '',
	'#description'   => t('')
  );

  // Curatedby Logo display options
  $logo_options = array('footer' => t('Footer'), 'page' => t('Page'));

  // Check allowDisableLogo flag value,
  // return TRUE, logo option show 'Do not display logo', FLASE not display
  if ($allowDisableLogo == 1) {

	$logo_options = array_merge($logo_options, array('none' => t('Do not display logo')));

	$curated_value = !empty($pt_value['curatedby']) ? $pt_value['curatedby'] : 'footer';

  }
  else {

	if (!empty($pt_value['curatedby']) && ($pt_value['curatedby'] == 'none')) {
	  $curated_value = 'footer';
	}
	else {
	  $curated_value = !empty($pt_value['curatedby']) ? $pt_value['curatedby'] : 'footer';
	}

  }

  $form['pt_settings']['curatedby']       = array(
	'#type'          => 'radios',
	'#title'         => t('Show PublishThis logo in'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#default_value' => $curated_value,
	'#options'       => $logo_options,
	'#description'   => t('')
  );
  $form['pt_settings']['curatedby_logos'] = array(
	'#type'          => 'radios',
	'#title'         => t('Logo Options'),
	'#attributes'    => array('class' => array('horizontal-list')),
	'#default_value' => 'curatedwith-box-darkgray.png',
	'#options'       => array(
	  1 => t('<img alt="Publishthis Curated By Logo Black" src="' . $objUtils->getCuratedByLogoImage(1) . '">'),
	  2 => t('<img alt="Publishthis Curated By Logo Gray" src="' . $objUtils->getCuratedByLogoImage(2) . '">'),
	  3 => t('<img alt="Publishthis Curated By Logo White" src="' . $objUtils->getCuratedByLogoImage(3) . '">'),
	  5 => t('<img alt="Publishthis Curated By Logo Medium" src="' . $objUtils->getCuratedByLogoImage(5) . '">')
	),
	'#default_value' => isset($pt_value['curatedby_logos']) ? $pt_value['curatedby_logos'] : "1",
	'#description'   => t('')
  );
  $form['pt_settings']['pt_token']        = array(
	'#type'        => 'fieldset',
	'#title'       => t('API Token'),
	'#weight'      => 2,
	'#collapsible' => TRUE,
	'#collapsed'   => FALSE,
  );

  // Since token return TRUE or FALSE, we have to check that
  // $form_state has been filled as well as what it contains.


  if (!empty($form_state['values']['api_token'])) {

	// Validate Token
	$token = $objAPI->validate_token($form_state['values']['api_token']);

	if ($token['valid'] == 1) {
	  $error_class = "updated";
	}
	else {
	  $error_class = "error";
	}
	// Validate Message
	$form['pt_settings']['pt_token']['msg'] = array(
	  '#markup' => '<p id="verify-token-error" class="' . $error_class . '" >' . $token['message'] . '</p>',
	);
  }
  else {
	$form['pt_settings']['pt_token']['msg'] = array(
	  '#markup' => '',
	);
  }

  $form['pt_settings']['pt_token']['api_token'] = array(
	'#type'          => 'textfield',
	'#title'         => t('API Token'),
	'#default_value' => isset($pt_value['api_token']) ? $pt_value['api_token'] : '',
	'#required'      => TRUE,
	'#description'   => t(''),
	'#prefix'        => '<div class="publishthis-token-setup-block">
			<p>To authenticate your API token, paste your API token in the field below.</p>',
	'#suffix'        => '</div>',
  );

  $form['pt_settings']['pt_token']['verfiy_button_name'] = array(
	'#type'  => 'button',
	'#value' => t('Verify'),
	'#ajax'  => array(
	  'callback' => 'api_token_validate_callback',
	  'wrapper'  => 'api-token-validate',
	  'method'   => 'replace',
	  'effect'   => 'fade',
	)
  );

  $form['submit'] = array(
	'#type'   => 'submit',
	'#weight' => 3,
	'#value'  => t('Save Changes'),
	'#submit' => array('publishthis_settings_submit')
  );

  // Rebuild form
  $form['#prefix'] = '<div id="api-token-validate">';
  $form['#suffix'] = '</div>';

  return $form;
}

/**
 * Implementation of hook_ajax().
 * return $form
 */
function api_token_validate_callback($form, $form_state) {
  if (empty($form_state['values']['api_token'])) {
	$form['pt_settings']['pt_token']['msg'] = array(
	  '#markup' => '<p id="verify-token-error" class="error" >API token can\'t be empty.</p>',
	);
  }
  $form_state['rebuild'] = TRUE;
  // return form_state value  
  return $form;
}

/**
 * Implements of hook_form_validate()
 * @desc Valdiate form value
 */
function publishthis_settings_validate($form, &$form_state) {

  if ($form_state['clicked_button']['#value'] == "Save Changes") {
	$objAPI = new Publishthis_API();
	$token  = $objAPI->validate_token($form_state['values']['api_token']);

	// Validate Token
	if ($token['valid'] != 1) {
	  form_set_error('api_token', $token['message']);
	}
  }
}

/**
 * Implements of hook_form_submit()
 * @desc Submit form value
 */
function publishthis_settings_submit($form, &$form_state) {
  if (!empty($form['pt_settings']['taxonomy']['#value']['get_term']) && $form['pt_settings']['taxonomy_group']['#value'] !== 'default') {
	$vocabulary = $form['pt_settings']['taxonomy_group']['#value'];
	if (!field_info_field('pt_' . $vocabulary)) {
	  $field = array(
		'field_name'  => 'pt_' . $vocabulary,
		'type'        => 'taxonomy_term_reference',
		'cardinality' => -1,
		'settings'    => array(
		  'allowed_values' => array(
			array(
			  'vocabulary' => $vocabulary,
			  'parent'     => 0
			),
		  ),
		),
	  );
	  field_create_field($field);
	}
	$node_types      = node_type_get_types();
	$vocabulary_name = taxonomy_vocabulary_machine_name_load($vocabulary);
	foreach ($node_types as $node_type) {
	  if (!field_info_instance('node', 'pt_' . $vocabulary, $node_type->type)) {
		$instance = array(
		  'field_name'  => 'pt_' . $vocabulary,
		  'label'       => $vocabulary_name->name,
		  'bundle'      => $node_type->type,
		  'entity_type' => 'node',
		  'widget'      => array(
			'type'      => 'options_buttons',
			'behaviors' => array(
			  'multiple values' => FIELD_CARDINALITY_UNLIMITED,
			),
		  ),
		);
		field_create_instance($instance);
	  }

	}
  }

  if ($form_state['clicked_button']['#value'] == "Save Changes") {

	$setting_formValue = serialize($form_state['values']);
	// Set varaible name, value pair
	$pt_settings_value = variable_set('pt_settings', $setting_formValue);

	drupal_set_message(t('The publishthis settings saved'));
	drupal_goto('admin/config/system/publishthis');

  }
}
