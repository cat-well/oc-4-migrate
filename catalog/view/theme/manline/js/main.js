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
        $('.slider_w').slick({
            dots: true,
            infinite: true,
            speed: 500,
            autoplay: true,
            autoplaySpeed: 4000,
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
        $('#header #cart').load(lang_pref+'index.php?route=module/cart #cart > *');
    });
    if ($('#simplecheckout_cart').length) {
        cuponPolt();
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
function freeDelivery() {
    const lang_pref = window.location.href.indexOf('/ua/') === -1 ? '' : '/ua';
    $.get(lang_pref+"/index.php?route=module/cart", function (data) {
        var total_cart = parseInt($(data).find('.total_money').text());
        var free_deliv = parseInt($('.dozakaz').attr('data-free-deliv'));
        var dozakaz = (free_deliv > total_cart) ? free_deliv - total_cart : '';
        if (total_cart) {
            if (total_cart < 1500) {
                if (dozakaz > 0) {
                    $('.rd').text(dozakaz + ' грн');
                    if (window.location.href.indexOf('/ua/') === -1) {
                        $('.dozak_word').text('Дозакажите');
                    } else {
                        $('.dozak_word').text('Дозамовте');
                    }
                    $('.dozakaz, .dozakaz_in').show();
                    $('.free').hide();
                } else {
                    if (window.location.href.indexOf('/ua/') === -1) {
                        $('.dozak_word').text('Закажите');
                    } else {
                        $('.dozak_word').text('Замовте');
                    }
                    $('.rd').text(free_deliv + ' грн');
                    $('.dozakaz, .dozakaz_in').show();
                    $('.free').hide();
                }
            } else {
                $('.dozakaz').show();
                $('.dozakaz_in').hide();
                $('.free').show();
            }
        } else {
            if (window.location.href.indexOf('/ua/') === -1) {
                $('.dozak_word').text('Закажите');
            } else {
                $('.dozak_word').text('Замовте');
            }
            $('.rd').text(free_deliv + ' грн');
            $('.dozakaz, .dozakaz_in').show();
            $('.free').hide();
        }
    }, 'html');
}
function freeDeliveryCart() {
    if ($('#total_total').length) {
        var polt_total = parseInt($('#total_sub_total .simplecheckout-cart-total-value').text().replace(/[^\d\.]/g, ''));
        if (polt_total >= 1500) {
            if ($('input[value*="novaposhta"]').is(':checked')) {
                if (window.location.href.indexOf('/ua/') === -1) {
                    $('#total_shipping .simplecheckout-cart-total-value').html('<span class="lint_thr">50 грн</span> <span class="free_deliv">бесплатно</span>');
                } else {
                    $('#total_shipping .simplecheckout-cart-total-value').html('<span class="lint_thr">50 грн</span> <span class="free_deliv">безкоштовно</span>');
                }
            }
            $('td.quote').each(function () {
                var label_val = $(this).find('label').text().replace(/[^\d\.]/g, '');
                if (label_val > 0) {
                    if ($(this).closest('tr').find('input').prop('checked')) {
                        $(this).closest('tr').find('input').prop('checked', false);
                        reloadAll();
                    }
                    $(this).closest('tr').hide();
                } else {
                    $(this).closest('tr').show();
                }
            });
            $('.title label:contains("Доставка в отделение Новой Почты")').text('Бесплатная доставка в отделение Новой Почты');
            $('.title label:contains("Доставка у відділення Нової Пошти")').text('Безкоштовна доставка у відділення Нової Пошти');
        } else {
            $('#simplecheckout_shipping tr').show();
            $('.title label:contains("Доставка в отделение Новой Почты")').text('Доставка в отделение Новой Почты');
            $('.title label:contains("Доставка у відділення Нової Пошти")').text('Доставка у відділення Нової Пошти');
            //$('#total_shipping .simplecheckout-cart-total-value').html('50 грн');
            $('td.quote').each(function () {
                var label_text = $(this).find('label').text();

                var label_input = $(this).closest('tr').find('input');

                if(label_input.prop('checked')) {
                    $('#total_shipping .simplecheckout-cart-total-value').html(label_text);
                }
            });
            // npCost();
        }
    }
}
function npCost() {
    // $('.title label:contains("Новой Почты")').closest('tr').find('.quote label').text('50 грн');
}