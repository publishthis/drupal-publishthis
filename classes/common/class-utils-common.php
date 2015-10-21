<?php

abstract class Publishthis_Utils_Common {
	abstract function _get_style_value( $key );

	static $defaultPhotoWidth = 120;
	static $defaultPhotoHeight = 90;

	public $css_sections = array( 
		'title' => 'Title',
		'summary' => 'Summary',
		'publishdate' => 'Publish Date',
		'annotation' => 'Annotation Body Text',
		'annotation_title' => 'Annotation Title',
		'readmore' => 'Read More' 
	);

	/**
	 * Format time to user-friendly look&feel
	 *
	 * @param unknown $utcDateTime Initial time
	 */
	function getElapsedPrettyTime( $utcDateTime ) {
		$str = "";

		$currentTime = time();

		$timestamp = strtotime( $utcDateTime );

		$timeDiff = $currentTime - $timestamp;

		$secondsInMinute = 60;
		$secondsInHour = 60 * 60;
		$secondsInDay = 60 * 60 * 24;
		$secondsInWeek = 60 * 60 * 24 * 7;

		if ( $timeDiff > $secondsInWeek ) {
			$weeks = floor( $timeDiff / $secondsInWeek );
			if ( $weeks == 1 ) {
				$str .= "one week ago";
			} else {
				$str .= "{$weeks} weeks ago";
			}
		} else if ( $timeDiff > $secondsInDay ) {
				$days = floor( $timeDiff / $secondsInDay );
				if ( $days == 1 ) {
					$str .= "one day ago";
				} else {
					$str .= "{$days} days ago";
				}
			} else if ( $timeDiff >= $secondsInHour ) {
				$hrs = floor( $timeDiff / $secondsInHour );
				if ( $hrs == 1 ) {
					$str .= "one hour ago";
				} else {
					$str .= "{$hrs} hours ago";
				}
			} else {
			$mins = floor( $timeDiff / $secondsInMinute );
			if ( $mins <= 5 ) {
				$str .= "a few minutes ago";
			} else {
				$str .= "{$mins} minutes ago";
			}
		}

		return $str;
	}

	/**
	*	Get content image URL
	*/
	function getContentPhotoUrl( $pt_content ) {
 		// image URL is a client image
		if( isset( $pt_content->imageUrlPublisher ) && !empty( $pt_content->imageUrlPublisher ) ) {
			$imageUrl = $pt_content->imageUrlThumbnail;
		}
		else {
			$imageUrl = $pt_content->imageUrl;
		}
		return $imageUrl;
	}

	/**
	 *   Returns Resized Photo Url
	 *
	 * @param string  $originalUrl       Original image url
	 * @param integer $intWidth          Output image width size
	 * @param integer $okToResizePreview Flag that shows should we resize image or not
	 * @param integer $intHeight         Output image height size (optional)
	 * @return Url for resized image
	 */
	function getResizedPhotoUrl( $originalUrl, $intWidth, $okToResizePreview, $intHeight=0, $okToIgnoreOriginalImageSize="1" ) {
		$returnUrl = '';
		// first see if this url is from publishthis
		$isPTImage = strrpos( $originalUrl, "publishthis.com" );
		if ( ! $isPTImage ) {
			return $originalUrl;
		}

		$isPTPreviewImage = strrpos( $originalUrl, "_preview_" );

		// now we need to check if we can resize the preview image
		if ( ! $okToResizePreview && $isPTPreviewImage ) {
			return $originalUrl;
		}

		$tmpImageUrl = $originalUrl;

		if (strrpos($originalUrl,"_original") > -1){
			if ($okToIgnoreOriginalImageSize == '1'){
				$tmpImageUrl = str_replace("_original_","_thumbnail_",$tmpImageUrl);			
			}
		}
    
		$attachSizes = array();
		$width = $this->getPhotoWidthByURL( $tmpImageUrl, $intWidth, $okToIgnoreOriginalImageSize );
		// check if image should be resized
		if ( $width > 0 ) {
			$attachSizes[] = "W=" . $width;
		}

	 	if ( $intHeight > 0 ) {
	 		//we need to find the correct height, depending on the image url
	 		//this happens because the user probably put in that they want to try resizing both the width and the height.
	 		//when we do that, we do need to check and make sure the original image setting is set and that we
	 		//can resize larger or not.
	 		$height = $this->getPhotoHeightByURL($tmpImageUrl, $intHeight, $okToIgnoreOriginalImageSize );
	 		if ($height > 0){
				$attachSizes[] = "H=" . intval( $height );
			}
		}

		$returnUrl = $tmpImageUrl;
		if( count( $attachSizes ) > 0 ) {
			$returnUrl .= "?" . implode( "&", $attachSizes );
		}

		return $returnUrl;
	}

