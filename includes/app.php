<?php

/**
 * Class WPM_Application
 * Основной класс для работы с плагином
 */

class WPM_Application
{
    /**
     * Backend режим
     * @var integer
     */
    const MODE_BACKEND = 1;

    /**
     * Frontend режим
     * @var integer
     */
    const MODE_FRONTEND = 2;

    /**
     * Текущий режим
     * @var integer
     */
    protected $currentMode;

    /**
     * Типы шаблонов попапов
     * @var array
     */
    protected $popupTemplatesTypes;

    /**
     * WPM_Application constructor.
     * @param bool|false $_autoStart
     */
    public function __construct($_autoStart = false)
    {
        // Регистрируем хук активации
        register_activation_hook(DP_WPM_FILENAME, array($this, 'activate'));

        // Грузим файл локализации
        load_textdomain(DP_WPM_TEXT_DOMAIN, DP_WPM_DIR . 'lang' . DIRECTORY_SEPARATOR . DP_WPM_TEXT_DOMAIN . '-' . get_locale() . '.mo');

        // Инициализируем типы шаблонов
        $this->popupTemplatesTypes = array(
            1 => __('C формой', DP_WPM_TEXT_DOMAIN),
            2 => __('C кнопкой', DP_WPM_TEXT_DOMAIN)
        );

        if (is_admin()) {
            $this->currentMode = self::MODE_BACKEND;
        } else {
            $this->currentMode = self::MODE_FRONTEND;
        }

        if ($_autoStart) {
            $this->run();
        }
    }

    /**
     * Активирует плагин
     * @return void
     */
    public static function activate()
    {
        // Проверяем активность плагина ACF. И если его нет, то останавливаем активацию
        if (!class_exists('acf')) {
            wp_die(__('Для активации плагина «Менеджер попапов» необходим установленный и активированный плагин «Advanced Custom Fields»!', DP_WPM_TEXT_DOMAIN));
        }
    }

    /**
     * Запускает плагин
     * @return void
     */
    public function run()
    {
        // Если не найдена критическая функция, то просим установить плагин «Advanced Custom Fields»
        if (!function_exists('register_field_group')) {
            $this->activate();
        }

        // Подключаем основной функционал
        add_action('init', array($this, 'registerCustomPostTypes'), PHP_INT_MAX);

        // Регистрируем роуты для WP-API
        $this->registerAPIRoutes();

        // Настраиваем single-страницу попапа
        $this->configureSinglePage();

        // Добавляем шорткод версии ;)
        add_shortcode('dp-wpm-plugin-version', array($this, 'versionShortCode'));
        add_shortcode('web-popup', array($this, 'popupShortCode'));

        // Функционал отличается в зависимости от текущего режима
        if ($this->currentMode === self::MODE_FRONTEND) {

            // Добавляем скрипты обработчики
            add_action('wp_enqueue_scripts', function() {

                // Если страница показа попапа, то убираем лишние скрипты, чтобы ускорить загрузку страницы
                if (WPM_Application::isSinglePopupPage()) {
                    if (isset($GLOBALS['wp_scripts'])) {
                        foreach ($GLOBALS['wp_scripts']->registered as $scriptName => $object) {
                            wp_dequeue_script($scriptName);
                        }
                    }
                }

                // Добавляем наши скрипты и стили
                wp_enqueue_script('jquery');
                wp_enqueue_script('dp-wpm-frontend-script', DP_WPM_URL . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'frontend.js');
                wp_enqueue_script('dp-wpm-backend-script', DP_WPM_URL . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'backend.js');
                wp_enqueue_style('dp-wpm-bootstrap-css', DP_WPM_URL . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'bootstrap.css');
                wp_enqueue_style('dp-wpm-frontend-css', DP_WPM_URL . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'frontend.css');

                // Настраиваем целевые страницы
                WPM_Application::configureTargetPages();

            }, PHP_INT_MAX);

        } else {

            // Убираем не нужные пункты из меню
            add_action('admin_menu', function() {
                remove_submenu_page('edit.php?post_type=dp-wpm-popup', 'post-new.php?post_type=dp-wpm-popup');
                add_submenu_page('edit.php?post_type=dp-wpm-popup', __('Настройки менеджера попапов', DP_WPM_TEXT_DOMAIN), __('Настройки', DP_WPM_TEXT_DOMAIN), 'manage_options', 'dp-wpm-options', array($this, 'optionsPage'));

                $taxonomies = get_taxonomies();
                foreach ($taxonomies as $taxonomy) {
                    remove_submenu_page('edit.php?post_type=dp-wpm-popup', "edit-tags.php?taxonomy=$taxonomy&amp;post_type=dp-wpm-popup");
                }
            });

            // Добавляем скрипты обработчики
            add_action('admin_enqueue_scripts', function() {
                wp_enqueue_script('jquery');
                wp_enqueue_script('dp-wpm-backend-script', DP_WPM_URL . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'backend.js');
                wp_enqueue_style('dp-wpm-backend-css', DP_WPM_URL . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'backend.css');
            });

            // Добавляем стили в редактор
            add_filter('mce_css', function($_url) {
                if (strlen($_url) > 0) {
                    $_url .= ',';
                }

                $_url .= DP_WPM_URL . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'bootstrap.css';
                return $_url;
            });
        }
    }

