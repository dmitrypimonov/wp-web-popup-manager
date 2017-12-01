//Возвращает массив GET параметров
function parseGET(url) {
    var GET = [];

    if(!url || url == '') url = document.location.search;
    if(url.indexOf('?') < 0) {
        GET['parse'] = false;
        return (GET);
    }else {
        GET['parse'] = true;
        url = url.split('?');
        url = url[1];

        var params = [];
        var keyval = [];

        if (url.indexOf('#') != -1) {
            var anchor = url.substr(url.indexOf('#') + 1);
            url = url.substr(0, url.indexOf('#'));
        }

        if (url.indexOf('&') > -1) params = url.split('&');
        else params[0] = url;

        for (var i = 0; i < params.length; i++) {
            if (params[i].indexOf('=') > -1) keyval = params[i].split('=');
            else {
                keyval[0] = params[i];
                keyval[1] = true;
            }
            GET[keyval[0]] = keyval[1];
        }

        return (GET);
    }
}

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

    var $postTitle = $('input[name="post_title"]'),
        $_GET = parseGET();

    if ($postTitle.length) {
        if (typeof($_GET['post']) !== typeof(undefined) && $_GET['action'] == 'edit') {
            var shortcodeInfo = '<span style="display: inline-block; padding-top: 5px;">' +
                                '    <strong>Шорткод: </strong>[web-popup id="' + $_GET['post'] + '"]Текст ссылки[/web-popup]' +
                                '</span>';

            $postTitle.parent().append(shortcodeInfo);
        }
    }
});