$(document).ready(function () {

    $('#button-search').bind('click', function() {
        const lang_pref = window.location.href.indexOf('/ua/') === -1 ? '' : '/ua/';
        url = lang_pref+'index.php?route=product/search';

        var search = $('#content input[name=\'search\']').attr('value');

        if (search) {
            url += '&search=' + encodeURIComponent(search);
        }

        var filter_category_id = $('#content select[name=\'filter_category_id\']').attr('value');

        if (filter_category_id > 0) {
            url += '&filter_category_id=' + encodeURIComponent(filter_category_id);
        }

        var sub_category = $('#content input[name=\'sub_category\']:checked').attr('value');

        if (sub_category) {
            url += '&sub_category=true';
        }

        var filter_description = $('#content input[name=\'description\']:checked').attr('value');

        if (filter_description) {
            url += '&description=true';
        }

        location = url;
    });
    
    if ($('.slider_w').length) {
        var $slider = $('.slider_w');
        var dots = ($slider.data('dots') !== undefined) ? !!Number($slider.data('dots')) : true;
        var autoplay = ($slider.data('autoplay') !== undefined) ? !!Number($slider.data('autoplay')) : true;
        var autoplaySpeed = ($slider.data('autoplay-speed') !== undefined) ? Number($slider.data('autoplay-speed')) : 4000;

        $slider.slick({
            dots: dots,
            infinite: true,
            speed: 500,
            autoplay: autoplay,
            autoplaySpeed: autoplaySpeed,
        });
    }
    if ($('.carusel_prod').length) {
        $('.carusel_prod').slick({
            infinite: true,
            touchMove: false,
            slidesToShow: 5,
            slidesToScroll: 5,
            centerMode: false,
            prevArrow: '<div class="prev-next-w"><div type="button" class="slick-prev"></div></div>',
            nextArrow: '<div class="prev-next-w"><div type="button" class="slick-next"></div></div>',
            touchMove: false,
            dots: true,
            touchThreshold: 12,
            autoplay: true,
            autoplaySpeed: 4000,
            responsive: [
                {
                    breakpoint: 991,
                    settings: {
                        slidesToShow: 3,
                        slidesToScroll: 3,
                        arrows: false,
                        dots: true
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 1,
                        slidesToScroll: 1,
                        arrows: false,
                        dots: true
                    }
                }


            ]
        });
    }

    if ($('.brand_carousel').length) {
        $('.brand_carousel').slick({
            infinite: true,
            touchMove: false,
            slidesToShow: 4,
            slidesToScroll: 4,
            centerMode: false,
            prevArrow: '<div class="prev-next-w"><div type="button" class="slick-prev"></div></div>',
            nextArrow: '<div class="prev-next-w"><div type="button" class="slick-next"></div></div>',
            touchMove: false,
            dots: true,
            touchThreshold: 12,
            responsive: [
                {
                    breakpoint: 991,
                    settings: {
                        slidesToShow: 3,
                        slidesToScroll: 3,
                        arrows: false,
                        dots: true
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 2,
                        slidesToScroll: 2,
                        arrows: false,
                        dots: true
                    }
                }


            ]
        });
    }
    

    $(document).on('click', '.select_phone_w', function (e) {
        var state = $(this).data('state');
        var thisis = $(this);
        switch (state) {
            case 1:
            case undefined:
                $(this).closest(".phone_h").find(".phone_pop").show();
                $(this).addClass("click");
                $(this).data('state', 2);
                break;
            case 2:
                $(this).closest(".phone_h").find(".phone_pop").hide();
                $(this).removeClass("click");
                $(this).data('state', 1);
                break;
        }
        if (thisis.closest(".phone_h").find(".phone_pop").is(':visible')) {
            $(document).click(function (event) {
                if (!$(event.target).closest(".select_phone_w").length) {
                    thisis.closest(".phone_h").find(".phone_pop").hide();
                    thisis.removeClass("click");
                    thisis.data('state', 1);
                }
            });
        }
    });
    $('.phone_pop_a').click(function (e) {
        var aclass = $.trim($(this).attr('class'));
        var phone = $.trim($(this).attr('data-phone-num'));
        $('.phone_pop_a').removeClass('active');
        $(this).addClass('active');
        $(this).closest('.phone_h').find('.phone_self_dash').html(phone);
        $.cookie('aclass', aclass, {expires: 365, path: '/'});
        $.cookie('phone', phone, {expires: 365, path: '/'});
    });
    if ($.cookie('phone')) {
        $('.phone_pop li .phone_pop_a').each(function () {
            var eqphone = $.cookie('phone');
            var data_p_n = $(this).attr('data-phone-num');
            if (eqphone == data_p_n) {
                $('.phone_pop_a').removeClass('active');
                $(this).addClass('active');
            }
        });
    } else {
        $('.first_phone_pop_a').addClass('active');
    }
    // if (!(/Android|iPhone|iPad|iPod|BlackBerry|Windows Phone/i).test(navigator.userAgent || navigator.vendor || window.opera)) {
    //     $('.para_ima').css('opacity', '0');
    //     var s = skrollr.init({
    //         forceHeight: false,
    //         smoothScrolling: false,
    //         easing: {
    //             wtf: Math.random, inverted: function (p) {
    //                 return 1 - p;
    //             }
    //         },
    //         duration: 0
    //     });
    //     $(window).load(function () {
    //         s.refresh($("div"));
    //         $('.para_ima').css('opacity', '1');
    //     });
    // }
    $(document).on('click', '.gal', function (e) {
        e.preventDefault();
        $('a.gal').each(function () {
            var gal = $(this).attr('rel');
            $('a[rel="' + gal + '"]').magnificPopup({
                type: 'image',
                mainClass: 'mfp-with-zoom',
                tLoading: '',
                zoom: {
                    enabled: true, duration: 300, easing: 'ease-in-out', opener: function (openerElement) {
                        return openerElement.is('img') ? openerElement : openerElement.find('img');
                    }
                },
                callbacks: {
                    elementParse: function (item) {
                    }, beforeOpen: function () {
                        this.wrap.addClass(this.st.el.attr('data-effect'));
                    }, removalDelay: 300, open: function () {
                        $.magnificPopup.instance.next = function () {
                            var self = this;
                            self.wrap.removeClass('opa mfp-s-ready zoomIn');
                            self.wrap.find('.mfp-content').removeClass('animated fadeInLeft').addClass('animated fadeOutLeft');
                            setTimeout(function () {
                                $.magnificPopup.proto.next.call(self);
                            }, 200);
                            setTimeout(function () {
                                self.wrap.find('.mfp-content').removeClass('animated fadeOutLeft').addClass('animated fadeInRight');
                            }, 200);
                        }
                        $.magnificPopup.instance.prev = function () {
                            var self = this;
                            self.wrap.removeClass('opa mfp-s-ready zoomIn');
                            self.wrap.find('.mfp-content').removeClass('animated fadeInRight').addClass('animated fadeOutRight');
                            setTimeout(function () {
                                $.magnificPopup.proto.prev.call(self);
                            }, 200);
                            setTimeout(function () {
                                self.wrap.find('.mfp-content').removeClass('animated fadeOutRight').addClass('animated fadeInLeft');
                            }, 200);
                        }
                    }, beforeClose: function () {
                        var self = this;
                        self.wrap.find('.mfp-content').addClass('opa animated zoomOut');
                    }
                },
                gallery: {
                    enabled: true,
                    preload: [0, 2],
                    navigateByImgClick: true,
                    arrowMarkup: '<button title="%title%" type="button" class="mfp-arrow mfp-arrow-%dir%"></button>',
                    tPrev: 'Пред (Стрелочка влево)',
                    tNext: 'След (Стрелочка вправо)',
                    tCounter: '<span class="mfp-counter">%curr% из %total%</span>'
                }
            });
        });
        $(this).trigger('click');
    });
    if ($('.p_dop_photo_h').length) {
        $(document).on('hover', '.p_dop_photo_h', function () {
            var data_href = $(this).attr('data-href');
            var product_w = $(this).closest('.product_w');
            var prod_image_img = product_w.find('.prod_image img');
            $('.p_dop_photo_h').removeClass('active');
            $(this).addClass('active');
            prod_image_img.attr('src', data_href);
        });
    }
    $(".clear_filter").click(function () {
        $("#filterpro .checked").removeClass("checked");
        $("#filterpro .disabled").removeClass("disabled");
    });
    $(".option_box td input").each(function () {
        if (!$(this).closest('tr').find("div").length) {
            $(this).wrap('<div class="td_inner"></div>');
        }
    });
    $('#filterpro .option_box input').on('change', function () {
        var $td_inner = $(this).closest('.td_inner');
        if ($td_inner.exists()) {
            if ($(this).is(":checked")) {
                $td_inner.addClass("checked");
                $td_inner.closest('.imgth_w').addClass("checked");
            } else {
                $td_inner.removeClass("checked");
                $td_inner.closest('.imgth_w').removeClass("checked");
            }
        }
    })
    $(document).on('click', '.gost', function (e) {
        e.preventDefault();
        const lang_pref = window.location.href.indexOf('/ua/') === -1 ? '' : '/ua/';
        $.magnificPopup.close();
        var ocLang = (window.ocLanguage || (window.location.pathname.indexOf('/ua') === 0 ? 'uk-ua' : 'ru-ru'));
        $('#header #cart').load('/index.php?route=common/cart.info&language=' + encodeURIComponent(ocLang) + ' #cart > *');
    });

    // Info/help popups (OC2 legacy): links like <a class="title_href" href="#some_popup">...
    // and <a class="btn_pop" href="#pop">...
    $(document).on('click', 'a.btn_pop, a.title_href', function(e) {
        var href = String($(this).attr('href') || '');

        if (href.charAt(0) !== '#') {
            return;
        }

        var $target = $(href);

        if (!$target.length) {
            return;
        }

        e.preventDefault();

        $.magnificPopup.open({
            items: { src: href },
            type: 'inline',
            fixedContentPos: true,
            removalDelay: 300,
            mainClass: String($(this).attr('data-effect') || 'mfp-zoom-in'),
            callbacks: {
                open: function() {
                    $('body').addClass('popup_open');
                },
                close: function() {
                    $('body').removeClass('popup_open');
                }
            }
        });
    });
    if ($('#simplecheckout_cart').length) {
        cuponPolt();
        bindSimplecheckoutFallback();
        initNovaPoshtaAutocomplete();
        $(document).on('click', '.have_cupon', function (e) {
            e.preventDefault();
            if ($('.cupon_w').hasClass('none')) {
                $('.cupon_w').removeClass('none');
            } else {
                $('.cupon_w').addClass('none');
            }
        });
        $('.checkout-heading').each(function () {
            if (!$(this).find('span').length) {
                $(this).wrapInner('<span></span>')
            }
        })
    }


    $(function () {
        $('.main_nav').slicknav({
            label: "Каталог",
            prependTo: ".main_nav_w",
            allowParentLinks: true,
            'closedSymbol': '',
            'openedSymbol': '',
            init: function () {
                $(".slicknav_parent .active").parent().addClass("active");
                $(".slicknav_menu ul").removeClass("list_menu");
                $(".slicknav_menu ul").removeClass("children_w");
                $(".slicknav_menu ul li").removeClass("col-md-4");
                $(".slicknav_menu .product_menu_polt").closest('.slicknav_parent').remove();
                if (!$('.mob_o_nas').length) {
                    if (window.location.href.indexOf('/ua/') === -1) {
                    $('<li class="mob_o_nas slicknav_collapsed slicknav_parent"><a class="slicknav_item slicknav_row" href="#">Помощь<span class="slicknav_arrow"></span></a></li>').appendTo('ul.slicknav_nav');
                    } else {
                        $('<li class="mob_o_nas slicknav_collapsed slicknav_parent"><a class="slicknav_item slicknav_row" href="#">Допомога<span class="slicknav_arrow"></span></a></li>').appendTo('ul.slicknav_nav');

                    }
                    $('.top_nav ul').clone().addClass('slicknav_hidden').attr('aria-hidden', 'true').attr('role', 'menu').css('display', 'none').appendTo('.mob_o_nas');
                }
            }
        });
    });
    $('#filterpro input').on("change", (function () {
        $('html, body').animate({scrollTop: $('.product-filter').offset().top}, 'slow');
    }));
    $(document).on('click', '.imgth_w', (function () {
        $('html, body').animate({scrollTop: $('.product-filter').offset().top}, 'slow');
    }));
    $('.show_filter').magnificPopup({
        removalDelay: 500,
        tClose: 'Закрыть (Esc)',
        tLoading: 'Загрузка...',
        fixedContentPos: true,
        callbacks: {
            beforeOpen: function () {
                this.st.mainClass = this.st.el.attr('data-effect');
                startWindowScroll = $(window).scrollTop();
            }, open: function () {
                $('body').addClass('popup_open');
                $('.option_name ').each(function () {
                    if ($(this).hasClass('hided')) {
                        $(this).addClass('hided_pop');
                    }
                    $(this).closest('.option_box').find('.collapsible').hide();
                    $(this).removeClass('hided');
                });
            }, close: function () {
                $('body').removeClass('popup_open');
                $(window).scrollTop(startWindowScroll);
                $('.option_name ').each(function () {
                    $(this).closest('.option_box').find('.collapsible').show();
                    if ($(this).hasClass('hided_pop')) {
                        $(this).addClass('hided');
                        $(this).closest('.option_box').find('.collapsible').hide();
                    }
                    $(this).removeClass('hided_pop');
                });
            }
        },
        midClick: true
    });
    $(document).on('click', '.close_filter', function (e) {
        e.preventDefault();
        $('html, body').animate({scrollTop: $('.product-filter').offset().top}, 'slow');
        $.magnificPopup.close();
    });
    function mobProdTitle() {
        if (window.matchMedia('(max-width: 767px)').matches) {
            if ($('.tovar_middle h1').length && !$('.mob_title').length) {
                $('.tovar_middle h1').clone().addClass('mob_title').insertBefore('.thumbnails');
            }
        } else {
            $('.mob_title').remove();
        }
    }

    function mobSpanImg() {
        if (window.matchMedia('(max-width: 767px)').matches) {
            if (!$('.mob_span_img').length) {
                $('<span class="mob_span_img"></span>').appendTo('.big_image_wrap');
            }
        } else {
            $('.mob_span_img').remove();
        }
    }

    mobProdTitle();
    mobSpanImg();
    $(window).resize(function () {
        mobProdTitle();
        mobSpanImg();
    });
    stcart();
    $(window).resize(function () {
        stcart();
    });
    $('.call_c').tooltipster({
        interactiveTolerance: 50,
        delay: 100,
        interactive: true,
        contentCloning: true,
        animation: 'fall',
        trigger: 'click'
    });
    $('.doopl_href').tooltipster({
        interactiveTolerance: 50,
        delay: 100,
        interactive: true,
        contentCloning: true,
        animation: 'grow',
        trigger: 'click'
    });
    $(window).scroll(function () {
        if ($(this).scrollTop() > 100) {
            $('.scrollup').fadeIn();
        } else {
            $('.scrollup').fadeOut();
        }
    });
    $('.scrollup').click(function () {
        $("html, body").animate({scrollTop: 0}, 600);
        return false;
    });
    $(document).on('click', 'a.all_opis, .tabl_r, .anim_href, .product_menu a', function (e) {
        e.preventDefault();
        var target = $($(this.hash).get('selector'));
        target.trigger('click');
        if (target.length) {
            $('html,body').animate({scrollTop: target.offset().top}, 800);
            return false;
        }
    });
    $(document).on('click', '.dost_oplata_href', function () {
        var target = $($(this.hash).get('selector'));
        if ($(target).hasClass('dost_oplata_active')) {
            $(target).removeClass('dost_oplata_active');
        } else {
            $(target).addClass('dost_oplata_active');
        }
    })
    freeDelivery();
    freeDeliveryCart();
});
function stcart() {
    if ($(".simplecheckout-left-column").length && $('#simplecheckout_button_confirm').length) {
        if (window.matchMedia('(max-width: 1100px)').matches || $(window).innerHeight() <= $('#menu-bokovoe-menyu').innerHeight() + 60) {
            $('.simplecheckout-left-column').unstick();
        } else {
            $('.simplecheckout-left-column').unstick();
            $(".simplecheckout-left-column").sticky({
                topSpacing: 0,
                className: 'h-animated',
                bottomSpacing: $(document).innerHeight() - $('.simplecheckout-button-block').offset().top + 30,
                wrapperClassName: 'site_header_wr'
            });
            $(".simplecheckout-left-column").sticky('update');
        }
    }
}
function cuponPolt() {
    if (!$('.cupon_w').length && !$('.cupon_btn').length) {
        $('input[name="coupon"]').wrap('<span class="cupon_w none"></span>');
        if (window.location.href.indexOf('/ua/') === -1) {
            $('<a class="btn cupon_btn" onclick="reloadAll()">Применить купон</a>').insertAfter('input[name="coupon"]');
            $('<a href="#" class="have_cupon">Есть купон?</a>').insertBefore('.cupon_w');
        } else {
            $('<a class="btn cupon_btn" onclick="reloadAll()">Застосувати купон</a>').insertAfter('input[name="coupon"]');
            $('<a href="#" class="have_cupon">Маєте купон?</a>').insertBefore('.cupon_w');
        }
        $('<span class="simplecheckout-cart-total-remove"></span>').insertAfter('.inputs');
        if ($('.inputs:contains("Купон:")')) {
            $('.inputs:contains("Купон:")').each(function () {
                $(this).html($(this).html().split("Купон:").join(""));
            });
        }
    }
}

