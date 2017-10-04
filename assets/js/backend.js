jQuery(document).ready(function() {
    var $ = jQuery,
        $popupCustomFields = $('#acf_dp-wpm-popup-fields'),
        $popupPreviewButton = $('#dp-wpm-popup-preview');

    if ($popupCustomFields.length) {
        $popupCustomFields.parent().children().not('.acf_postbox').hide();

        var $postBodyContent = $('#post-body-content');
        if ($postBodyContent.length) {
            $postBodyContent.find('#titlediv').children('.inside').hide();
        }
    }
    if ($popupPreviewButton.length) {
        $popupPreviewButton.find('a').on('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            $(this).parents('body').find('.dp-wpm-popup').fadeIn();
        });
    }
});