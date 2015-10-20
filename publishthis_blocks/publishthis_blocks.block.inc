<?php
function publishthis_automated_feed(){
  $published_mixes = variable_get('published_mixes');
  $objAPI        = new Publishthis_API();
  $pt_items  = $objAPI->get_feed_content_by_id( $published_mixes , array());
  $html ='';
  if($pt_items){
    foreach($pt_items as $pt_item){
      $html .= '<p class="pt-automated pt-image">
  <a class="pt-imgcontent-link" target="_blank" href="'. $pt_item->originalUrl .'">
  <img class="pt-image alignleft" alt="'. $pt_item->title .'" src="'. $pt_item->imageUrl .'">
  </a></p>
  <p class="pt-automated pt-title"><a class="pt-content-link" target="_blank" href="'. $pt_item->originalUrl .'">'. $pt_item->title .'</a></p>
  <p class="pt-automated pt-publishdate"><span>&nbsp;via <strong>'. $pt_item->publisher .'</strong></span></p>
  <p class="pt-automated pt-summary">'. $pt_item->summary .'</p><p class="clear">&nbsp;</p>';
    }
  }
return $html;
}

