<?php global $wpdb;

// Определяем функцию загрузки внешнего изображения
function dp_wpm_sideload_image($post_id, $file, $desc = null){

    if (!function_exists('media_handle_sideload')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }

    // Загружаем файл во временную директорию
    $tmp = download_url($file);

    // Устанавливаем переменные для размещения
    preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $file, $matches);
    $file_array = array();
    $file_array['name'] = basename($matches[0]);
    $file_array['tmp_name'] = $tmp;

    // Удаляем временный файл, при ошибке
    if (is_wp_error($tmp)) {
        @unlink($file_array['tmp_name']);
        $file_array['tmp_name'] = '';
    }

    $id = media_handle_sideload($file_array, $post_id, $desc);

    // Проверяем работу функции
    if (is_wp_error($id)) {
        @unlink($file_array['tmp_name']);
    } else {
        update_post_meta($post_id, '_thumbnail_id', $id);
    }

    // удалим временный файл
    @unlink($file_array['tmp_name']);
}

if (isset($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case "export":

            $query = "SELECT p.ID,
                             p.post_content,
                             p.post_title,
                             p.post_excerpt,
                             p.post_status,
                             p.comment_status,
                             p.ping_status,
                             p.post_name,
                             p.post_type
                          FROM %s p
                         WHERE p.post_type IN ('dp-wpm-popup', 'dp-wpm-templates')";

            $postDataForExport = array();
            $postsData = $wpdb->get_results(sprintf($query, $wpdb->prefix . 'posts'), 'ARRAY_A');

            foreach ($postsData as $postData) {
                $query = "SELECT pm.meta_key,
                                 pm.meta_value,
                                 pm.post_id
                            FROM %s pm
                           WHERE pm.post_id = %d
                             AND pm.meta_key LIKE '%s'";

                $postsMetaData = $wpdb->get_results(sprintf($query, $wpdb->prefix . 'postmeta', $postData['ID'], '%dp-wpm%'), 'ARRAY_A');
                $postData['meta_fields'] = $postsMetaData;
                $postData['img_url'] = get_the_post_thumbnail_url($postData['ID'], 'full');
                $postDataForExport[$postData['post_type']][] = $postData;
            }

            ob_end_clean();
            header('Content-disposition: attachment; filename=wpm-popups.json');
            header('Content-type: application/json');
            echo json_encode($postDataForExport, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
            die();
            break;

        case "import":

            if (!isset($_FILES['file'])) {
                break;
            }

            if ($postsData = json_decode(file_get_contents($_FILES['file']['tmp_name']), true)) {

                // Вставляем записи попапов
                $newPopupIDs = array();
                foreach ($postsData['dp-wpm-popup'] as $popupData) {
                    $query = "INSERT INTO %s (post_content,
                                              post_title,
                                              post_excerpt,
                                              post_status,
                                              comment_status,
                                              ping_status,
                                              post_name,
                                              post_type,
                                              post_date,
                                              post_modified,
                                              post_author)
                              VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', NOW(), NOW(), %d)";

                    $wpdb->query(sprintf(
                        $query,
                        $wpdb->prefix . 'posts',
                        str_replace("'", "\\'", $popupData['post_content']),
                        str_replace("'", "\\'", $popupData['post_title']),
                        $popupData['post_excerpt'],
                        $popupData['post_status'],
                        $popupData['comment_status'],
                        $popupData['ping_status'],
                        $popupData['post_name'],
                        $popupData['post_type'],
                        get_current_user_id()
                    ));

                    $postID = intval($wpdb->insert_id);
                    $newPopupIDs[] = $postID;

                    $query = "UPDATE %s SET guid = '%s', post_name = '%s' WHERE ID = %d";
                    $postURL = get_site_url() . '/?post_type=' . $popupData['post_type'] . '&#038;p=' . $postID;
                    $postName = intval($popupData['post_name']) > 0 ? $postID : $popupData['post_name'];
                    $wpdb->query(sprintf($query,  $wpdb->prefix . 'posts', $postURL, $postName, $postID));

                    foreach ($popupData['meta_fields'] as $meta_field) {
                        $query = "INSERT INTO %s (meta_key, meta_value, post_id)
                              VALUES ('%s', '%s', %d)";

                        $wpdb->query(sprintf(
                            $query,
                            $wpdb->prefix . 'postmeta',
                            $meta_field['meta_key'],
                            str_replace("'", "\\'", $meta_field['meta_value']),
                            $postID
                        ));
                    }

                    if ($popupData['img_url']) {
                        dp_wpm_sideload_image($postID, $popupData['img_url']);
                    }
                }

                // Вставляем шаблоны
                foreach ($postsData['dp-wpm-templates'] as $templateData) {
                    $query = "INSERT INTO %s (post_content,
                                              post_title,
                                              post_excerpt,
                                              post_status,
                                              comment_status,
                                              ping_status,
                                              post_name,
                                              post_type,
                                              post_date,
                                              post_modified,
                                              post_author)
                              VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', NOW(), NOW(), %d)";

                    $wpdb->query(sprintf(
                        $query,
                        $wpdb->prefix . 'posts',
                        str_replace("'", "\\'", $templateData['post_content']),
                        str_replace("'", "\\'", $templateData['post_title']),
                        $templateData['post_excerpt'],
                        $templateData['post_status'],
                        $templateData['comment_status'],
                        $templateData['ping_status'],
                        $templateData['post_name'],
                        $templateData['post_type'],
                        get_current_user_id()
                    ));

                    $postID = intval($wpdb->insert_id);

                    $query = "UPDATE %s SET guid = '%s', post_name = '%s' WHERE ID = %d";
                    $postURL = get_site_url() . '/?post_type=' . $templateData['post_type'] . '&#038;p=' . $postID;
                    $postName = intval($templateData['post_name']) > 0 ? $postID : $templateData['post_name'];
                    $wpdb->query(sprintf($query,  $wpdb->prefix . 'posts', $postURL, $postName, $postID));

                    foreach ($templateData['meta_fields'] as $meta_field) {
                        $query = "INSERT INTO %s (meta_key, meta_value, post_id)
                              VALUES ('%s', '%s', %d)";

                        $wpdb->query(sprintf(
                            $query,
                            $wpdb->prefix . 'postmeta',
                            $meta_field['meta_key'],
                            str_replace("'", "\\'", $meta_field['meta_value']),
                            $postID
                        ));
                    }

                    if ($templateData['img_url']) {
                        dp_wpm_sideload_image($postID, $templateData['img_url']);
                    }

                    foreach ($newPopupIDs as $postPopupID) {
                        $query = "SELECT pm.*
                                    FROM %s pm
                                   WHERE pm.post_id = %d
                                     AND pm.meta_key IN ('dp-wpm-popup-fields-template-1', 'dp-wpm-popup-fields-template-2')";

                        $postsMetaData = $wpdb->get_results(sprintf($query, $wpdb->prefix . 'postmeta', $postPopupID), 'ARRAY_A');
                        foreach ($postsMetaData as $postMetaField) {
                            if ($postMetaField['meta_value'] == $templateData['ID']) {
                                $query = "UPDATE %s SET meta_value = '%d' WHERE meta_id = %d";
                                $wpdb->query(sprintf($query, $wpdb->prefix . 'postmeta', intval($postID), $postMetaField['meta_id']));
                            }
                        }
                    }
                }

                echo '<h4>' . __('Файл успешно импортирован в БД!', DP_WPM_TEXT_DOMAIN) . '</h4>';

            } else {
                echo '<h4>' . __('Нет данных в файле, или он имеет неверный формат!', DP_WPM_TEXT_DOMAIN) . '</h4>';
            }

            break;
    }
}
?>

<h3><?php _e('Экспорт', DP_WPM_TEXT_DOMAIN); ?></h3>
<p><?php _e('Нажмите на', DP_WPM_TEXT_DOMAIN); ?> <a href="?post_type=dp-wpm-popup&page=dp-wpm-options&tab=settings-export-import&action=export&noheader=true"><?php _e('эту ссылку', DP_WPM_TEXT_DOMAIN); ?></a> <?php _e('чтобы получить файл экспорта', DP_WPM_TEXT_DOMAIN); ?></p>
<h3><?php _e('Импорт', DP_WPM_TEXT_DOMAIN); ?></h3>
<p><?php _e('Внимание! Для импорта записей используйте файл формата «<b>.json</b>», который был ранее получен с помощью экспорта!', DP_WPM_TEXT_DOMAIN); ?><br></p>
<form action="?post_type=dp-wpm-popup&page=dp-wpm-options&tab=settings-export-import&action=import" enctype="multipart/form-data" method="post">
    <input type="file" name="file" accept="application/json">
    <button type="submit" class="dp-wpm-form-button"><?php _e('Загрузить', DP_WPM_TEXT_DOMAIN); ?></button>
</form>