	/**
	 *   Returns accepted caption width
	 *
	 * @param unknown $originalUrl Image url
	 * @param unknown $intMaxWidth Output image max size
	 */
	function getPhotoCaptionWidth( $originalUrl, $intMaxWidth ) {
		$width = $this->getPhotoWidthByURL( $originalUrl, $intMaxWidth );

		return ( $width > 0 ) ? $width : Publishthis_Utils_Common::$defaultPhotoWidth;
	}

	/**
	 *   This will look at our url, and see if we can go beyond our max width or not.
	 *   1 - if we are a preview image, see if the option is set to allow preview images to be resized
	 *   2 - if the max width is less than our thumbnail size, then no changes need to be done, just pass in the width argument
	 *   3 - if max width is set, then use our small-xlarge markers to see if we can resize or not
	 *
	 * @param unknown $originalUrl Image url
	 * @param unknown $intMaxWidth Output image max size
	 * @return Image width. -1 means resizing is not required
	 */
	function getPhotoWidthByURL( $originalUrl, $intMaxWidth,$okToIgnoreOriginalImageSize='1' ) {
		/**
		 * thumbnail is 120x90
		 *
		 * xsmall - 100 or less
		 * small - 100-199
		 * medium - 200-299
		 * large - 300-399
		 * xlarge - larger
		 */

		if ( $intMaxWidth == Publishthis_Utils_Common::$defaultPhotoWidth ) {
			return -1;
		}

		// if it is smaller than our thumbnail, doesn't really matter
		// just return the resized image
		if ( $intMaxWidth < Publishthis_Utils_Common::$defaultPhotoWidth ) {
			return $intMaxWidth;
		}


		//if the client has said that they want to resize, no matter what, then
		//we skip checking the originals image size and just return.
    if ($okToIgnoreOriginalImageSize == '1'){
    	return $intMaxWidth;
    }

		$isXSmall = strrpos( $originalUrl, "_xsmall_" );
		$isSmall = strrpos( $originalUrl, "_small_" );
		$isMedium = strrpos( $originalUrl, "_medium_" );
		$isLarge = strrpos( $originalUrl, "_large_" );
		$isXLarge = strrpos( $originalUrl, "_xlarge_" );

		if ( $isXSmall && ( $intMaxWidth >= 100 ) ) {
			return -1;
		}

		// return it as big as we can
		if ( $isSmall && ( $intMaxWidth >= 200 ) ) {
			return 200;
		}

		// return it as big as we can
		if ( $isMedium && ( $intMaxWidth >= 300 ) ) {
			return 300;
		}

		// return it as big as we can
		if ( $isLarge && ( $intMaxWidth >= 400 ) ) {
			return 400;
		}

		// return it as big as we can
		if ( $isXLarge && ( $intMaxWidth >= 800 ) ) {
			return 800;
		}

		// ok, we have now checked max sizes beyond the scope of the image url
		return $intMaxWidth;
	}
	
	
	/**
	 *   This will look at our url, and see if we can go beyond our max height or not.
	 *   1 - if we are a preview image, see if the option is set to allow preview images to be resized
	 *   2 - if the max height is less than our thumbnail size, then no changes need to be done, just pass in the height argument
	 *   3 - if max height is set, then use our small-xlarge markers to see if we can resize or not
	 *
	 * @param unknown $originalUrl Image url
	 * @param unknown $intMaxHeight Output image max size
	 * @return Image width. -1 means resizing is not required
	 */
	function getPhotoHeightByURL( $originalUrl, $intMaxHeight,$okToIgnoreOriginalImageSize='1' ) {
		/**
		 * thumbnail is 120x90
		 *
		 * xsmall - 100 or less
		 * small - 100-199
		 * medium - 200-299
		 * large - 300-399
		 * xlarge - larger
		 */

		if ( $intMaxHeight == Publishthis_Utils_Common::$defaultPhotoHeight ) {
			return -1;
		}

		// if it is smaller than our thumbnail, doesn't really matter
		// just return the resized image
		if ( $intMaxHeight < Publishthis_Utils_Common::$defaultPhotoHeight ) {
			return $intMaxHeight;
		}


		//if the client has said that they want to resize, no matter what, then
		//we skip checking the originals image size and just return.
    if ($okToIgnoreOriginalImageSize == '1'){
    	return $intMaxHeight;
    }

		$isXSmall = strrpos( $originalUrl, "_xsmall_" );
		$isSmall = strrpos( $originalUrl, "_small_" );
		$isMedium = strrpos( $originalUrl, "_medium_" );
		$isLarge = strrpos( $originalUrl, "_large_" );
		$isXLarge = strrpos( $originalUrl, "_xlarge_" );

		if ( $isXSmall && ( $intMaxHeight >= 100 ) ) {
			return -1;
		}

		// return it as big as we can
		if ( $isSmall && ( $intMaxHeight >= 200 ) ) {
			return 200;
		}

		// return it as big as we can
		if ( $isMedium && ( $intMaxHeight >= 300 ) ) {
			return 300;
		}

		// return it as big as we can
		if ( $isLarge && ( $intMaxHeight >= 400 ) ) {
			return 400;
		}

		// return it as big as we can
		if ( $isXLarge && ( $intMaxHeight >= 800 ) ) {
			return 800;
		}

		// ok, we have now checked max sizes beyond the scope of the image url
		return $intMaxHeight;
	}
	
	

