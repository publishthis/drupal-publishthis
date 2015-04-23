<?php
/* Helper functions to render posts in Individual and Combined modes */

class Publishthis_Render {
	public $pt_content;
	public $pt_content_features;
	//public $pt_is_first = false;
	public $pt_found_featured_image = false;

	public $obj_utils = null;

	function __construct() {
		$this->obj_utils = new Publishthis_Utils();
	}

	/**
	 * Render individual post
	 */
	function render_content( $format_type ) {
		$sectionContent = '';
		if ( $format_type == 'Digest' ) {
			$sectionContent .= $this->display_title();
		}
		$sectionContent .= $this->display_publishdate();

		if ( $this->pt_content_features['alignment'] == '0' ) {
			$sectionContent .= $this->display_annotation();
		}

		switch ( $this->pt_content->contentType ) {
		case 'video':
			if ( isset( $this->pt_content->embed ) && ! empty( $this->pt_content->embed ) ) {
				$sectionContent .= $this->display_embed_object();
			} else {
				$sectionContent .= $this->display_image();
			}
			$sectionContent .= $this->display_summary();
			$sectionContent .= $this->display_read_more();
			break;

		case 'tweet':
			$sectionContent .= $this->display_tweet();
			break;

		case 'photo':
			$sectionContent .= $this->display_image();
			$sectionContent .= $this->display_summary();
			break;

		case 'text':
			$sectionContent .= $this->display_text();
			break;

		default:
			// do the default display. assume that it is an article, but could also just
			// be an unknown content type
			$sectionContent .= "<!-- default view -->";
			$sectionContent .= $this->display_image();
			$sectionContent .= $this->display_summary();
			$sectionContent .= $this->display_read_more();
			break;
		}

		if ( $this->pt_content_features['alignment'] == '1' ) {
			$sectionContent .= $this->display_annotation();
		}

		if ( $format_type == 'Digest' ) {
			//provide space for the next entry after this
			$sectionContent = $sectionContent . '<p class="clear pt-spacer"><img src="http://img.publishthis.com/images/empty.gif" alt="" style="border:none;" /></p>';
		}

		return $sectionContent;
	}

	/**
	 *   publishDate block
	 */
	function display_publishdate() {
		$html = '';
		if ( $this->pt_content_features['publish_date']!=1 ) {
			$html .= "";
			return $html;
		}

		if ( isset ( $this->pt_content->publishDate ) ) {
			$html .= '<p class="pt-publishdate">' . $this->obj_utils->getElapsedPrettyTime( $this->pt_content->publishDate ) . '</p>';
		}

		$html .= "";
		return $html;
	}

	/**
	 *   Annotation block
	 */
	function display_annotation() {
		$html = '';
		if ( $this->pt_content_features['annotation']!=1 ) {
			$html .= "";
			return $html;
		}

		if ( isset ( $this->pt_content->annotations ) ) {
			if ( count( $this->pt_content->annotations ) > 0 ) {
				if ( isset( $this->pt_content_features['annot_displaytext'] ) && strlen( $this->pt_content_features['annot_displaytext'] )>0 ) {
					$html .= '<p class="pt-annotation-title pt-annotation-title-h-' . strtolower($this->pt_content_features['horizontal']) . ' pt-annotation-title-v-' . strtolower($this->pt_content_features['vertical']) . '">' . $this->pt_content_features['annot_displaytext'] . '</p>';
				}
				$html .= '<p class="pt-annotation">' . $this->pt_content->annotations[0]->annotation . '</p>';
			}
		}

		$html .= "";
		return $html;
	}

	/**
	 *   Summary block
	 */
	function display_summary() {
		$html = '';
		if ( $this->pt_content_features['summary']!=1 ) {
			$html .= "";
			return $html;
		}

		$this->pt_content->summary = trim( $this->pt_content->summary );
		if ( isset( $this->pt_content->summary ) && strlen( $this->pt_content->summary )>0 && $this->pt_content->summary!="<br />" ) {
			$html .= '<p class="pt-summary">' . $this->pt_content->summary . '</p>';
		}

		$html .= "";
		return $html;
	}