    /**
     * Регистрирует типы записей для работы попапов
     * @return void
     */
    public function registerCustomPostTypes()
    {
        // Регистрируем основной тип записи «Попапы»
        register_post_type('dp-wpm-popup', array(
            'label'  => null,
            'labels' => array(
                'name'               => __('Попапы', DP_WPM_TEXT_DOMAIN), // основное название для типа записи
                'singular_name'      => __('Попап', DP_WPM_TEXT_DOMAIN), // название для одной записи этого типа
                'all_items'          => __('Все попапы', DP_WPM_TEXT_DOMAIN), // название списка всех записей
                'add_new'            => __('Добавить попап', DP_WPM_TEXT_DOMAIN), // для добавления новой записи
                'add_new_item'       => __('Добавление попапа', DP_WPM_TEXT_DOMAIN), // заголовка у вновь создаваемой записи в админ-панели.
                'edit_item'          => __('Редактирование попапа', DP_WPM_TEXT_DOMAIN), // для редактирования типа записи
                'new_item'           => __('Новый попап', DP_WPM_TEXT_DOMAIN), // текст новой записи
                'view_item'          => __('Смотреть попап', DP_WPM_TEXT_DOMAIN), // для просмотра записи этого типа.
                'search_items'       => __('Искать попап', DP_WPM_TEXT_DOMAIN), // для поиска по этим типам записи
                'not_found'          => __('Не найдено', DP_WPM_TEXT_DOMAIN), // если в результате поиска ничего не было найдено
                'not_found_in_trash' => __('Не найдено в корзине', DP_WPM_TEXT_DOMAIN), // если не было найдено в корзине
                'menu_name'          => __('Попапы', DP_WPM_TEXT_DOMAIN), // название меню
            ),
            'public'              => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => false,
            'show_ui'             => true,
            'show_in_menu'        => true, // показывать ли в меню адмнки
            'capability_type'     => 'page',
            'menu_position'       => 6,
            'menu_icon'           => 'dashicons-align-center',
            'hierarchical'        => false,
            'supports'            => array('title', 'thumbnail'),
            'taxonomies'          => array_diff(get_taxonomies(), array('link_category')),
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => true,
        ));

        // Здесь нам надо распределить шаблоны попапов для select'ов
        $popupTemplatePosts = get_posts(array('post_type' => 'dp-wpm-templates', 'numberposts' => -1));
        $formPopupChoices = $buttonPopupChoices = array();

        foreach ($popupTemplatePosts as $post) {
            $customFields = get_post_custom($post->ID);
            $popupTemplateType = array_shift($customFields['dp-wpm-popup-templates-type']);

            // Шаблон попапа с формой
            if ($popupTemplateType == 1) {
                $formPopupChoices[$post->ID] = $post->post_title;
            } else {
                $buttonPopupChoices[$post->ID] = $post->post_title;
            }
        }

        // Добавляем произвольные поля типу записи «Попапы»
        register_field_group(array(
            'id' => 'dp-wpm-popup-fields',
            'title'    => __('Параметры попапа', DP_WPM_TEXT_DOMAIN),
            'fields' => array (
                array(
                    'key'           => 'dp-wpm-popup-fields-type',
                    'label'         => __('Тип шаблона попапа', DP_WPM_TEXT_DOMAIN),
                    'name'          => 'dp-wpm-popup-fields-type',
                    'type'          => 'select',
                    'choices'       => $this->popupTemplatesTypes,
                    'required'      => 1,
                    'default_value' => 1
                ),
                array(
                    'key'          => 'dp-wpm-popup-fields-template-1',
                    'label'        => __('Шаблон попапа', DP_WPM_TEXT_DOMAIN),
                    'name'         => 'dp-wpm-popup-fields-template-1',
                    'type'         => 'select',
                    'choices' => $formPopupChoices,
                    'conditional_logic' => array(
                        'status' => 1,
                        'rules'  => array(
                            array(
                                'field'    => 'dp-wpm-popup-fields-type',
                                'operator' => '==',
                                'value'    => '1',
                            ),
                        ),
                        'allorany' => 'all',
                    ),
                    'required'      => 1,
                    'multiple'      => 0
                ),
                array(
                    'key'          => 'dp-wpm-popup-fields-template-2',
                    'label'        => __('Шаблон попапа', DP_WPM_TEXT_DOMAIN),
                    'name'         => 'dp-wpm-popup-fields-template-2',
                    'type'         => 'select',
                    'choices' => $buttonPopupChoices,
                    'conditional_logic' => array(
                        'status' => 1,
                        'rules'  => array(
                            array(
                                'field'    => 'dp-wpm-popup-fields-type',
                                'operator' => '==',
                                'value'    => '2',
                            ),
                        ),
                        'allorany' => 'all',
                    ),
                    'required'     => 1,
                    'multiple'     => 0
                ),
                array(
                    'key'          => 'dp-wpm-popup-fields-class',
                    'label'        => __('Класс элемента, который откроет попап', DP_WPM_TEXT_DOMAIN),
                    'name'         => 'dp-wpm-popup-fields-class',
                    'prepend'      => 'am_popup_',
                    'type'         => 'text',
                    'required'     => 1
                ),
                array(
                    'key'          => 'dp-wpm-popup-fields-open-link',
                    'label'        => __('Ссылка, по которой откроется попап', DP_WPM_TEXT_DOMAIN),
                    'name'         => 'dp-wpm-popup-fields-open-link',
                    'type'         => 'text',
                    'required'     => 1
                ),
                array(
                    'key'          => 'dp-wpm-popup-fields-popup-title',
                    'label'        => __('Заголовок попапа', DP_WPM_TEXT_DOMAIN),
                    'name'         => 'dp-wpm-popup-fields-popup-title',
                    'type'         => 'text',
                    'required'     => 1
                ),
                array(
                    'key'          => 'dp-wpm-popup-fields-popup-desc',
                    'label'        => __('Текст под заголовком', DP_WPM_TEXT_DOMAIN),
                    'name'         => 'dp-wpm-popup-fields-popup-desc',
                    'type'         => 'textarea',
                    'required'     => 0
                ),
                array(
                    'key'          => 'dp-wpm-popup-fields-inner-form',
                    'label'        => __('Встроенная форма', DP_WPM_TEXT_DOMAIN),
                    'name'         => 'dp-wpm-popup-fields-inner-form',
                    'type'         => 'textarea',
                    'conditional_logic' => array(
                        'status' => 1,
                        'rules'  => array(
                            array(
                                'field'    => 'dp-wpm-popup-fields-type',
                                'operator' => '==',
                                'value'    => '1',
                            ),
                        ),
                        'allorany' => 'all',
                    ),
                    'instructions' => '',
                    'required'     => 0
                ),
                array(
                    'key'           => 'dp-wpm-popup-fields-target-url',
                    'label'         => __('URL целевого действия', DP_WPM_TEXT_DOMAIN),
                    'name'          => 'dp-wpm-popup-fields-target-url',
                    'type'          => 'text',
                    'conditional_logic' => array(
                        'status' => 1,
                        'rules' => array(
                            array(
                                'field'    => 'dp-wpm-popup-fields-type',
                                'operator' => '==',
                                'value'    => '2',
                            ),
                        ),
                        'allorany' => 'all',
                    ),
                    'required'      => 0
                ),
                array(
                    'key'          => 'dp-wpm-popup-fields-popup-button-text',
                    'label'        => __('Текст кнопки призыва к действию', DP_WPM_TEXT_DOMAIN),
                    'name'         => 'dp-wpm-popup-fields-popup-button-text',
                    'type'         => 'text',
                    'required'     => 1,
                    'default_value' => __('Получить доступ', DP_WPM_TEXT_DOMAIN)
                ),
                array(
                    'key'           => 'dp-wpm-popup-fields-show-sec',
                    'label'         => __('Показать попап через', DP_WPM_TEXT_DOMAIN),
                    'name'          => 'dp-wpm-popup-fields-show-sec',
                    'append'        => __('секунд', DP_WPM_TEXT_DOMAIN),
                    'type'          => 'text',
                    'required'      => 1,
                    'default_value' => '30'
                ),
                array(
                    'key'           => 'dp-wpm-popup-fields-show-repeat-sec',
                    'label'         => __('В случае закрытия попапа, повторно показать через', DP_WPM_TEXT_DOMAIN),
                    'name'          => 'dp-wpm-popup-fields-show-repeat-sec',
                    'append'        => __('секунд', DP_WPM_TEXT_DOMAIN),
                    'type'          => 'text',
                    'required'      => 1,
                    'default_value' => '86400'
                ),
                array(
                    'key'           => 'dp-wpm-popup-fields-max-show-repeat',
                    'label'         => __('Максимальное количество показов в случае закрытия', DP_WPM_TEXT_DOMAIN),
                    'name'          => 'dp-wpm-popup-fields-max-show-repeat',
                    'type'          => 'text',
                    'required'      => 1,
                    'default_value' => '5'
                )
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'dp-wpm-popup',
                    )
                )
            ),
            'menu_order'            => 1,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label'
        ));

        // Задаём перечень колонок типа записи «Попапы»
        add_filter('manage_dp-wpm-popup_posts_columns', function() {
            $_postsColumns = array(
                "title"  => __('Заголовок', DP_WPM_TEXT_DOMAIN),
                "author" => __('Автор', DP_WPM_TEXT_DOMAIN),
                "date"   => __('Дата', DP_WPM_TEXT_DOMAIN),
                "code"   => __('Шорткод', DP_WPM_TEXT_DOMAIN)
            );

            return $_postsColumns;
        }, PHP_INT_MAX);

        // Инициализируем кастомную колонку
        add_action('manage_dp-wpm-popup_posts_custom_column', function($_column) {
            global $post;

            switch ($_column) {
                case 'code':
                    echo '[web-popup id="' . $post->ID . '"]Текст ссылки[/web-popup]';
                    break;
            }
        }, PHP_INT_MAX);

        // Удаляем не нужные метабоксы у типа записи «Попапы»
        add_action('add_meta_boxes_dp-wpm-popup', function() {
            if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-popup'])) {
                if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['side']['high'])) {
                    unset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['side']['high']);
                }
                if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['normal']['high'])) {
                    unset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['normal']['high']);
                }
                if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['normal']['low'])) {
                    unset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['normal']['low']);
                }
                if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['advanced']['high'])) {
                    unset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['advanced']['high']);
                }
                if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['advanced']['default'])) {
                    unset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['advanced']['default']);
                }
                if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['advanced']['low'])) {
                    unset($GLOBALS['wp_meta_boxes']['dp-wpm-popup']['advanced']['low']);
                }
            }
        }, PHP_INT_MAX);

        // Регистрируем второй тип записи «Шаблоны попапов»
        register_post_type('dp-wpm-templates', array(
            'label'  => null,
            'labels' => array(
                'name'               => __('Шаблоны попапов', DP_WPM_TEXT_DOMAIN), // основное название для типа записи
                'singular_name'      => __('Шаблон попапа', DP_WPM_TEXT_DOMAIN), // название для одной записи этого типа
                'all_items'          => __('Шаблоны попапов', DP_WPM_TEXT_DOMAIN), // название списка всех записей
                'add_new'            => __('Добавить шаблон попапа', DP_WPM_TEXT_DOMAIN), // для добавления новой записи
                'add_new_item'       => __('Добавление шаблона попапа', DP_WPM_TEXT_DOMAIN), // заголовка у вновь создаваемой записи в админ-панели.
                'edit_item'          => __('Редактирование шаблона попапа', DP_WPM_TEXT_DOMAIN), // для редактирования типа записи
                'new_item'           => __('Новый шаблон попапа', DP_WPM_TEXT_DOMAIN), // текст новой записи
                'view_item'          => __('Смотреть шаблон попапа', DP_WPM_TEXT_DOMAIN), // для просмотра записи этого типа.
                'search_items'       => __('Искать шаблон попапа', DP_WPM_TEXT_DOMAIN), // для поиска по этим типам записи
                'not_found'          => __('Не найдено', DP_WPM_TEXT_DOMAIN), // если в результате поиска ничего не было найдено
                'not_found_in_trash' => __('Не найдено в корзине', DP_WPM_TEXT_DOMAIN), // если не было найдено в корзине
                'menu_name'          => __('Шаблоны попапов', DP_WPM_TEXT_DOMAIN), // название меню
            ),
            'public'              => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => false,
            'show_ui'             => true,
            'capability_type'     => 'page',
            'show_in_menu'        => 'edit.php?post_type=dp-wpm-popup',
            'menu_position'       => 10,
            'hierarchical'        => false,
            'supports'            => array('title', 'editor'),
            'taxonomies'          => array(),
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => true,
        ));

        // Добавляем произвольные поля типу записи «Шаблоны попапов»
        register_field_group(array(
            'id' => 'dp-wpm-templates-fields',
            'title'    => __('Параметры шаблона попапа', DP_WPM_TEXT_DOMAIN),
            'fields' => array (
                array(
                    'key'           => 'dp-wpm-popup-templates-type',
                    'label'         => __('Тип шаблона', DP_WPM_TEXT_DOMAIN),
                    'name'          => 'dp-wpm-popup-templates-type',
                    'type'          => 'select',
                    'choices'       => $this->popupTemplatesTypes,
                    'required'      => 1,
                    'default_value' => 1
                ),
                array(
                    'key'          => 'dp-wpm-popup-templates-css-form',
                    'label'        => __('[CSS] Пользовательские стили для шаблона', DP_WPM_TEXT_DOMAIN),
                    'name'         => 'dp-wpm-popup-templates-css-form',
                    'type'         => 'textarea',
                    'placeholder'  => __('Ввёденные стили будут связаны с шаблоном!', DP_WPM_TEXT_DOMAIN),
                    'required'     => 0
                )
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'dp-wpm-templates',
                    )
                )
            ),
            'menu_order'            => 100,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label'
        ));

        // Добавляем описашку по умолчанию в редактор
        add_filter('the_editor_content', function($_content) {
            if (isset($GLOBALS['post'])) {
                if (!empty($GLOBALS['post'])) {
                    if (strlen($_content) == 0 && $GLOBALS['post']->post_type == 'dp-wpm-templates') {
                        $_content = __('Для создания шаблона можно использовать подключенный Visual Composer, либо сетку «Bootstrap» с префиксом «wpm». К примеру wpm-container или wpm-col-xs. Перечень существующих макросов доступен на вкладке Настройки->Информация.', DP_WPM_TEXT_DOMAIN);
                    }
                }
            }

            return $_content;
        });

        // Задаём перечень колонок типа записи «Шаблоны попапов»
        add_filter('manage_dp-wpm-templates_posts_columns', function() {
            $_postsColumns = array(
                "title"  => __('Заголовок', DP_WPM_TEXT_DOMAIN),
                "type"   => __('Тип шаблона', DP_WPM_TEXT_DOMAIN),
                "author" => __('Автор', DP_WPM_TEXT_DOMAIN),
                "date"   => __('Дата', DP_WPM_TEXT_DOMAIN)
            );

            return $_postsColumns;
        }, PHP_INT_MAX);

        // Инициализируем кастомную колонку
        add_action('manage_dp-wpm-templates_posts_custom_column', function($_column) {
            global $post;

            $postCustomFields = get_post_custom($post->ID);
            $postTemplateType = intval(array_shift($postCustomFields['dp-wpm-popup-templates-type']));

            switch ($_column) {
                case 'type':
                    echo $this->popupTemplatesTypes[$postTemplateType];
                    break;
            }
        }, PHP_INT_MAX);

        // Удаляем не нужные метабоксы у типа записи «Шаблоны попапов»
        add_action('add_meta_boxes_dp-wpm-templates', function() {
            if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-templates'])) {
                if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-templates']['side']['high'])) {
                    unset($GLOBALS['wp_meta_boxes']['dp-wpm-templates']['side']['high']);
                }
                if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-templates']['normal']['low'])) {
                    unset($GLOBALS['wp_meta_boxes']['dp-wpm-templates']['normal']['low']);
                }
                if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-templates']['advanced']['high'])) {
                    unset($GLOBALS['wp_meta_boxes']['dp-wpm-templates']['advanced']['high']);
                }
                if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-templates']['advanced']['default'])) {
                    unset($GLOBALS['wp_meta_boxes']['dp-wpm-templates']['advanced']['default']);
                }
                if (isset($GLOBALS['wp_meta_boxes']['dp-wpm-templates']['advanced']['low'])) {
                    unset($GLOBALS['wp_meta_boxes']['dp-wpm-templates']['advanced']['low']);
                }
            }
        }, PHP_INT_MAX);
    }

    /**
     * Страница настроек
     * @return void
     */
    public function optionsPage()
    {
        require(DP_WPM_DIR . 'includes' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'settings-main.php');
    }

    /**
     * Регистрирует роуты для обращения по WP-API
     * @return void
     */
    public function registerAPIRoutes()
    {
        add_action('rest_api_init', function () {
            register_rest_route('web-popup-manager/v1', '/popups', array(
                'methods'  => 'GET',
                'callback' => array($this, 'getPopupsList')
            ));
        });
    }

    /**
     * Возвращает сформированные попапы и информацию по ним
     * @return array
     */
    public function getPopupsList()
    {
        // Получаем попапы
        $popupPosts = get_posts(array('post_type' => 'dp-wpm-popup', 'numberposts' => -1));

        // Собираем попапы из шаблонов
        foreach ($popupPosts as $key => $post) {
            $customFields = get_post_custom($post->ID);
            $popupPosts[$key]->meta_fields = $customFields;
            $popupPosts[$key]->permalink = get_the_permalink($post->ID);

            $utmString = get_option('dp-wpm-utm-string-prepared');
            $popupPosts[$key]->utm = $utmString ? $utmString : '';
        }

        return $popupPosts;
    }

    /**
     * Настройка для single-страницы попапа
     * @return void
     */
    public function configureSinglePage()
    {
        add_action('template_redirect', function() {
            if (WPM_Application::isSinglePopupPage()) {
                header('Access-Control-Allow-Origin: *');
                ob_start();
                add_action('wp_footer', array($this, 'showConfiguredSinglePopupPage'));
            }
        });
    }

    /**
     * Показывает конкретную страницу попапа + предпросмотр
     * @return void
     */
    public function showConfiguredSinglePopupPage()
    {
        // Получаем контент страницы
        $htmlPage = ob_get_contents();
        ob_end_clean();

        // Закрываем теги, где это необходимо
        if (!mb_strpos($htmlPage, '</body>', 0, 'utf-8')) {
            $htmlPage .= '</body>';
        }
        if (!mb_strpos($htmlPage, '</html>', 0, 'utf-8')) {
            $htmlPage .= '</html>';
        }

        // Вырезаем контент, так как у нас будет свой
        $bodyStartPosition = mb_strpos($htmlPage, '<body', 0, 'utf-8');
        $bodyEndPosition = mb_strrpos($htmlPage, '</body>', 0, 'utf-8');
        $firstPartOfHtmlPage = mb_substr($htmlPage, 0, $bodyStartPosition - 1, 'utf-8');
        $lastPartOfHtmlPage = mb_substr($htmlPage, $bodyEndPosition + 7, null, 'utf-8');

        // Определяем наш пост и получаем шаблон
        $popup = $GLOBALS['post'];
        $postCustomFields = get_post_custom($popup->ID);
        $popupType = array_shift($postCustomFields['dp-wpm-popup-fields-type']);
        $popupTemplateID = array_shift($postCustomFields["dp-wpm-popup-fields-template-$popupType"]);

        // Если определился шаблон, то собираем попап
        if ($popupTemplateID) {
            $popupTemplate = get_post($popupTemplateID);
            $popupTemplateCustomFields = get_post_custom($popupTemplate->ID);
            $popupTemplateContent = $popupTemplate->post_content;

            // Заменяем шорткоды в контенте
            $popupTemplateContent = do_shortcode(do_shortcode($popupTemplateContent));

            // Определяем переменные для замены
            $title = isset($postCustomFields['dp-wpm-popup-fields-popup-title']) ? array_shift($postCustomFields['dp-wpm-popup-fields-popup-title']) : '';
            $desc = isset($postCustomFields['dp-wpm-popup-fields-popup-desc']) ? array_shift($postCustomFields['dp-wpm-popup-fields-popup-desc']) : '';
            $actionUrl = isset($postCustomFields['dp-wpm-popup-fields-target-url']) ? array_shift($postCustomFields['dp-wpm-popup-fields-target-url']) : '';
            $buttonName = isset($postCustomFields['dp-wpm-popup-fields-popup-button-text']) ? array_shift($postCustomFields['dp-wpm-popup-fields-popup-button-text']) : __('Получить доступ', DP_WPM_TEXT_DOMAIN);
            $innerForm = isset($postCustomFields['dp-wpm-popup-fields-inner-form']) ? array_shift($postCustomFields['dp-wpm-popup-fields-inner-form']) : '';
            $formCSS = isset($popupTemplateCustomFields['dp-wpm-popup-templates-css-form']) ? array_shift($popupTemplateCustomFields['dp-wpm-popup-templates-css-form']) : '';
            $thumbnail = get_the_post_thumbnail_url($popup->ID, 'full');

            // Меняем макросы
            $finalPopup = str_replace('[wpm-title]', $title, $popupTemplateContent);
            $finalPopup = str_replace('[wpm-desc]', $desc, $finalPopup);
            $finalPopup = str_replace('[wpm-img]', '<img src="' . $thumbnail . '" alt="' . $title . '">', $finalPopup);
            $finalPopup = str_replace('[wpm-button-name]', $buttonName, $finalPopup);

            // В зависимости от типа попапа у нас разные обработчики
            if ($popupType == 1) {

                // Если есть форма, то обрабатываем, иначе пытаемся заменить макрос
                if (mb_strpos($finalPopup, '</form>', 0, 'utf-8') && mb_strpos($innerForm, '</form>', 0, 'utf-8')) {

                    $templateDOM = new DOMDocument();
                    $templateDOM->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $finalPopup);
                    $templateFormElement = $templateDOM->getElementsByTagName('form')->item(0);

                    $innerFormDOM = new DOMDocument();
                    $innerFormDOM->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $innerForm);
                    $innerFormElement = $innerFormDOM->getElementsByTagName('form')->item(0);

                    // Меняем атрибуты формы
                    if ($innerFormElement->hasAttributes()) {
                        foreach ($innerFormElement->attributes as $attr) {
                            $name = $attr->nodeName;
                            $value = $attr->nodeValue;

                            if (strlen($value)) {
                                $templateFormElement->setAttribute($name, $value);
                            }
                        }
                    }

                    // Вставляем input-элементы
                    $templateFormInputs = $templateFormElement->getElementsByTagName('input');
                    $innerFormInputs = $innerFormElement->getElementsByTagName('input');

                    for ($i = 0; $i < $innerFormInputs->length; $i++) {
                        $currentInput = $innerFormInputs->item($i);

                        if ($currentInput->getAttribute('type') == 'hidden') {
                            $findElement = false;

                            for ($l = 0; $l < $templateFormInputs->length; $l++) {
                                $templateInput = $templateFormInputs->item($l);

                                if ($templateInput->getAttribute('type') == 'hidden' && $templateInput->getAttribute('name') == $currentInput->getAttribute('name')) {
                                    if (strlen($inputValue = $currentInput->getAttribute('value'))) {
                                        $templateInput->setAttribute('value', $inputValue);
                                        $findElement = true;
                                    }
                                }
                            }

                            if (!$findElement) {
                                $templateFormElement->appendChild($templateDOM->importNode($currentInput, true));
                            }
                        }
                    }

                    // HTML-представление попапа
                    $finalPopup = $templateDOM->saveHTML();

                    // Вытаскиваем сформированный попап
                    $popupBodyStartPosition = mb_strpos($finalPopup, '<body>', 0, 'utf-8');
                    $popupBodyEndPosition = mb_strrpos($finalPopup, '</body>', 0, 'utf-8');
                    $finalPopup = mb_substr($finalPopup, $popupBodyStartPosition + 6, ($popupBodyEndPosition - $popupBodyStartPosition - 6), 'utf-8');

                } else {
                    $finalPopup = str_replace('[wpm-form]', $innerForm, $finalPopup);
                }

            } else {
                $finalPopup = str_replace('[wpm-action-url]', $actionUrl, $finalPopup);
            }

            //Вставляем попап в модальное окно
            ob_start();
            require(DP_WPM_DIR . 'includes' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'popup-window.php');
            $finalPopup = ob_get_contents();
            ob_end_clean();

            // Еслие есть стили, то применяем
            if (strlen($formCSS)) {
                $firstPartOfHtmlPage = str_replace('</head>', "<style>$formCSS</style></head>", $firstPartOfHtmlPage);
            }

            // Формируем и отдаём готовую страницу
            $htmlPage = $firstPartOfHtmlPage . '<body>' . $finalPopup . '</body>' . $lastPartOfHtmlPage;
            echo $htmlPage;
        }

        exit;
    }

    /**
     * Настраивает целевые страницы для показа попапов
     * @return void
     */
    public static function configureTargetPages()
    {
        if (!WPM_Application::isSinglePopupPage()) {
            if (is_tag() || is_single() || is_category()) {
                // Определяем доступные таксономии
                $availableTaxonomies = array_diff(get_taxonomies(), array('link_category'));

                // Определяем какие категории сейчас отображаются
                $currentCategoryArray = array();
                if (is_tag() || is_category()) {
                    $queriedObject = get_queried_object();
                    $currentCategoryArray[] = $queriedObject->term_id;
                } elseif (is_single() && isset($GLOBALS['post'])) {
                    foreach ($availableTaxonomies as $taxonomy) {
                        $postTerms = get_the_terms($GLOBALS['post'], $taxonomy);
                        if (is_array($postTerms)) {
                            foreach ($postTerms as $term) {
                                $currentCategoryArray[] = $term->term_id;
                            }
                        }
                    }
                }

                // Смотрим надо ли нам показывать в этих категориях какой-либо попап
                $targetPopups = array();
                $popupPosts = get_posts(array('post_type' => 'dp-wpm-popup', 'numberposts' => -1));

                foreach ($popupPosts as $post) {
                    $popupTermsIDs = array();

                    foreach ($availableTaxonomies as $taxonomy) {
                        $postTerms = get_the_terms($post, $taxonomy);
                        if (is_array($postTerms)) {
                            foreach ($postTerms as $term) {
                                $popupTermsIDs[] = $term->term_id;
                            }
                        }
                    }

                    $existenceCategories = array_intersect($popupTermsIDs, $currentCategoryArray);
                    if (count($existenceCategories)) {
                        $targetPopups[] = $post->ID;
                    }
                }

                // Добавляем объект с ID попапов для показа
                wp_localize_script('dp-wpm-frontend-script', 'wpmShowPopupIDs', (count($targetPopups) ? implode('_', $targetPopups) : ''));
            }
        }
    }

    /**
     * Проверяет, является ли текущая страница страницей показа попапа
     * @return bool
     */
    public static function isSinglePopupPage()
    {
        if (is_single() && isset($GLOBALS['post'])) {
            if ($GLOBALS['post']->post_type == 'dp-wpm-popup') {
                return true;
            }
        }

        return false;
    }

    /**
     * Шорткод попапа
     * @param $_attributes
     * @param $_content
     * @return string
     */
    public function popupShortCode($_attributes, $_content) {
        $replacement = '';

        if (isset($_attributes['id'])) {
            if ($post = get_post(intval($_attributes['id']))) {
                $customFields = get_post_custom($post->ID);
                $replacement = '<a href="#" class="am_popup_' . trim(array_shift($customFields['dp-wpm-popup-fields-class'])) . '">' . (isset($_content) ? $_content : '') . '</a>';
            }
        }

        return $replacement;
    }

    /**
     * Шорткод версии плагина
     * @return string
     */
    public function versionShortCode() {
        return __('Версия плагина «Менеджер Попапов»:' . DP_WPM_VERSION, DP_WPM_TEXT_DOMAIN);
    }
}