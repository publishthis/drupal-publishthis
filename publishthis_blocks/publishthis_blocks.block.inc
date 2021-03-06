<?php
function publishthis_automated_feed($edit) {

  if (isset($edit['multiblock_delta']['#value'])) {
    $id_block = $edit['multiblock_delta']['#value'];
  }
  else {
    $id_block = '';
  }
  $published_mixes   = variable_get('published_mixes_' . $id_block);
  $objAPI            = new Publishthis_API();
  $pt_num_results    = variable_get('num_results_' . $id_block);
  $params['results'] = $pt_num_results;
  if (variable_get('mix_defaults') != '1') {
    $params['sort']                 = variable_get('sort_by_' . $id_block);
    $params['removeNearDuplicates'] = (variable_get('remove_duplicates_' . $id_block) == "1" ? "true" : "false");
    $params['removeNearRelated']    = (variable_get('remove_related_' . $id_block) == "1" ? "true" : "false");
  }
  $pt_items   = $objAPI->get_feed_content_by_id($published_mixes, $params);
  $pt_content = array(
    'result'             => $pt_items,
    'type'               => 'feed',
    'feedId'             => $published_mixes,
    'show_links'         => variable_get('show_links_' . $id_block),
    'show_photos'        => variable_get('show_photos_' . $id_block),
    'show_source'        => variable_get('show_source_' . $id_block),
    'show_summary'       => variable_get('show_summary_' . $id_block),
    'show_date'          => variable_get('show_date_' . $id_block),
    'show_nofollow'      => variable_get('show_nofollow_' . $id_block),
    'image_size'         => variable_get('image_size_' . $id_block),
    'image_align'        => variable_get('image_align_' . $id_block),
    'ok_resize_previews' => variable_get('ok_resize_previews_' . $id_block),
    'image_width'        => variable_get('image_width_' . $id_block),
    'image_height'       => variable_get('image_height_' . $id_block),
    'image_maxwidth'     => variable_get('image_maxwidth_' . $id_block),
  );

  $obj_utils = new Publishthis_Utils();
  $html      = '';
  if ($pt_items) {
    foreach ($pt_items as $pt_item) {
      $strImageUrl = NULL;
      if (isset($pt_item->imageUrl) && !empty($pt_item->imageUrl)) {
        if ($pt_content['image_size'] == 'theme_default') {
          $strImageUrl = $pt_item->imageUrl;
        }
        else {
          $imageUrl = $obj_utils->getContentPhotoUrl($pt_item);
          if ($pt_content['image_size'] == 'custom') {
            $strImageUrl = $obj_utils->getResizedPhotoUrl($imageUrl, $pt_content['image_width'], $pt_content['ok_resize_previews'], $pt_content['image_height']);
          }
          elseif ($pt_content['image_size'] == 'custom_max_width') {
            $strImageUrl = $obj_utils->getResizedPhotoUrl($imageUrl, $pt_content['image_maxwidth'], $pt_content['ok_resize_previews']);
          }
        }
      }

      if (isset($pt_item->imageUrl) && $pt_content['show_photos']) {
        $html .= '<p class="pt-automated pt-image"><a class="pt-imgcontent-link"';
        if ($pt_content['show_nofollow']) {
          $html .= 'rel="nofollow"';
        }
        $html .= ' target="_blank" href="' . $pt_item->originalUrl . '">
     <img class="pt-image align' . $pt_content['image_align'] . '" alt="' . $pt_item->title . '" src="' . $strImageUrl . '"></a></p>';
      }
      if ($pt_content['show_links']) {
        $html .= ' <p class="pt-automated pt-title"><a class="pt-content-link"';
        if ($pt_content['show_nofollow']) {
          $html .= 'rel="nofollow"';
        }
        $html .= '  target="_blank" href="' . $pt_item->originalUrl . '">' . $pt_item->title . '</a></p>';
      }
      else {
        $html .= ' <p class="pt-automated pt-title">' . $pt_item->title . '</p>';
      }
      if ((isset($pt_item->publishDate) && $pt_content['show_date']) || (isset($pt_items->publisher) && $pt_content['show_source'])) {
        $html .= '<p class="pt-automated pt-publishdate"><span>';
        if (isset($pt_item->publishDate) && $pt_content['show_date']) {
          $html .= $obj_utils->getElapsedPrettyTime($pt_item->publishDate);
        }
        if (isset($pt_item->publisher) && $pt_content['show_source']) {
          $html .= '&nbsp;via <strong>' . $pt_item->publisher . '</strong>';
        }
        $html .= '</span></p>';
      }
      if (isset($pt_item->summary) && $pt_content['show_summary']) {
        $html .= '  <p class="pt-automated pt-summary">' . $pt_item->summary . '</p>';
      }
      $html .= '  <p class="clear">&nbsp;</p>';
    }
  }
  return $html;
}

function publishthis_automated_tweets($edit) {
  if (isset($edit['multiblock_delta']['#value'])) {
    $id_block = $edit['multiblock_delta']['#value'];
  }
  else {
    $id_block = '';
  }
  $published_mixes   = variable_get('published_mixes_tweets_' . $id_block);
  $objAPI            = new Publishthis_API();
  $pt_num_results    = variable_get('num_results_tweets_' . $id_block);
  $params['results'] = $pt_num_results;
  $pt_items          = $objAPI->get_tweets_by_feed_id($published_mixes, $params);
  $html              = '';
  if ($pt_items) {
    foreach ($pt_items as $pt_item) {
      $html .= '<blockquote class="twitter-tweet" ><p>' . $pt_item->statusText . '</p>
      &mdash; Twitter  (@' . $pt_item->userScreenName . ') <a href="' . $pt_item->statusUrl . '" data-datetime="' . $pt_item->publishDate . '" >' . $pt_item->publishDate . '</a></blockquote>';
    }
  }
  return $html;
}