	/**
	 *   Read more block
	 */
	function display_read_more() {
		$html = '';
		if ( $this->pt_content_features['readmore']!=1 ) {
			$html .= "";
			return $html;
		}

		if ( isset( $this->pt_content->url ) ) {
			//$settings = $this->pt_content_features['styles']['readmore']->readmoreLayoutSettings;
			$nofollow = $this->pt_content_features['nofollow'] == "1" ? 'rel="nofollow"' : '';
			$target = $this->pt_content_features['opennewwindow'] == "1" ? 'target="_blank"' : '';
			$publisher = $this->pt_content_features['include_publisher'] == "1" && isset( $this->pt_content->publisher ) ? ' at '.$this->pt_content->publisher : '';
			$html .= '<p class="pt-readmore"><a href="' . $this->obj_utils->build_url_with_tracking($this->pt_content->url, $this->pt_content->feedId,true,$this->pt_content->docId,$this->pt_content->contentType) . '" ' . $target . ' ' . $nofollow . '>' . $this->pt_content_features["rm_displaytext"] . $publisher . '</a></p>';
		}

		$html .= "";
		return $html;
	}

	/**
	 *   Text block
	 */
	function display_text() {
		$html = '';
		$html .= $this->balanceTags ( '<p class="pt-text">' . $this->pt_content->text . '</p>', true );

		$html .= "";
		return $html;
	}
	
	
	
	function balanceTags( $text ) {
		$tagstack = array();
		$stacksize = 0;
		$tagqueue = '';
		$newtext = '';
		// Known single-entity/self-closing tags
		$single_tags = array( 'area', 'base', 'basefont', 'br', 'col', 'command', 'embed', 'frame', 'hr', 'img', 'input', 'isindex', 'link', 'meta', 'param', 'source' );
		// Tags that can be immediately nested within themselves
		$nestable_tags = array( 'blockquote', 'div', 'object', 'q', 'span' );

		// fix for comments - in case you REALLY meant to type '< !--'
		$text = str_replace('< !--', '<    !--', $text);
		// fix for LOVE <3 (and other situations with '<' before a number)
		$text = preg_replace('#<([0-9]{1})#', '&lt;$1', $text);

		while ( preg_match("/<(\/?[\w:]*)\s*([^>]*)>/", $text, $regex) ) {
			$newtext .= $tagqueue;

			$i = strpos($text, $regex[0]);
			$l = strlen($regex[0]);

			// clear the shifter
			$tagqueue = '';
			// Pop or Push
			if ( isset($regex[1][0]) && '/' == $regex[1][0] ) { // End Tag
				$tag = strtolower(substr($regex[1],1));
				// if too many closing tags
				if( $stacksize <= 0 ) {
					$tag = '';
					// or close to be safe $tag = '/' . $tag;
				}
				// if stacktop value = tag close value then pop
				elseif ( $tagstack[$stacksize - 1] == $tag ) { // found closing tag
					$tag = '</' . $tag . '>'; // Close Tag
					// Pop
					array_pop( $tagstack );
					$stacksize--;
				} else { // closing tag not at top, search for it
					for ( $j = $stacksize-1; $j >= 0; $j-- ) {
						if ( $tagstack[$j] == $tag ) {
						// add tag to tagqueue
							for ( $k = $stacksize-1; $k >= $j; $k--) {
								$tagqueue .= '</' . array_pop( $tagstack ) . '>';
								$stacksize--;
							}
							break;
						}
					}
					$tag = '';
				}
			} else { // Begin Tag
				$tag = strtolower($regex[1]);

				// Tag Cleaning

				// If it's an empty tag "< >", do nothing
				if ( '' == $tag ) {
					// do nothing
				}
				// ElseIf it presents itself as a self-closing tag...
				elseif ( substr( $regex[2], -1 ) == '/' ) {
					// ...but it isn't a known single-entity self-closing tag, then don't let it be treated as such and
					// immediately close it with a closing tag (the tag will encapsulate no text as a result)
					if ( ! in_array( $tag, $single_tags ) )
						$regex[2] = trim( substr( $regex[2], 0, -1 ) ) . "></$tag";
				}
				// ElseIf it's a known single-entity tag but it doesn't close itself, do so
				elseif ( in_array($tag, $single_tags) ) {
					$regex[2] .= '/';
				}
				// Else it's not a single-entity tag
				else {
					// If the top of the stack is the same as the tag we want to push, close previous tag
					if ( $stacksize > 0 && !in_array($tag, $nestable_tags) && $tagstack[$stacksize - 1] == $tag ) {
						$tagqueue = '</' . array_pop( $tagstack ) . '>';
						$stacksize--;
					}
					$stacksize = array_push( $tagstack, $tag );
				}

				// Attributes
				$attributes = $regex[2];
				if( ! empty( $attributes ) && $attributes[0] != '>' )
					$attributes = ' ' . $attributes;

				$tag = '<' . $tag . $attributes . '>';
				//If already queuing a close tag, then put this tag on, too
				if ( !empty($tagqueue) ) {
					$tagqueue .= $tag;
					$tag = '';
				}
			}
			$newtext .= substr($text, 0, $i) . $tag;
			$text = substr($text, $i + $l);
		}

		// Clear Tag Queue
		$newtext .= $tagqueue;

		// Add Remaining text
		$newtext .= $text;

		// Empty Stack
		while( $x = array_pop($tagstack) )
			$newtext .= '</' . $x . '>'; // Add remaining tags to close

		// fix for the bug with HTML comments
		$newtext = str_replace("< !--","<!--",$newtext);
		$newtext = str_replace("<    !--","< !--",$newtext);

		return $newtext;
	}
	
	