var simplecheckoutFallbackBound = false;

function bindSimplecheckoutFallback() {
    if (simplecheckoutFallbackBound) {
        return;
    }

    simplecheckoutFallbackBound = true;

    $(document).on('click', '#simplecheckout_form_0 [data-onclick]', function (e) {
        if (window.__simplecheckoutLiteActive) {
            return;
        }

        var action = String($(this).attr('data-onclick') || '');
        var key = String($(this).attr('data-product-key') || '');
        var $qty;
        var value;

        if (!action) {
            return;
        }

        if (action === 'increaseProductQuantity' && key) {
            e.preventDefault();
            $qty = $('#simplecheckout_form_0 input[name="quantity[' + key + ']"]');

            if ($qty.length) {
                value = parseInt($qty.val(), 10) || 1;
                $qty.val(String(value + 1));
            }

            if (typeof window.reloadAll === 'function') {
                window.reloadAll();
            }

            return;
        }

        if (action === 'decreaseProductQuantity' && key) {
            e.preventDefault();
            $qty = $('#simplecheckout_form_0 input[name="quantity[' + key + ']"]');

            if ($qty.length) {
                value = parseInt($qty.val(), 10) || 1;
                value = value > 1 ? value - 1 : 1;
                $qty.val(String(value));
            }

            if (typeof window.reloadAll === 'function') {
                window.reloadAll();
            }

            return;
        }

        if (action === 'removeProduct' && key) {
            e.preventDefault();
            $('#simplecheckout_remove').val(key);

            if (typeof window.reloadAll === 'function') {
                window.reloadAll();
            }

            return;
        }

        if (action === 'changeProductQuantity' || action === 'reloadAll') {
            e.preventDefault();

            if (typeof window.reloadAll === 'function') {
                window.reloadAll();
            }

            return;
        }

        if (action === 'createOrder') {
            e.preventDefault();

            if (typeof window.reloadAll === 'function') {
                window.reloadAll({create_order: 1});
            }
        }
    });

    $(document).on('change', '#simplecheckout_form_0 [data-onchange]', function () {
        if (window.__simplecheckoutLiteActive) {
            return;
        }

        var action = String($(this).attr('data-onchange') || '');

        if (action === 'changeProductQuantity' || action === 'reloadAll') {
            if (typeof window.reloadAll === 'function') {
                window.reloadAll();
            }
        }
    });
}

