<?php

// UTM-метки по умолчанию
$utmDefault = "utm_source=gkzmoney\r\nutm_medium=affiliate\r\naff_medium=organic_sites\r\naff_source=gdeikakzarabotat.ru\r\naff_campaign=popup";
$message = '';
$repeat = 0;

if (isset($_REQUEST['action'])) {
    if ($_REQUEST['action'] == 'save') {
        $newUtm = str_replace(array("\r\n", "\r", "\n"), '{::}', $_REQUEST['utm']);
        $utmArray = explode('{::}', $newUtm);
        for ($i = 0; $i < count($utmArray); $i++) {
            $utmArray[$i] = str_replace(':', '=', $utmArray[$i]);
        }
        $utmString = implode('&', $utmArray);

        if (preg_match('/^([A-Za-z\-0-9\_\.]+\=[A-Za-z\-0-9\_\.\#]+\&{0,1})*$/', $utmString)) {
            update_option('dp-wpm-utm-string', $_REQUEST['utm']);
            update_option('dp-wpm-utm-string-prepared', $utmString);
            $message = __('UTM-Метки успешно сохранены!', DP_WPM_TEXT_DOMAIN);
        } else {
            $message = __('Произошла ошибка при сохранении настроек! Проверьте правильность введённых меток!', DP_WPM_TEXT_DOMAIN);
            $repeat = 1;
        }
    }
}
?>

<?php if (strlen($message) > 0): ?>
    <h4><?php echo $message; ?></h4>
<?php endif; ?>

<p class="dp-wpm-italic-font">
    <?php _e('
        <span class="dp-wpm-normal-font">Введите метки, которые будут добавляться при вызове попапа. Метки вносятся в формате ключ=значение. Каждая метка вносится с новой строки.</span><br><br>

        Пример:<br>
        utm_source=gkzmoney<br>
        utm_medium=affiliate<br>
        ... <br>
        aff_medium=organic_sites<br><br>', DP_WPM_TEXT_DOMAIN
    ); ?>
</p>
<form action="?post_type=dp-wpm-popup&page=dp-wpm-options&tab=settings-utm" method="post" class="dp-wpm-utm-form">
    <input type="hidden" name="action" value="save">
    <h2><label for="utm-form"><?php _e('Метки связанные с попапами', DP_WPM_TEXT_DOMAIN); ?></label></h2><br>
    <textarea name="utm" id="utm-form"><?php echo ($repeat ? $_REQUEST['utm'] : get_option('dp-wpm-utm-string', $utmDefault)); ?></textarea><br>
    <button type="submit" class="dp-wpm-form-button"><?php _e('Сохранить', DP_WPM_TEXT_DOMAIN); ?></button>
</form>
