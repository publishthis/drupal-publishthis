<?php
function publishthis_automated_feed(){
  $published_mixes = variable_get('published_mixes');
  $objAPI        = new Publishthis_API();
  $pt_num_results = variable_get('num_results');
  $params['results'] = $pt_num_results;
  if( variable_get('mix_defaults') != '1' ) {
    $params['sort'] = variable_get('sort_by');
    $params['removeNearDuplicates'] = (variable_get('remove_duplicates')=="1" ? "true" : "false");
    $params['removeNearRelated'] = (variable_get('remove_related')=="1" ? "true" : "false");
  }
  $pt_items  = $objAPI->get_feed_content_by_id( $published_mixes , $params);
  $pt_content = array(
    'result'             => $pt_items,
    'type'               => 'feed',
    'feedId'             => $published_mixes,
    'show_links'         => variable_get('show_links'),
    'show_photos'        => variable_get('show_photos'),
    'show_source'        => variable_get('show_source'),
    'show_summary'       => variable_get('show_summary'),
    'show_date'          => variable_get('show_date'),
    'show_nofollow'      => variable_get('show_nofollow'),
    'image_size'         => variable_get('image_size'),
    'image_align'        => variable_get('image_align'),
    'ok_resize_previews' => variable_get('ok_resize_previews')
  );
  $html ='';
  if($pt_items){
  foreach($pt_items as $pt_item){
    if ( isset( $pt_item->imageUrl) && $pt_content['show_photos'] ) {
      $html .= '<p class="pt-automated pt-image"><a class="pt-imgcontent-link" target="_blank" href="'. $pt_item->originalUrl .'">
     <img class="pt-image alignleft" alt="'. $pt_item->title .'" src="'. $pt_item->imageUrl .'"></a></p>';
    }
    if ($pt_content['show_links'] ) {
      $html .= ' <p class="pt-automated pt-title"><a class="pt-content-link" target="_blank" href="'. $pt_item->originalUrl .'">'. $pt_item->title .'</a></p>';
    }else{
      $html .= ' <p class="pt-automated pt-title">'. $pt_item->title .'</p>';
    }
    if(( isset( $pt_items->publishDate ) && $pt_content['show_date'] ) || ( isset( $pt_items->publisher ) && $pt_content['show_source'] )) {
     $html .= '<p class="pt-automated pt-publishdate"><span>&nbsp;via <strong>'. $pt_item->publisher .'</strong></span></p>';
    }
    if ( isset( $pt_item->summary ) && $pt_content['show_summary'] ) {
      $html .='  <p class="pt-automated pt-summary">'. $pt_item->summary .'</p>';
   }
    $html .='  <p class="clear">&nbsp;</p>';
    }
  }
return $html;
}