(function ($) {
    if (!$.fn.npAutocompleteAddress) {
        var methods = {
            init: function (options) {
                return this.each(function () {
                    var $input = $(this);

                    if ($input.data('npAutocompleteAddress')) {
                        methods.destroy.call($input);
                    }

                    var settings = $.extend({}, options || {});
                    var $list = $('<ul class="dropdown-address"></ul>').hide();
                    var timer = null;
                    var items = {};

                    function hide() {
                        $list.hide();
                    }

                    function show() {
                        var offset = $input.offset();

                        $list.css({
                            top: offset.top + $input.outerHeight(),
                            left: offset.left,
                            width: $input.outerWidth()
                        }).show();
                    }

                    function render(data) {
                        items = {};

                        if (!Array.isArray(data) || !data.length) {
                            hide();
                            $list.html('');
                            return;
                        }

                        var html = '';

                        $.each(data, function (_, item) {
                            var value = String(item.value || item.description || '');

                            if (!value) {
                                return;
                            }

                            var key = value + '|' + String(item.ref || '');
                            items[key] = item;

                            html += '<li data-key="' + $('<div/>').text(key).html() + '"><a href="#">' + $('<div/>').text(String(item.label || value)).html() + '</a></li>';
                        });

                        $list.html(html);

                        if (html && $input.is(':focus')) {
                            show();
                        } else {
                            hide();
                        }
                    }

                    function request(search) {
                        if (typeof settings.source !== 'function') {
                            return;
                        }

                        clearTimeout(timer);

                        timer = setTimeout(function () {
                            settings.source(search, function (result) {
                                render(result || []);
                            });
                        }, 180);
                    }

                    $input.attr('autocomplete', 'new-password');

                    $input.on('focus.npAutocompleteAddress', function () {
                        request($input.val());
                    });

                    $input.on('blur.npAutocompleteAddress', function () {
                        setTimeout(hide, 180);
                    });

                    $input.on('keydown.npAutocompleteAddress', function (event) {
                        if (event.keyCode === 27) {
                            hide();
                        }
                    });

                    $input.on('input.npAutocompleteAddress', function () {
                        request($input.val());
                    });

                    $list.on('mousedown.npAutocompleteAddress touchstart.npAutocompleteAddress', 'a', function (event) {
                        event.preventDefault();
                        event.stopPropagation();

                        var key = String($(this).closest('li').attr('data-key') || '');
                        var item = items[key];

                        if (!item) {
                            return;
                        }

                        if (typeof settings.select === 'function') {
                            settings.select(item, $input);
                        }

                        hide();
                    });

                    // Selection runs on mousedown above; the click that follows
                    // would otherwise navigate (<a href="#"> + <base href> = home).
                    // Confirmed via HAR: GET https://manline.com.ua/# initiator "other".
                    $list.on('click.npAutocompleteAddress', 'a', function (event) {
                        event.preventDefault();
                    });

                    $('body').append($list);
                    $input.data('npAutocompleteAddress', true);
                    $input.data('npAutocompleteAddressList', $list);

                    if ($input.is(':focus')) {
                        request($input.val());
                    }
                });
            },
            destroy: function () {
                return this.each(function () {
                    var $input = $(this);

                    if (!$input.data('npAutocompleteAddress')) {
                        return;
                    }

                    $input.removeData('npAutocompleteAddress');
                    $input.off('.npAutocompleteAddress');
                    $input.data('npAutocompleteAddressList').remove();
                    $input.removeData('npAutocompleteAddressList');
                });
            }
        };

        $.fn.npAutocompleteAddress = function (method) {
            if (methods[method]) {
                return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
            }

            if (typeof method === 'object' || !method) {
                return methods.init.apply(this, arguments);
            }

            return this;
        };
    }
}(window.jQuery));

