<?php
function publishthis_automated_feed(){
  $published_mixes = variable_get('published_mixes');
  $objAPI        = new Publishthis_API();

$items  = $objAPI->get_feed_content_by_id( $published_mixes , array());
  $html ='';
  if($items){
    foreach($items as $item){
      $html .= '<p class="pt-automated pt-image">
			<a class="pt-imgcontent-link" target="_blank" href="'. $item->originalUrl .'">
			<img class="pt-image alignleft" alt="'. $item->title .'" src="'. $item->imageUrl .'">
			</a></p>
<p class="pt-automated pt-title"><a class="pt-content-link" target="_blank" href="'. $item->originalUrl .'">'. $item->title .'</a></p>
  <p class="pt-automated pt-publishdate"><span>&nbsp;via <strong>'. $item->publisher .'</strong></span></p>
  <p class="pt-automated pt-summary">'. $item->summary .'</p><p class="clear">&nbsp;</p>';
    }
  }
return $html;
}

