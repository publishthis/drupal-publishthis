<?php

class Publishthis_Utils extends Publishthis_Utils_Common {
  /**
   * Publishthis constructor.
   */
  function __construct() {
  }

  /**
   *  Returns Curated By Logo
   */
  function getCuratedByLogo() {
    global $pt_settings_value;
    global $pt_client_info;

    //get selected logo
    $logo_index = $pt_settings_value['curatedby_logos'];
    $logo_index = isset( $logo_index ) ? $logo_index : 0;

    $client = $pt_client_info;
    $client_id = $client && $client->clientId ? $client->clientId : 0;

    $url = 'http://www.publishthis.com/?utm_source='.trim( $_SERVER['HTTP_HOST'] ).'_'.$client_id.'&utm_medium=image&utm_campaign=WPPluginCurateByButton';

    $html = '<p id="pt_curated_by" class="pt_curated_by">'.
      '<a href="'.rawurlencode( $url ).'" target="_blank">'.
      '<img src="' . $this->getCuratedByLogoImage( strval($logo_index) ) . '" alt="Curated By Logo">'.
      '</a>'.
      '</p>';

    return $html;
  }

  public function _map_style_values( $section_name ) {
    $form_section_name = $section_name=='publishdate' ? 'publishDate' : $section_name;
    $pt_styles = variable_get( 'pt_style_options' );

    if ( !isset( $pt_styles ) ) return array();

    $pt_styles = unserialize( $pt_styles );

    return array( 
      $section_name.'_font' => $pt_styles['pt_style_'.$form_section_name]['font'],
      $section_name.'_font-custom' => $pt_styles['pt_style_'.$form_section_name]['font_custom'],
      $section_name.'_font_size' => $pt_styles['pt_style_'.$form_section_name]['font_size'],
      $section_name.'_font_size-custom' => $pt_styles['pt_style_'.$form_section_name]['font_size_custom'],
      $section_name.'_font_color' => $pt_styles['pt_style_'.$form_section_name]['font_color'],    
      $section_name.'_font_color-custom' => '#'.$pt_styles['pt_style_'.$form_section_name]['font_color_custom'],
      $section_name.'_font_style' => $pt_styles['pt_style_'.$form_section_name]['font_style'],
      $section_name.'_font_style-bold' => $pt_styles['pt_style_'.$form_section_name]['font_style_custom']['bold']==="bold" ? "1" : "0",
      $section_name.'_font_style-italic' => $pt_styles['pt_style_'.$form_section_name]['font_style_custom']['italic']==="italic" ? "1" : "0",
      $section_name.'_font_style-underline' => $pt_styles['pt_style_'.$form_section_name]['font_style_custom']['underline']==="underline" ? "1" : "0",    
      $section_name.'_border_size' => $pt_styles['pt_style_'.$form_section_name]['font_border_size'], 
      $section_name.'_border_size-custom' => $pt_styles['pt_style_'.$form_section_name]['font_border_size_custom'], 
      $section_name.'_border_color' => $pt_styles['pt_style_'.$form_section_name]['border_color'],    
      $section_name.'_border_color-custom' => '#'.$pt_styles['pt_style_'.$form_section_name]['border_color_custom'],
      $section_name.'_background_color' => $pt_styles['pt_style_'.$form_section_name]['bg_color'],
      $section_name.'_background_color-custom' => '#'.$pt_styles['pt_style_'.$form_section_name]['bg_color_custom'],
      $section_name.'_margins' => $pt_styles['pt_style_'.$form_section_name]['margins'],
      $section_name.'_margins-left' => $pt_styles['pt_style_'.$form_section_name]['margin_left'],
      $section_name.'_margins-right' => $pt_styles['pt_style_'.$form_section_name]['margin_right'],
      $section_name.'_margins-top' => $pt_styles['pt_style_'.$form_section_name]['margin_top'],
      $section_name.'_margins-btm' => $pt_styles['pt_style_'.$form_section_name]['margin_bottom'],
      $section_name.'_paddings' => $pt_styles['pt_style_'.$form_section_name]['paddings'],
      $section_name.'_paddings-left' => $pt_styles['pt_style_'.$form_section_name]['padding_left'],
      $section_name.'_paddings-right' => $pt_styles['pt_style_'.$form_section_name]['padding_right'],
      $section_name.'_paddings-top' => $pt_styles['pt_style_'.$form_section_name]['padding_top'],
      $section_name.'_paddings-btm' => $pt_styles['pt_style_'.$form_section_name]['padding_bottom']
    );
  }

  public function _get_style_value( $key ) {
    $tmp = explode( '_', $key );
    $styles = $this->_map_style_values( strpos($key, 'annotation_title')!==false ? 'annotation_title' : $tmp[0] );
    return isset($styles[ $key ]) ? $styles[ $key ] : '';
  }

}