var simpleNpAutocompleteBound = false;

function selectedShippingMethodSimplecheckout() {
    var checked = $('#simplecheckout_shipping input[name="shipping_method"]:checked').val();

    if (checked) {
        return String(checked);
    }

    var selected = $('#simplecheckout_shipping select[name="shipping_method"]').val();

    if (selected) {
        return String(selected);
    }

    var firstNp = $('#simplecheckout_shipping input[name="shipping_method"][value^="novaposhta."]').first().val();

    return firstNp ? String(firstNp) : '';
}

function isNovaPoshtaMethodSimplecheckout(method) {
    return String(method || '').indexOf('novaposhta.') === 0;
}

function isNovaPoshtaWarehouseMethodSimplecheckout(method) {
    return method === 'novaposhta.branch' || method === 'novaposhta.locker';
}

function clearNovaPoshtaRefsSimplecheckout(clearCity) {
    if (clearCity) {
        $('input[name="shipping_city"]').val('');
        $('#shipping_address_city_ref').val('');
    }

    $('input[name="shipping_address_1"]').val('');
    $('#shipping_address_address_ref').val('');
}

function updateNovaPoshtaAreaFieldsSimplecheckout() {
    var $selected = $('#shipping_address_area_ref option:selected');

    $('#shipping_address_area').val(String($selected.attr('data-area') || $selected.text() || ''));
    $('#shipping_address_zone_id').val(String($selected.attr('data-zone-id') || '0'));
}

