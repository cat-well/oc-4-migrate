(function (a) {
    a.fn.endlessScroll = function (b) {
        var c = {bottomPixels:400, fireOnce:true, fireDelay:0, contentTarget:"", callback:function () {
            return true
        }, resetCounter:function () {
            return false
        }, ceaseFire:function () {
            return false
        }, intervalFrequency:250};
        var b = a.extend({}, c, b), d = false, e = 0, f = false, g = this, h = a(".endless_scroll_inner_wrap", this), i;
        a(this).scroll(function () {
            f = true;
            g = this
        });
        setInterval(function () {
            if (f) {
                f = false;
                if (b.ceaseFire.apply(g, [e]) === true) {
                    return
                }
                if (g == document || g == window) {
                    if (b.contentTarget != "" && a(b.contentTarget).length) {
                        i = a(b.contentTarget).offset().top + a(b.contentTarget).height() - a(window).height() <= a(window).scrollTop() + b.bottomPixels
                    } else {
                        i = a(document).height() - a(window).height() <= a(window).scrollTop() + b.bottomPixels
                    }
                } else {
                    if (h.length == 0) {
                        h = a(g).wrapInner('<div class="endless_scroll_inner_wrap" />').find(".endless_scroll_inner_wrap")
                    }
                    i = h.length > 0 && h.height() - a(g).height() <= a(g).scrollTop() + b.bottomPixels
                }
                if (i && (b.fireOnce == false || b.fireOnce == true && d != true)) {
                    if (b.resetCounter.apply(g) === true) {
                        e = 0
                    }
                    d = true;
                    e++;
                    if (a.isFunction(b.beforeLoad)) {
                        b.beforeLoad.apply(g, [e])
                    }
                    b.callback.apply(g, [e, function () {
                        if (b.fireDelay !== false || b.fireDelay !== 0) {
                            setTimeout(function () {
                                d = false
                            }, b.fireDelay)
                        } else {
                            d = false
                        }
                    }])
                }
            }
        }, b.intervalFrequency)
    }
})(jQuery);

$(".el-p").hide();
$(".el-d").show();

// $(window).endlessScroll({contentTarget:"#el-s", bottomPixels:400, fireOnce:false, callback:function (a, b) {
//     var $next = $('.pagination div.links b').next('a');
//     if ($next.length == 0) {
//         return;
//     }
//     var page = $next.attr('href').match(/page[=-](\d+)/) || [, 1]
//     $("#filterpro_page").val(page[1]);
//     doFilter(false, false, true);
// }});
function showmore() {
    var $next = $('.pagination div.links b').next('a');
    if ($next.length == 0) {
        return;
    }
    var page = $next.attr('href').match(/page[=-](\d+)/) || [, 1]
    $("#filterpro_page").val(page[1]);
    $('.show_once').remove();
    doFilter(false, false, true);
}
$(document).ready(function () {
    const lang = window.location.href.indexOf('/ua/') === -1 ? 'ru' : 'ua';
    $.removeCookie('showmore', { path: '/' });
    if ($('.pagination div.links b').next('a').length > 0) {
        if (lang == 'ua') {
            $('.pagination').after('<div id="showmore"><a onclick="showmore()"><span><img src="catalog/view/theme/manline/image/showmore.png" alt=""/>Показати всі товари</span></a></div>');
        }
        else {
            $('.pagination').after('<div id="showmore"><a onclick="showmore()"><span><img src="catalog/view/theme/manline/image/showmore.png" alt=""/>Показать все товары</span></a></div>');
        }
        $(document).on('click', '#showmore a', function () {
            $.cookie('showmore', 'true', {expires: 1, path: '/'});
        });
        if ($('#showmore').is(":visible")) {
            function trueWordform (num, form_for_1, form_for_2, form_for_5) {
                var num = Math.abs(num) % 100; // берем число по модулю и сбрасываем сотни (делим на 100, а остаток присваиваем переменной $num)
                var num_x = num % 10; // сбрасываем десятки и записываем в новую переменную
                if (num > 10 && num < 20) // если число принадлежит отрезку [11;19]
                    return form_for_5;
                if (num_x > 1 && num_x < 5) // иначе если число оканчивается на 2,3,4
                    return form_for_2;
                if (num_x == 1) // иначе если оканчивается на 1
                    return form_for_1;
                return form_for_5;
            }
            var show_more_limit_p = $('#filterpro_limit').val();
            var filterpro_page_p = $('#filterpro_page').val();
            var count_prod_p = parseInt($('.count_prod').text());
            var show_more_end = count_prod_p - (show_more_limit_p*filterpro_page_p);
            var show_more_text = (show_more_limit_p <= show_more_end) ? show_more_limit_p : show_more_end;
            if (lang == 'ua') {
                var word_more = trueWordform(show_more_text, ' товар',' товари',' товарів');
                $('<a onclick="showmore()" class="show_once col-xs-12 col-md-3">Показати ще <span> ' + show_more_text + word_more + ' </span></a>').appendTo('.product-grid');
            }
            else {
                var word_more = trueWordform(show_more_text, ' товар',' товара',' товаров');
                $('<a onclick="showmore()" class="show_once col-xs-12 col-md-3">Показать еще <span> ' + show_more_text + word_more + ' </span></a>').appendTo('.product-grid');
            }
        }
    }
});