	/**
	 *   Set the image alignment
	 *
	 * @param unknown $strAlignmentValue Possible values: 0 - none, 1 - center, 2 - left, 3 - right
	 * @return string Image alignment
	 */
	function getImageAlignmentClass( $strAlignmentValue ) {
		$align = "";

		switch ( $strAlignmentValue ) {
		case "1": //align to center
			$align = "aligncenter";
			break;

		case "2": //align to the left
			$align = "alignleft";
			break;

		case "3": //align to the right
			$align = "alignright";
			break;

		default: break;
		}
		return $align;
	}



	/**
	 *  Returns Curated By Logo image by index
	 *
	 * @param integer $index The index of the image
	 */
	function getCuratedByLogoImage( $index ) {
		$image = "";

		if( !defined('CURATED_LOGO_PATH') ) define( 'CURATED_LOGO_PATH', 'http://img.publishthis.com/images/ptbuttons/' );

		switch ( $index ) {
		case '1':
			$image = "curatedwith-box-darkgray.png";
			break;
		case '2':
			$image = "curatedwith-box-lightgray-trans.png";
			break;
		case '3':
			$image = "curatedwith-box-white.png";
			break;
		case '4':
			$image = "justpt-small-transparent.png";
			break;
		case '5':
			$image = "curatedwith-small-transparent.png";
			break;
		default:
			$image = "curatedwith-box-darkgray.png";
			break;
		}

		$image = CURATED_LOGO_PATH . $image;

		return $image;
	}