function initNovaPoshtaAutocomplete() {
    var $form = $('#simplecheckout_form_0');

    if (!$form.length) {
        return;
    }

    var endpoint = String($form.attr('data-np-url') || '');

    if (!endpoint) {
        return;
    }

    $('#simplecheckout_shipping_address .simplecheckout-block-content').css('overflow', 'visible');

    if (simpleNpAutocompleteBound) {
        return;
    }

    simpleNpAutocompleteBound = true;

    updateNovaPoshtaAreaFieldsSimplecheckout();

    $(document).on('change.simpleNp', '#simplecheckout_form_0 [name="shipping_method"], #simplecheckout_form_0 [name="shipping_country_id"], #simplecheckout_form_0 [name="shipping_area_ref"]', function (event) {
        var method = selectedShippingMethodSimplecheckout();

        if (!isNovaPoshtaMethodSimplecheckout(method)) {
            clearNovaPoshtaRefsSimplecheckout(true);
            $('input[name="shipping_city"], input[name="shipping_address_1"]').npAutocompleteAddress('destroy');
            return;
        }

        if (event.target.name === 'shipping_area_ref') {
            updateNovaPoshtaAreaFieldsSimplecheckout();
        }

        if (event.target.name === 'shipping_country_id' || event.target.name === 'shipping_area_ref') {
            $('input[name="shipping_city"]').val('');
            $('input[name="shipping_address_1"]').val('');
            clearNovaPoshtaRefsSimplecheckout(true);
            $('input[name="shipping_city"], input[name="shipping_address_1"]').npAutocompleteAddress('destroy');
            return;
        }

        if (!isNovaPoshtaWarehouseMethodSimplecheckout(method)) {
            $('#shipping_address_address_ref').val('');
            $('input[name="shipping_address_1"]').npAutocompleteAddress('destroy');
        } else {
            $('input[name="shipping_address_1"]').val('');
            $('#shipping_address_address_ref').val('');
        }
    });

    $(document).on('change.simpleNp', '#simplecheckout_form_0 [name="shipping_city"]', function () {
        if (!isNovaPoshtaMethodSimplecheckout(selectedShippingMethodSimplecheckout())) {
            return;
        }

        if (!$(this).val()) {
            $('#shipping_address_city_ref').val('');
        }

        $('input[name="shipping_address_1"]').val('');
        $('#shipping_address_address_ref').val('');
    });

    function updateNovaPoshtaPrice() {
        var method = selectedShippingMethodSimplecheckout();

        if (!isNovaPoshtaMethodSimplecheckout(method)) {
            return;
        }

        var cityRef = String($('#shipping_address_city_ref').val() || '');
        var cityName = String($('input[name="shipping_city"]').val() || '');
        var areaRef = String($('select[name="shipping_area_ref"]').val() || '');

        if (!cityRef && !cityName) {
            return;
        }

        $.ajax({
            url: endpoint,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'getPrice',
                shipping_method: method,
                area_ref: areaRef,
                city_ref: cityRef,
                city: cityName
            },
            global: false
        }).done(function (json) {
            if (json && json.error) {
                console.warn('Nova Poshta price error:', json.error);
                return;
            }

            if (!json || !json.text) {
                return;
            }

            var $checked = $('#simplecheckout_shipping input[name="shipping_method"]:checked');
            var $row = $checked.closest('tr');

            if (!$row.length) {
                return;
            }

            var $cell = $row.find('td.quote label').first();

            if (!$cell.length) {
                $cell = $row.find('td.quote').first();
            }

            if (!$cell.length) {
                return;
            }

            $row.find('.np-price').remove();
            $('<span class="np-price" style="margin-left:8px; white-space:nowrap;"></span>')
                .text(String(json.text))
                .appendTo($cell);
        });
    }

    $('body').on('focus.simpleNp', '#simplecheckout_form_0 input[name="shipping_city"], #simplecheckout_form_0 input[name="shipping_address_1"]', function () {
        var method = selectedShippingMethodSimplecheckout();
        var isCityInput = this.name === 'shipping_city';
        var needsWarehouse = isNovaPoshtaWarehouseMethodSimplecheckout(method);
        var areaRef = String($('select[name="shipping_area_ref"]').val() || '');

        if ((!isCityInput && !needsWarehouse) || (!isNovaPoshtaMethodSimplecheckout(method) && (!isCityInput || !areaRef))) {
            $(this).npAutocompleteAddress('destroy');
            return;
        }

        var $input = $(this);
        var cityRef = String($('#shipping_address_city_ref').val() || '');
        var cityName = String($('input[name="shipping_city"]').val() || '');
        var action = isCityInput ? 'getCities' : 'getWarehouses';

        $input.npAutocompleteAddress({
            source: function (request, response) {
                var payload = {
                    action: action,
                    search: String(request || ''),
                    shipping_method: method,
                    area_ref: areaRef,
                    city_ref: cityRef,
                    city: cityName
                };

                $.ajax({
                    url: endpoint,
                    type: 'POST',
                    dataType: 'json',
                    data: payload,
                    global: false
                }).done(function (json) {
                    response(Array.isArray(json) ? json : []);
                }).fail(function () {
                    response([]);
                });
            },
            select: function (item, $target) {
                var value = String(item.value || item.description || '');

                $target.val(value);

                if ($target.attr('name') === 'shipping_city') {
                    $('#shipping_address_city_ref').val(String(item.ref || ''));
                    $('input[name="shipping_address_1"]').val('');
                    $('#shipping_address_address_ref').val('');
                } else {
                    $('#shipping_address_address_ref').val(String(item.ref || ''));
                }

                $target.trigger('change');

                updateNovaPoshtaPrice();
            }
        });
    });

    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(null, args);
            }, delay);
        };
    }

    var debouncedPriceUpdate = debounce(updateNovaPoshtaPrice, 300);

    $(document).on('change.simpleNpPrice', '#simplecheckout_form_0 [name="shipping_method"]', function () {
        updateNovaPoshtaPrice();
    });

    // If user types city manually (without selecting from autocomplete), still try to resolve and fetch price.
    $(document).on('input.simpleNpPrice', '#simplecheckout_form_0 [name="shipping_city"]', function () {
        debouncedPriceUpdate();
    });

    $(document).on('input.simpleNpPrice', '#simplecheckout_form_0 [name="shipping_address_1"]', function () {
        debouncedPriceUpdate();
    });
}

