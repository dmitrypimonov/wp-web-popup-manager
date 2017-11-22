jQuery(document).ready(function() {

    /** -- Глобальный объект для работы плагина -- **/
    window.dpWpm = {};
    window.dpWpm.scriptSource = '/';
    window.dpWpm.popupClassPrefix = 'am_popup_';

    /** -- Объект для работы с куками -- **/
    window.dpWpm.toolCookie = {

        // Удаляет cookie с именем name
        deleteCookie: function(name) {
            this.setCookie(name, "", {
                expires: -1
            });
        },

        // Возвращает cookie с именем name, если есть, если нет, то undefined
        getCookie: function(name) {
            var matches = document.cookie.match(new RegExp(
                "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
            ));
            return matches ? decodeURIComponent(matches[1]) : '';
        },

        // Устанавливает cookie с именем name и значением value
        // options - объект с свойствами cookie (expires, path, domain, secure)
        // options = { path:'', domain:'', expires:'', secure:''}
        setCookie: function(name, value, options) {
            options = options || {};

            var expires = options.expires;

            if (typeof expires == "number" && expires) {
                var d = new Date();
                d.setTime(d.getTime() + expires * 1000);
                expires = options.expires = d;
            }
            if (expires && expires.toUTCString) {
                options.expires = expires.toUTCString();
            }

            value = encodeURIComponent(value);

            var updatedCookie = name + "=" + value;

            for (var propName in options) {
                updatedCookie += "; " + propName;
                if (options.hasOwnProperty(propName)) {
                    var propValue = options[propName];
                    if (propValue !== true) {
                        updatedCookie += "=" + propValue;
                    }
                }
            }

            document.cookie = updatedCookie;
        },

        // Показывает куку
        showCookies: function() {
            var arr = document.cookie.split(' '),
                obj = {};

            for (var i in arr){
                if (arr.hasOwnProperty(i)) {
                    arr[i] = (arr[i][arr[i].length - 1] == ';') ? arr[i].slice(0, -1) : arr[i];
                    obj[arr[i].split('=')[0]] = arr[i].split('=')[1];
                }
            }
            return obj;
        }
    };

    /** -- Объект с функциями для формирования параметра aff_content -- **/
    window.dpWpm.ttObj = {

        // Декодируем русские слова из урла
        decodeURISafe: function (uri) {
            var res;
            try {
                res = decodeURI(uri)
            } catch(e) {
                res = uri;
            }
            return res;
        },

        // Возвращает UTM метки элемента
        getUtm: function(elem) {
            if (elem && elem.hasAttribute("data-utm")) {
                return elem.getAttribute("data-utm");
            } else {
                console.log('Атрибут «data-utm» у данного элемента отсутствует, либо пуст!');
            }
        },

        // Возвращает якорь
        getHref: function (elem) {
            if (elem && elem.href && (elem.href.indexOf('#') != -1)) {
                return elem.href.split('#')[1];
            } else {
                console.log('Атрибут «href» у данного элемента либо отсутствует, либо не содержит «#»!');
            }
        },

        // Возвращает название сайта
        getSiteName: function() {
            return window.location.hostname.split('.').shift();
        },

        // Возврашает название страницы
        getPageName: function() {
            // Страница поиска (site.ru/?s=словопоиска)
            if (window.location.search){
                var search = window.location.search.slice(1);

                if (search[0] == 'p') {
                    return 'h1=empty';
                }
                if (search[0] == 's') {
                    return this.decodeURISafe(search.slice(2));
                }
            }
            // Статья (site.ru/категория/статья-url.html)
            // или страница (site.ru/страница-без-html)
            else if (window.location.pathname !== '/') {
                var arrPathName = window.location.pathname.split('/');	// массив
                arrPathName = arrPathName[arrPathName.length - 1];		// строка (последний элемент)
                if (arrPathName.indexOf('html')){						// отрезаем ".html", если есть
                    arrPathName = arrPathName.split('.')[0];
                }
                return arrPathName;
            }
            // Главная
            else {
                return 'home';
            }
        }
    };

    /** -- Объект для работы с попапами -- **/
    window.dpWpm.popupsController = {

        // Ищет попап по классу
        findPopupUsingElementClass: function(elementClass) {
            for (var i = 0; i < window.dpWpm.popupsData.length; i++) {
                if (typeof(window.dpWpm.popupsData[i]['meta_fields']['dp-wpm-popup-fields-class']) != 'undefined') {
                    if (window.dpWpm.popupClassPrefix + window.dpWpm.popupsData[i]['meta_fields']['dp-wpm-popup-fields-class'] == elementClass) {
                        return window.dpWpm.popupsData[i];
                    }
                }
            }

            return false;
        },

        // Ищет попап по ID
        findPopupByID: function(id) {
            for (var i = 0; i < window.dpWpm.popupsData.length; i++) {
                if (window.dpWpm.popupsData[i]['ID'] == id) {
                    return window.dpWpm.popupsData[i];
                }
            }

            return false;
        },

        // Проверяет существование класса, на который реагирует попап
        isPopupActionClassExists: function(elementClass) {
            for (var i = 0; i < window.dpWpm.popupsDataClassesArray.length; i++) {
                if (window.dpWpm.popupsDataClassesArray[i] == '.' + elementClass) {
                    return true;
                }
            }

            return false;
        },

        // Показывает попап при клике по элементу определённого класса
        showPopupUsingElementClass: function(elementClass, deferredLoading, clickedElement) {

            // Отложенный показ попапа?
            // Если отложенный, то мы спокойно грузим попап и показываем без лоадера
            if (typeof(deferredLoading) == 'undefined') {
                deferredLoading = false
            }

            if (this.isPopupActionClassExists(elementClass)) {
                var popupObject = this.findPopupUsingElementClass(elementClass),
                    popupCookieID = 'wpm-' + elementClass + '-' + String(popupObject.ID);

                if (popupObject) {

                    // Ссылка открытия попапа
                    var permalink = popupObject.permalink;
                    if (typeof(popupObject['meta_fields']['dp-wpm-popup-fields-open-link']) !== typeof(undefined)) {
                        if (String(popupObject['meta_fields']['dp-wpm-popup-fields-open-link']).trim().length) {
                            permalink = String(popupObject['meta_fields']['dp-wpm-popup-fields-open-link']).trim();
                        }
                    }

                    // Для UTM
                    var siteName    = window.dpWpm.ttObj.getSiteName(),
                        pageName    = window.dpWpm.ttObj.getPageName(),
                        utm         = typeof(clickedElement) != 'undefined' ? window.dpWpm.ttObj.getUtm(clickedElement) : 'undefined',
                        //href        = typeof(clickedElement) != 'undefined' ? window.dpWpm.ttObj.getHref(clickedElement): 'undefined',
                        totalKey    = siteName + '|' + pageName + '|' + utm + '|' + elementClass,
                        aff_content = 'aff_content=' + totalKey,
                        full_url = encodeURI(permalink + (permalink.indexOf('?') != -1 ? '&' : '?') + popupObject.utm + '&' + aff_content);

                    // Основные объекты
                    var $body = $('body'),
                        $popupFrame = $body.find('.dp-wpm-popup-frame'),
                        $popupFrameLoader = $popupFrame.find('.dp-wpm-popup-frame-inner'),
                        $existingFrame = $popupFrame.find('iframe');

                    // Прячем фрейм и показываем лоадер на время загрузки
                    $existingFrame.hide();
                    $popupFrameLoader.show();

                    if (!deferredLoading) {
                        $popupFrame.fadeIn();
                    }

                    // С фреймом лучше работать из чистого JS
                    var iframe = document.getElementById('dp-wpm-frame');
                    iframe.onload = function() {
                        var frame = document.getElementById('dp-wpm-frame'),
                            frameDocument = frame.contentDocument || frame.contentWindow.document,
                            frameBody = frameDocument.getElementsByTagName('body'),
                            $frameBody = $(frameBody),
                            $frameForm =  $frameBody.find('form'),
                            $frameButtons = $frameBody.find('*[href="' + popupObject['meta_fields']['dp-wpm-popup-fields-target-url'] + '"]');

                        if ($frameForm.length) { // Если есть форма
                            $frameForm.on('submit', function() {
                                window.dpWpm.popupsController.closePopupWindow(true);
                            });
                        } else if ($frameButtons.length) { // Если есть кнопка
                            $frameButtons.on('click', function() {
                                window.dpWpm.popupsController.closePopupWindow(true);
                            });
                        }

                        // Создаем обработчики закрытия и показываем фрейм
                        window.dpWpm.popupsController.createCloseHandlersForPopupBody($frameBody);
                        $existingFrame.show();
                        $popupFrameLoader.hide();

                        if (deferredLoading) {
                            $popupFrame.fadeIn();
                        }
                    };

                    $existingFrame.attr('src', full_url);
                    $popupFrame.attr('data-popup-cookie-id', popupCookieID);
                    $popupFrame.attr('data-popup-id', popupObject.ID);
                }
            }

            return false;
        },

        // Закрывает попап и пишет куку о закрытии
        closePopupWindow: function(forever) {
            if (this.isPopupSinglePage()) {
                $('.dp-wpm-popup').fadeOut();
            } else {
                // Навсегда закрыть? Будет открываться только по клику
                forever = typeof(forever) != 'undefined';

                var $body = $('body'),
                    popupID = $body.find('.dp-wpm-popup-frame').attr('data-popup-id'),
                    popupCookieID = $body.find('.dp-wpm-popup-frame').attr('data-popup-cookie-id'),
                    popupObject = this.findPopupByID(popupID),
                    popupShowRepeatSec = Number(popupObject['meta_fields']['dp-wpm-popup-fields-show-repeat-sec']),
                    currentCookieValue = window.dpWpm.toolCookie.getCookie(popupCookieID);

                // Устанавливаем значение по умолчанию, если не определилось
                popupShowRepeatSec = popupShowRepeatSec == 0 ? 86400 : popupShowRepeatSec;

                // Устанавливаем куку о показе
                if (forever) {
                    window.dpWpm.toolCookie.setCookie(popupCookieID, '0_' + (Date.now() + (60 * 60 * 24 * 365 * 10)), {expires: 60 * 60 * 24 * 365 * 10});
                } else {

                    var currentPopupViews = -1;
                    if (currentCookieValue.length) {
                        var cookieValuesArray = currentCookieValue.split('_');
                        currentPopupViews = Number(cookieValuesArray.shift());
                    }

                    // Если не вечная кука, то обновляем
                    if (currentPopupViews != 0) {
                        if (currentPopupViews == -1) {
                            currentPopupViews = 0;
                        }
                        window.dpWpm.toolCookie.setCookie(popupCookieID, String(currentPopupViews + 1) + '_' + (Date.now() + popupShowRepeatSec), {expires: 60 * 60 * 24 * 365 * 10});
                    }
                }

                setTimeout(function () {
                    $('.dp-wpm-popup-frame').fadeOut();
                }, 1000);
            }
        },

        // Создает инстанс окна с попапом
        getPopupFrameWindowInstance: function() {
            var $popupWindow = $(
                '<div class="dp-wpm-popup-frame">' +
                    '<div class="dp-wpm-popup-frame-inner">' +
                        '<div class="dp-wpm-popup-frame-inner-loader">' +
                            '<div class="dp-wpm-popup-frame-inner-loader-gif"></div>' +
                        '</div>' +
                    '</div>' +
                    '<iframe id="dp-wpm-frame" src="javascript:\'\'"></iframe>' +
                '</div>'
            );

            // В нашем случае надо явно установить все свойства с учетом префиксов,
            // поэтому не используем метод css()
            $popupWindow.attr(
                'style',
                'width: 100%; height: 100%; position: fixed; display: none; z-index: 99999; top: 0; left: 0; overflow: hidden;'
            );
            $popupWindow.find('.dp-wpm-popup-frame-inner').attr(
                'style',
                'width: 100%; height: 100%; position: relative; background: rgba(0, 0, 0, 0.7); display: -ms-flexbox; display: -webkit-flex; display: -moz-flex; display: flex;'
            );
            $popupWindow.find('.dp-wpm-popup-frame-inner-loader').attr(
                'style',
                'margin: auto; width: 44px; height: 44px; position: relative; background-image: url(\'' + window.dpWpm.scriptSource + 'wp-content/plugins/web-popup-manager/assets/img/sprite.png\'); background-position: 0 -108px; opacity: 0.8; cursor: pointer;'
            );
            $popupWindow.find('.dp-wpm-popup-frame-inner-loader-gif').attr(
                'style',
                'width: 44px; height: 44px; background: url(\'' + window.dpWpm.scriptSource + 'wp-content/plugins/web-popup-manager/assets/img/loading.gif\') center center no-repeat;'
            );
            $popupWindow.find('iframe').attr(
                'style',
                'width: 100%; width: calc(100% + 20px); height: 100%; display: block; border: 0; overflow: hidden; padding: 0; margin: 0;'
            );

            return $popupWindow;
        },

        // Создает обработчики закрытия окна попапа
        createCloseHandlersForPopupBody: function($body) {
            $body.on('click', '.dp-wpm-popup, .dp-wpm-close-button', function(event) {
                var $clickedElement = $(event.target);

                if ($clickedElement.hasClass('dp-wpm-close-button') || $clickedElement.hasClass('dp-wpm-popup')) {
                    event.preventDefault();
                    event.stopPropagation();
                    window.dpWpm.popupsController.closePopupWindow();
                }
            });
            $body.on('keyup', '.dp-wpm-popup, .dp-wpm-close-button', function(event) {
                if (event.keyCode == 27) {
                    event.preventDefault();
                    event.stopPropagation();
                    window.dpWpm.popupsController.closePopupWindow();
                }
            });
        },

        // Создает модальное окно
        createPopupWindow: function() {
            var $popupInstance = this.getPopupFrameWindowInstance(),
                $body = $('body');

            // Если окно просмотра попапа, то вещаем обработчики закрытия
            // Иначе добавляем фрейм
            if (this.isPopupSinglePage()) {
                this.createCloseHandlersForPopupBody($body);
            } else {
                if (!$body.find('.dp-wpm-popup-frame').length) {
                    $body.append($popupInstance);
                }
            }
        },

        // Проверяет на страницу показа попапа
        isPopupSinglePage: function() {
            return !!(window.location.href.indexOf('dp-wpm-popup') + 1);
        },

        // Аналог функции in_array в PHP
        inArray : function(what, where) {
            for (var i=0; i < where.length; i++) {
                if (what == where[i]) {
                    return true;
                }
            }
            return false;
        }
    };


    /** -- Инициализируем основные переменные и объекты -- **/
    var $ = jQuery,
        $pageScripts = $('script'),
        urlRegExp = new RegExp('\/\/[A-Z-a-z0-9А-Яа-я\.\-\_]*?\/', 'u');

    /** -- Ищем адрес для посылки запроса -- **/
    $pageScripts.each(function() {
        var currentScriptSource = $(this).attr('src');

        if (typeof(currentScriptSource) != 'undefined') {
            if (currentScriptSource.indexOf('web-popup-manager/assets/js/frontend.js') + 1) {
                var regExResult = urlRegExp.exec(currentScriptSource);
                window.dpWpm.scriptSource = regExResult.shift();
                return false;
            }
        }
    });

    /**
     * Если мы находимся на странице попапа, то просто показываем его
     * Иначе отправляем запрос на получение попапов и в случае успеха вешаем обработчики
     */
    if (window.dpWpm.popupsController.isPopupSinglePage()) {
        window.dpWpm.popupsController.createPopupWindow();
        $('html').attr('style', 'margin: 0 !important; padding: 0 !important;').removeAttr('class');
    } else {

        $.get(window.dpWpm.scriptSource + 'wp-json/web-popup-manager/v1/popups', function(data) {
            try {
                // Тело документа
                var $body = $('body');

                // Запрашиваем данные о попапах один раз
                window.dpWpm.popupsData = data;
                window.dpWpm.popupsDataClassesArray = [];

                // Собираем классы попапов
                for (var i = 0; i < window.dpWpm.popupsData.length; i++) {
                    if (typeof(window.dpWpm.popupsData[i]['meta_fields']['dp-wpm-popup-fields-class']) != 'undefined') {
                        window.dpWpm.popupsDataClassesArray.push('.' + window.dpWpm.popupClassPrefix + window.dpWpm.popupsData[i]['meta_fields']['dp-wpm-popup-fields-class']);
                    }
                }

                // Вещаем обработчики кликов по классам. Эти классы открывают конкретный попап
                $body.on('click', window.dpWpm.popupsDataClassesArray.join(','), function(event) {
                    var clickedElementClasses = $(this).attr('class').split(' ');

                    for (var l = 0; l < clickedElementClasses.length; l++) {
                        if (window.dpWpm.popupsController.isPopupActionClassExists(clickedElementClasses[l])) {
                            window.dpWpm.popupsController.showPopupUsingElementClass(clickedElementClasses[l], false, event.target);
                            break;
                        }
                    }
                });

                // Отложенный показ попапов. Выбираем случайный из доступных
                // Функция показа попапов с учетов термов записей и таксономий
                if (typeof(window.wpmShowPopupIDs) != 'undefined' && typeof(window.wpmShowPopupIDs) == 'string') {
                    if (window.wpmShowPopupIDs.length) {
                        var showPopupArray = window.wpmShowPopupIDs.split('_'),
                            findPopupForShow = false,
                            checkedPopupsArray = [];

                        while (checkedPopupsArray.length < showPopupArray.length && findPopupForShow == false) {
                            var popupForShowID = showPopupArray[Math.floor(Math.random() * showPopupArray.length)],
                                popupObject = window.dpWpm.popupsController.findPopupByID(popupForShowID);
                            window.dpWpm.currentPopupClass = window.dpWpm.popupClassPrefix + popupObject['meta_fields']['dp-wpm-popup-fields-class'];

                            var popupCookieID = 'wpm-' + window.dpWpm.currentPopupClass + '-' + String(popupObject.ID),
                                popupShowSec = Number(popupObject['meta_fields']['dp-wpm-popup-fields-show-sec']),
                                popupMaxShowRepeat = Number(popupObject['meta_fields']['dp-wpm-popup-fields-max-show-repeat']),
                                currentCookieValue = window.dpWpm.toolCookie.getCookie(popupCookieID);

                            // Устанавливаем значения по умолчанию, если они не определились
                            popupShowSec = popupShowSec == 0 ? 30 : popupShowSec;
                            popupMaxShowRepeat = popupMaxShowRepeat == 0 ? 5 : popupMaxShowRepeat;

                            if (currentCookieValue.length) {
                                var cookieValuesArray = currentCookieValue.split('_'),
                                    currentPopupViews = Number(cookieValuesArray.shift()),
                                    nextPopupShowTime = Number(cookieValuesArray.shift());

                                if (currentPopupViews < popupMaxShowRepeat && Date.now() >= nextPopupShowTime) {
                                    findPopupForShow = true;
                                }
                            } else {
                                findPopupForShow = true;
                            }

                            if (findPopupForShow) {
                                setTimeout(function() {
                                    if (!$('.dp-wpm-popup-frame').first().is(':visible')) {
                                        window.dpWpm.popupsController.showPopupUsingElementClass(window.dpWpm.currentPopupClass, true);
                                    }
                                }, ((popupShowSec - 2) * 1000));
                            }

                            if (!window.dpWpm.popupsController.inArray(popupForShowID, checkedPopupsArray)) {
                                checkedPopupsArray.push(popupForShowID);
                            }
                        }
                    }
                }

                // Создаем окно попапа
                window.dpWpm.popupsController.createPopupWindow();

            } catch (e) {
                console.log('Не удалось инициализировать попапы! Ошибка: ' + e.message);
            }
        });

    }
});