	/**
	 *   Build dynamic styles
	 */
	function display_css($wrap=true) {
		$style = '';

		if($wrap) {
			$style .= '<style type="text/css">';
		}

		foreach ($this->css_sections as $section_key => $section_title) {
			$style .= $this->build_style( $section_key );
		}

		if($wrap) {
			$style .= '</style>';
		}
		return $style;
	}

	/**
	 * Build single dynamic style
	 */
	function build_style( $key, $is_link=false ) {		
		$style = $return_style = '';
		
		//Build font styles
		$font = $this->_get_style_value( $key.'_font' );
		$font_custom = $this->_get_style_value( $key.'_font-custom' );
		if ( !empty( $font ) && $font != 'default' && !empty( $font_custom ) ) $style .= 'font-family: "'.$font_custom.'";';

		$font_size = $this->_get_style_value( $key.'_font_size' );
		$font_size_custom = $this->_get_style_value( $key.'_font_size-custom' );
		if ( !empty( $font_size ) && $font_size != 'default' && !empty( $font_size_custom ) ) $style .= 'font-size: '.$font_size_custom.';';

		$font_color = $this->_get_style_value( $key.'_font_color' );
		$font_color_custom = $this->_get_style_value( $key.'_font_color-custom' );
		if ( !empty( $font_color ) && $font_color != 'default' && !empty( $font_color_custom ) ) $style .= 'color: '.$font_color_custom.';';

		$font_style = $this->_get_style_value( $key.'_font_style' );
		if ( !empty( $font_style ) && $font_style != 'default' ) {
			$font_style_bold = $this->_get_style_value( $key.'_font_style-bold' );
			if( $font_style_bold == "1" ) $style .= "font-weight: bold;";
			else $style .= "font-weight: normal;";

			$font_style_italic = $this->_get_style_value( $key.'_font_style-italic' );			
			if( $font_style_italic == "1" ) $style .= "font-style: italic;";
			else $style .= "font-style: normal;";

			$font_style_underline = $this->_get_style_value( $key.'_font_style-underline' );			
			if( $font_style_underline == "1" ) $style .= "text-decoration: underline;";
			else $style .= "text-decoration: none;";
		}

		//Build border styles
		$border_size = $this->_get_style_value( $key.'_border_size' );
		$border_size_custom = $this->_get_style_value( $key.'_border_size-custom' );
		if ( !empty( $border_size ) && $border_size != 'default' && !empty( $border_size_custom ) ) $style .= 'border-width: '.$border_size_custom.'pt;border-style:solid;';

		$border_color = $this->_get_style_value( $key.'_border_color' );
		$border_color_custom = $this->_get_style_value( $key.'_border_color-custom' );
		if ( !empty( $border_color ) && $border_color != 'default' && !empty( $border_color_custom ) ) $style .= 'border-color: '.$border_color_custom.';';

		$background_color = $this->_get_style_value( $key.'_background_color' );
		$background_color_custom = $this->_get_style_value( $key.'_background_color-custom' );
		if ( !empty( $background_color ) && $background_color != 'default' && !empty( $background_color_custom ) ) $style .= 'background-color: '.$background_color_custom.';';

		$margins = $this->_get_style_value( $key.'_margins' );
		if ( !empty( $margins ) && $margins != 'default' ) {
			$margin_top = $this->_get_style_value( $key.'_margins-top' );
			if( !empty( $margin_top ) ) $style .= "margin-top: {$margin_top}pt;";

			$margin_left = $this->_get_style_value( $key.'_margins-left' );
			if( !empty( $margin_left ) ) $style .= "margin-left: {$margin_left}pt;";

			$margin_right = $this->_get_style_value( $key.'_margins-right' );
			if( !empty( $margin_right ) ) $style .= "margin-right: {$margin_right}pt;";

			$margin_btm = $this->_get_style_value( $key.'_margins-btm' );
			if( !empty( $margin_btm ) ) $style .= "margin-bottom: {$margin_btm}pt;";			
		}

		$paddings = $this->_get_style_value( $key.'_paddings' );
		if ( !empty( $paddings ) && $paddings != 'default' ) {
			$padding_top = $this->_get_style_value( $key.'_paddings-top' );
			if( !empty( $padding_top ) ) $style .= "padding-top: {$padding_top}pt;";

			$padding_left = $this->_get_style_value( $key.'_paddings-left' );
			if( !empty( $padding_left ) ) $style .= "padding-left: {$padding_left}pt;";

			$padding_right = $this->_get_style_value( $key.'_paddings-right' );
			if( !empty( $padding_right ) ) $style .= "padding-right: {$padding_right}pt;";

			$padding_btm = $this->_get_style_value( $key.'_paddings-btm' );
			if( !empty( $padding_btm ) ) $style .= "padding-bottom: {$padding_btm}pt;";			
		}

		//Wrap into css class
		if( !empty( $style  ) ) {
			$css_class = str_replace('_', '-', $key);
			switch ($css_class) {
				case 'title':
					$return_style = "p.pt-".$css_class.", h4.pt-".$css_class." {" . $style . "}\n";
					$return_style .= "p.pt-".$css_class.">a, h4.pt-".$css_class.">a {border:none !important;}\n";
					$return_style .= "p.pt-".$css_class." a, h4.pt-".$css_class." a {" . $style . "}\n";
					break;

				case 'readmore':
					$return_style = "p.pt-".$css_class.", div.pt-".$css_class." {" . $style . "}\n";
					$return_style .= "p.pt-".$css_class.">a, div.pt-".$css_class.">a {border:none !important;}\n";
					$return_style .= "p.pt-".$css_class." a, div.pt-".$css_class." a {" . $style . "}\n";
					break;
				
				default: 
					$return_style = "p.pt-".$css_class.", div.pt-".$css_class." {" . $style . "}\n";
					break;
			}
			
		}
		
		return $return_style;
	}

