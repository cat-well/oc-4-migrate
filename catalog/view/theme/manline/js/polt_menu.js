$(document).ready(function () {
    var time_id;
    $(".nav_menu > li.catalog_list").each(function () {
        $(this).hover(function () {
            var self = $(this);
            if (self.closest(".nav_menu").find(".children_w").is(':visible')) {
                self.closest(".nav_menu").find("li").not(self).find('.children_w').stop(true, true).slideUp(100, function () {
                    $(this).css('overflow', 'visible')
                });
            }
            // self.find(".children_w li ul").hide();
            if (time_id) {
                clearTimeout(time_id);
            }
            time_id = setTimeout(function () {
                self.find(".children_w").stop(true, true).slideDown(100, function () {
                    $(this).css('overflow', 'visible')
                });
            }, 100);
        }, function () {
            var self = $(this);
            if (time_id) {
                clearTimeout(time_id);
            }
            time_id = setTimeout(function () {
                self.find(".children_w").stop(true, true).slideUp(100, function () {
                    $(this).css('overflow', 'visible')
                });
            }, 500);
        });
    });
});