function moneyToInt(value) {
    var normalized = String(value || '').replace(/[^\d]/g, '');

    if (!normalized) {
        return 0;
    }

    return parseInt(normalized, 10) || 0;
}

function checkoutSubtotal() {
    var sub_total = moneyToInt($('#total_sub_total .simplecheckout-cart-total-value').first().text());

    if (sub_total > 0) {
        return sub_total;
    }

    var first_row_total = moneyToInt($('#simplecheckout_cart .simplecheckout-cart-total .simplecheckout-cart-total-value').first().text());

    if (first_row_total > 0) {
        return first_row_total;
    }

    var cart_total = moneyToInt($('#simplecheckout_cart_total').first().text());

    if (cart_total > 0) {
        return cart_total;
    }

    return moneyToInt($('#total_total .simplecheckout-cart-total-value').first().text());
}

function checkoutDozakazBanner() {
    var in_checkout = $('#simplecheckout_form_0 .simplecheckout-left-column .dozakaz').first();

    if (in_checkout.length) {
        return in_checkout;
    }

    return $('.dozakaz').first();
}

function freeDelivery() {
    var banner = checkoutDozakazBanner();

    if (!banner.length) {
        return;
    }

    var free_deliv = moneyToInt(banner.attr('data-free-deliv')) || 1500;
    var is_ua = window.location.href.indexOf('/ua/') !== -1;
    var banner_rd = banner.find('.rd').first();
    var banner_word = banner.find('.dozak_word').first();
    var banner_dozakaz_in = banner.find('.dozakaz_in').first();
    var banner_free = banner.find('.free').first();

    function renderBanner(total_cart) {
        var dozakaz = (free_deliv > total_cart) ? free_deliv - total_cart : 0;

        if (total_cart > 0) {
            if (total_cart < free_deliv) {
                if (dozakaz > 0) {
                    banner_rd.text(dozakaz + ' грн');
                    if (!is_ua) {
                        banner_word.text('Дозакажите');
                    } else {
                        banner_word.text('Дозамовте');
                    }
                    banner.show();
                    banner_dozakaz_in.show();
                    banner_free.hide();
                } else {
                    if (!is_ua) {
                        banner_word.text('Закажите');
                    } else {
                        banner_word.text('Замовте');
                    }
                    banner_rd.text(free_deliv + ' грн');
                    banner.show();
                    banner_dozakaz_in.show();
                    banner_free.hide();
                }
            } else {
                banner.show();
                banner_dozakaz_in.hide();
                banner_free.show();
            }
        } else {
            if (!is_ua) {
                banner_word.text('Закажите');
            } else {
                banner_word.text('Замовте');
            }
            banner_rd.text(free_deliv + ' грн');
            banner.show();
            banner_dozakaz_in.show();
            banner_free.hide();
        }
    }

    if ($('#simplecheckout_cart').length) {
        renderBanner(checkoutSubtotal());
        return;
    }

    const lang_pref = window.location.href.indexOf('/ua/') === -1 ? '' : '/ua';
    var total_checkout = checkoutSubtotal();

    if (total_checkout > 0) {
        renderBanner(total_checkout);
        return;
    }

    var ocLang = (window.ocLanguage || (window.location.pathname.indexOf('/ua') === 0 ? 'uk-ua' : 'ru-ru'));
    $.get('/index.php?route=common/cart.info&language=' + encodeURIComponent(ocLang), function (data) {
        var total_cart = moneyToInt($(data).find('.total_money').first().text());
        renderBanner(total_cart);
    }, 'html');
}
function freeDeliveryCart() {
    if (!$('#total_total').length) {
        return;
    }

    var selected_shipping = $('#simplecheckout_shipping input[type="radio"]:checked');
    var total_shipping_value = $('#total_shipping .simplecheckout-cart-total-value');

    if (!selected_shipping.length || !total_shipping_value.length) {
        return;
    }

    var selected_row = selected_shipping.closest('tr');
    var selected_quote = selected_row.find('td.quote label').first();

    if (selected_quote.length) {
        total_shipping_value.html(selected_quote.text());
    }
}
function npCost() {
    // $('.title label:contains("Новой Почты")').closest('tr').find('.quote label').text('50 грн');
}
