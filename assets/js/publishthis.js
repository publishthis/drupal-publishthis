(function ($) {
	$(document).ready(function() {
		bindImagesEvents( $("input[id*='edit-featured-image-html-body-image']") );

		$("input[id*='edit-featured-image-html-body-image']").live('click', function() {
			bindImagesEvents( $(this) );
		});

		$('.check-for-int input[type=text]').live('change', function() {
			var value = parseInt($(this).val());
			if( isNaN(value) ) {
				value = 0;
			}
			$(this).val( value );		
		});

		$("fieldset[id*='edit-image-customize'], fieldset[id*='edit-image-customize'] a.fieldset-title").live('click', function() {
			if( $(this).hasClass('disabled') ) return false;
		});

		$('input[name=image]').live('click', function() {
			if( $(this).hasClass('disabled') ) return false;
			var a = $("fieldset[id*='edit-image-customize'], fieldset[id*='edit-image-customize'] a.fieldset-title");
			if( !$(this).is(':checked') ) a.addClass('disabled');
			else a.removeClass('disabled');
		});
	});	

	function bindImagesEvents( el ) {
		if( el.is(':checked') ) {
			$('input[name=image]').attr('checked', true).addClass('disabled');
		}
		else {
			$('input[name=image]').removeClass('disabled');
		}

		$("fieldset[id*='edit-image-customize'], fieldset[id*='edit-image-customize'] a.fieldset-title").removeClass('disabled');
		$("fieldset[id*='edit-image-customize']").removeAttr('disabled').removeClass('form-disabled');
	}
}(jQuery));

