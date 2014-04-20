(function ($) {
  //$('#edit-customcolor').jPicker();

  $('#colorpickerField1, #colorpickerField2, #colorpickerField3').ColorPicker({
    onSubmit: function(hsb, hex, rgb, el) {
      $(el).val(hex);
      $(el).ColorPickerHide();
    },
    onBeforeShow: function () {
      $(this).ColorPickerSetColor(this.value);
    }
  })
  .bind('keyup', function(){
    $(this).ColorPickerSetColor(this.value);
  });
   
 
}(jQuery));
