<div class="wrap">
    <h2><?php echo __('Настройки менеджера попапов', DP_WPM_TEXT_DOMAIN); ?></h2>
    <?php settings_errors(); ?>
    <?php $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings-info'; ?>

    <h2 class="nav-tab-wrapper">
        <a href="?post_type=dp-wpm-popup&page=dp-wpm-options&tab=settings-info" class="nav-tab <?php echo $active_tab == 'settings-info' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Информация', DP_WPM_TEXT_DOMAIN); ?>
        </a>
        <a href="?post_type=dp-wpm-popup&page=dp-wpm-options&tab=settings-export-import" class="nav-tab <?php echo $active_tab == 'settings-export-import' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Экспорт/Импорт', DP_WPM_TEXT_DOMAIN); ?>
        </a>
        <a href="?post_type=dp-wpm-popup&page=dp-wpm-options&tab=settings-utm" class="nav-tab <?php echo $active_tab == 'settings-utm' ? 'nav-tab-active' : ''; ?>">
            <?php _e('UTM-Метки', DP_WPM_TEXT_DOMAIN); ?>
        </a>
    </h2>

    <?php require($active_tab . '.php'); ?>
</div>
