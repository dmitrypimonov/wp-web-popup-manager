<?php
/**
 * Plugin Name: Менеджер Попапов
 * Description: Плагин для управления попапами
 * Version: 1.0
 * Author: Dmitry Pimonov
 * Author URI: https://vk.com/heartilly
 * License: MIT
 */

/**
 * Название файла плагина
 * @var string
 */
define('DP_WPM_FILENAME', __FILE__);

/**
 * Версия плагина
 * @var string
 */
define('DP_WPM_VERSION', '1.0');

/**
 * Путь до папки с плагином
 * @var string
 */
define('DP_WPM_DIR', plugin_dir_path(__FILE__));

/**
 * URL по которому доступен плагин
 * @var string
 */
define('DP_WPM_URL', plugin_dir_url(__FILE__));

/**
 * Константа для локализации
 * @var string
 */
define('DP_WPM_TEXT_DOMAIN', 'dp-wpm');

/** -- Регистрируем плагин -- **/
require_once(DP_WPM_DIR . 'includes' . DIRECTORY_SEPARATOR . 'app.php');
add_action('plugins_loaded', function() {
    try {
        $wpmApplication = new WPM_Application();
        $wpmApplication->run();
    } catch (Exception $e) {
        echo $e->getMessage();
    }
});