	/**
	 *   Tweet Summary Card block
	 *    https://dev.twitter.com/docs/cards/types/summary-card
	 */
	function display_tweet() {
		$html = '';
		// <blockquote class="twitter-tweet"><p>Search API will now always return
		// "real" Twitter user IDs. The with_twitter_user_id parameter is no longer
		// necessary. An era has ended. ^TS</p>&mdash; Twitter API (@twitterapi) <a
		// href="https://twitter.com/twitterapi/status/133640144317198338"
		// data-datetime="2011-11-07T20:21:07+00:00">November 7, 2011</a></blockquote>
		// <script src="//platform.twitter.com/widgets.js" charset="utf-8"></script>
		$html .= "<blockquote class=\"twitter-tweet\"><p>" . $this->pt_content->statusText . "</p>";
		$html .= "&mdash; Twitter  (@" . $this->pt_content->userScreenName . ") <a href=\"" . $this->pt_content->statusUrl . "\" data-datetime=\"" . $this->pt_content->publishDate . "\">" . $this->pt_content->publishDate . "</a></blockquote>";

		$html .= "";
		return $html;
	}

	/**
	 *   Embed Object block
	 */
	function display_embed_object() {
		$html = '';
		if ( $this->pt_content_features['embed']!=1 ) {
			$html .= "";
			return $html;
		}

		$embed = $this->pt_content->embed;
		$size = '';
			
		switch ( $this->pt_content_features['embed_image_size'] ) {
			case 'custom':
				$size = 'style="width:' . $this->pt_content_features['embed_image_width'] . 'px;height:' . $this->pt_content_features['embed_image_height'] . 'px;"';
				$embed = preg_replace( '/width="(\d+)"/i', 'width="'.$this->pt_content_features['embed_image_width'].'"', $embed);
				$embed = preg_replace( '/height="(\d+)"/i', 'height="'.$this->pt_content_features['embed_image_height'].'"', $embed);
				break;
			case 'custom_max_width':
				$size = 'style="width:' . $this->pt_content_features['embed_image_maxwidth'] . 'px;"';
				
				preg_match( '/width="(\d+)"/i', $embed, $old_size);
				$old_width = intval( $old_size[1] );

				preg_match( '/height="(\d+)"/i', $embed, $old_size);
				$old_height = intval( $old_size[1] );

				$embed = preg_replace( '/width="(\d+)"/i', 'width="'.$this->pt_content_features['embed_image_maxwidth'].'"', $embed);
				$embed = preg_replace( '/height="(\d+)"/i', 'height="'.($old_height*$this->pt_content_features['embed_image_maxwidth']/$old_width).'"', $embed);
				break;
			default:
				break;
		}
		
		$html .= '<div '.$size.'>' . $embed . '</div>';

		return $html;
	}

