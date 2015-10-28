<?php

/**
 * @name Entry Form of PublishThis RSS Settings
 * @method publishthis_rss_settings
 */
function publishthis_rss_settings($form, &$form_state) {
  global $base_url;
  $form['header'] = array(
    '#markup' => '<div class="wrap">
			<h2><img src="/' . drupal_get_path('module', 'publishthis') . '/assets/img/ico-32x32.png" />
			<span style="line-height:33px;vertical-align:top;">Settings PublishThis RSS</span></h2>
		</div>'
  );
  $form['pt_settings_rss']['rss_link'] = array(
   '#prefix'        => '<div class="form-item form-type-checkboxes">
	 <label for="edit-cms-url">URL PublishThis RSS</label>
        <div id="edit-cms-url">
		 <div class="form-item form-type-checkbox">' . $base_url . '/ptrss.xml</div>
		</div>
	 </div>',
    '#suffix'   => '',
  );
  global $pt_num_results;
  $form['pt_settings_rss']['num_results'] = array(
    '#type'          => 'select',
    '#title'         => t('Number of Results to Display:'),
    '#options'       => $pt_num_results,
    '#default_value' =>  variable_get('published_rss_num_results', '5'),
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
    $form['pt_settings_rss']['taxonomy_'.  $vocabularie_name] = array(
      '#type'          => 'select',
      '#title'         => t( $vocabularie_name),
      '#options'       => $vocab,
      '#default_value' =>  variable_get('published_mixes_rss_'. $vocabularie_name , 'all'),
      '#suffix'        => '</div>',
    );
  }
  $form['pt_settings_rss']['pt_post'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Use PublishThis Post Link for the RSS Feed Item URL when possible'),
    '#description'   => t(''),
    '#default_value' => variable_get('published_rss_pt_post', '1'),
  );
 $form['submit'] = array(
    '#type'   => 'submit',
    '#weight' => 3,
    '#value'  => t('Save Changes'),
    '#submit' => array('publishthis_rss_settings_submit')
  );

  return $form;

}

/**
 * Implementation of hook_ajax().
 * return $form
 */
function api_token_validate_callback($form, $form_state) {
 // return form_state value
  return $form;
}
/**
 * Implements of hook_form_validate()
 * @desc Valdiate form value
 */
function publishthis_rss_settings_validate($form, &$form_state) {
  if ($form_state['clicked_button']['#value'] == "Save Changes") {

  }
}

/**
 * Implements of hook_form_submit()
 * @desc Submit form value
 */
function publishthis_rss_settings_submit($form, &$form_state) {
  if ($form_state['clicked_button']['#value'] == "Save Changes") {
    variable_set('published_rss_num_results', $form_state['values']['num_results']);
    variable_set('published_rss_pt_post', $form_state['values']['pt_post']);
    $vocabularies  = taxonomy_get_vocabularies();
    $taxonomy = array();
    foreach ($vocabularies as $vocabularie) {
      $vocabularie_name = $vocabularie->machine_name;
      if($form_state['values']['taxonomy_'. $vocabularie_name] !== 'all'){
        $taxonomy[$vocabularie_name] = $form_state['values']['taxonomy_'. $vocabularie_name];
      }
     variable_set('published_mixes_rss_'. $vocabularie_name, $form_state['values']['taxonomy_'. $vocabularie_name]);
    }
    variable_set('published_rss_taxonomy', $taxonomy);
	drupal_set_message(t('The publishthis rss settings saved'));
	drupal_goto('admin/config/system/publishthis-rss');
  }
}