	function getNumberName( $intNumber ) {
		$numbers = array( 1 => "one", 2 => "two", 3 => "three", 4 => "four", 5 => "five" );
		return isset($numbers[$intNumber]) ? $numbers[$intNumber] : "none";  	
	}

	/**
	 * Add PT tracking to the url so we can track external clicks from our service.
	 * This info is then aggregated for reports to the client
	 */

	function build_url_with_tracking( $url, $feedId, $isCurated, $docId, $contentType, $widgetType='' ) {

		if ( !empty( $url ) ) {
			if ( strpos( $url , "#" ) > 0 ) {
				//right now, no good way to get past urls that all ready have hash anchors
				return $url;
			}

			//great, build our own hash anchor for tracking purposes.
			$hashTracking = "";

			if ( !empty( $feedId ) ) {
				$hashTracking .= "fid=" . urlencode($feedId);
				$hashTracking .= "&";
			}

			if ( !empty( $isCurated ) ) {
				$hashTracking .= "isc=" . urlencode($isCurated);
				$hashTracking .= "&";
			}

			if ( !empty( $docId ) ) {
				$hashTracking .= "did=" . urlencode( $docId );
				$hashTracking .= "&";
			}

			if ( !empty( $contentType ) ) {
				$hashTracking .= "ctp=" . urlencode($contentType);
				$hashTracking .= "&";
			}
			
			if ( !empty( $widgetType ) ) {
				$hashTracking .= "wtp=" . urlencode($widgetType);
				$hashTracking .= "&";
			}
			
			if ( empty( $hashTracking ) ) {
				return $url;
			}else {
				//remove the end &
				$hashTracking = rtrim( $hashTracking, "&" );
	//			return $url . "#ptlink." . $hashTracking; /* Update the HTML generation. */
				return $url;
			}

		}
		return $url;
	}
	
	
}