	/**
	 *   Image block
	 *   align left and right is easy, but align center
	 *   has wordpress adding some <p> tag with text alignment added to it
	 */
	function display_image() {
		$html = '';
		// html_body_image=1 - Include image to post body
		// otherwise:
		// for individual post - not show image for the curated content;
		// for digest post - not place the image for the first curated item.
		if ( $this->pt_content_features['image']!=1 || $this->pt_content_features['featured_image']['html_body_image'] == '0' ) {
			if ( $this->pt_content_features['format_type'] == 'Individual' || $this->pt_content_features['format_type'] == 'Digest' && $this->pt_found_featured_image ) {
				$html .= "";
				return $html;
			}
		}
		$ok_resize_previews = $this->pt_content_features['preview_images']['resize_preview']===0 ? "0" : "1";
		$allow_to_override = $this->pt_content_features['custom_images']['resize_custom_image']===0 ? "0" : "1";
	
		$sourceImg = null;

		if ( isset( $this->pt_content->imageUrl ) || isset( $this->pt_content->imageUrlThumbnail ) ) {
			$sourceImg = $this->pt_content->imageUrl;
			if ( $this->pt_content_features['image_size'] !== 'theme_default' && $allow_to_override == "1" ) {
				//if we are allowing image resizing and we can over-ride any client images, just force the thumbnail view
				//for resizing
				$sourceImg = $this->pt_content->imageUrlThumbnail;
			}
		}
	
		$img_style = '';
		$class = $this->pt_content_features['image_alignment']!='default' ? 'class="pt_content_photo align' . $this->pt_content_features['image_alignment'] . '"' : 'class="pt_content_photo"';
		$caption_width = 0;

		if ( $allow_to_override == "1" || !isset( $this->pt_content->imageUrlPublisher ) ) {
			if ( $this->pt_content_features['image_size'] == 'custom' ) {
				$caption_width = $this->pt_content_features['image_width'];
				$sourceImg = $this->obj_utils->getResizedPhotoUrl ( $sourceImg, $this->pt_content_features['image_width'], $ok_resize_previews, $this->pt_content_features['image_height'] );
			}
			elseif ( $this->pt_content_features['image_size'] == 'custom_max_width' ) {
				$caption_width = $this->pt_content_features['image_maxwidth'];
				$sourceImg = $this->obj_utils->getResizedPhotoUrl ( $sourceImg, $this->pt_content_features['image_maxwidth'], $ok_resize_previews );
			}
		}

		if ( isset( $sourceImg ) && !empty( $sourceImg ) ) {
			$html .= '<div class="pt_photo">';
			if ( !empty( $this->pt_content->url ) ) {
				$html .= '<a href="' . $this->obj_utils->build_url_with_tracking($this->pt_content->url, $this->pt_content->feedId,true,$this->pt_content->docId,$this->pt_content->contentType)  . '" rel="nofollow" target="_blank">';
			}

			$html .= '<img src="' . $sourceImg . '"/>';

			if ( !empty( $this->pt_content->url ) ) {
				$html .= '</a>';
			}
			$html .= '</div>';
			if ( !empty( $this->pt_content->photoCaption ) ) {
				$html .= '<div class="pt_photo_caption" '.($caption_width>0 ? 'style="width:'.$caption_width.'px;"' : '').'>' . $this->pt_content->photoCaption . '</div>';
			}			
		}

		$html = '<div ' . $class . '>' . $html . '</div>';
		return $html;
	}

	/**
	 *   Title block
	 */
	function display_title() {
		//render title comment inside h4, otherwise that produce redundant <br> tags in output
		$html = '';
		if ( $this->pt_content_features['title']!=1 ) {
			$html .= "";
			return $html;
		}
		
		if ( strlen( $this->pt_content->title ) > 0 ) {
			$html .= '<h4 class="pt-title">';
			if ( isset ( $this->pt_content->url ) &&  ! empty ( $this->pt_content->url ) && $this->pt_content_features['title_customize']['fields']['clickable'] == "1" ) {
				$nofollow = $this->pt_content_features['title_customize']['fields']['wraplink'] == "1" ? 'rel="nofollow"' : '';
				$html .= '<a href="' . $this->obj_utils->build_url_with_tracking($this->pt_content->url, $this->pt_content->feedId,true,$this->pt_content->docId,$this->pt_content->contentType)  . '" target="_blank" ' . $nofollow . '>' . $this->pt_content->title . '</a>';
			} else {
				$html .= '' . $this->pt_content->title;
			}
			$html .= "</h4>";
		}

		return $html;
	}

	/**
	 * Curated By logo block
	 */
	function display_curated_logo() {
		$html = "";

		if ( intval( $this->get_variable( 'curatedby' ) ) == "page" ) {
			$html .= $this->obj_utils->getCuratedByLogo();
		}

		$html .= "";
		return $html;
	}
}
