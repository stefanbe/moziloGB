$(function() {
    $('form.js-entries-form').each(function() {
        var form_item = $(this);
        var form_name = this.name.replace(/newentry_/,'');

        if($('.js-entry-show',form_item).length < 1)
            return true;

        $('.js-entry-show',form_item).show(0);
        $('.js-entry-hide',form_item).css("display","none");
        $('.entry-new-success',form_item).css("display","none");

        $('.js-entry-new',form_item).before($("label[for=\"in-radio-new"+form_name+"new\"]"));

        $('input[id^="in-radio-new"]',form_item).css("display","none");

        if($('input[type="hidden"]',form_item).val() == "new")
            $('.js-entry-new',form_item).show(0);

        form_item.on('click','.js-entry-box-show,.js-entry-box-hide', function(event,speed) {
            var that = $(this);
            var box = $('.js-entry-box',form_item);
            if(speed == 0) speed = speed; else speed = 'slow';
            that.hide();
            if(that.hasClass('js-entry-box-show')) {
                that.siblings('.js-entry-box-hide').show();
                box.show(speed);
            } else {
                that.siblings('.js-entry-box-show').show();
                box.hide(speed);
            }
        });

        form_item.on('click','input[id^="in-radio-new"]', function(event,speed) {
            var new_item = $('.js-entry-new',form_item);
            var add_item = $(this).closest('.js-entry-add-new');
            new_item.addClass('js-new-class');
            if(add_item.length < 1) {
                add_item = $(this).closest('label');
                new_item.removeClass('js-new-class');
            }
            if(speed == 0) speed = speed; else speed = 'slow';

            if(add_item.next('.js-entry-new').length > 0 && new_item.is(':visible'))
                new_item.hide(speed);
            else {
                if(speed == 'slow') {
                    $('.entry-new-error,.entry-new-success',form_item).remove();
                    $('.entry-input-error',form_item).removeClass('entry-input-error');
                    $('input[type="text"],textarea',new_item).val("");
                    $('input[type="checkbox"]',new_item).prop('checked',false);
                }
                add_item.after(new_item);
                new_item.hide(0).show(speed);
                $('input[type="text"]',new_item).eq(0).focus();
            }
        });

        form_item.on('click','input[type="reset"]', function() {
            var new_item = $('.js-entry-new',form_item);
            $('.entry-new-error,.entry-new-success',form_item).remove();
            $('.entry-input-error',form_item).removeClass('entry-input-error');
            $('input[type="text"],textarea',new_item).val("");
            $('input[type="checkbox"]',new_item).prop('checked',false);
        });

        if(typeof change_page != "undefined") {
            if(form_name == change_page) {
                $('.js-entry-box-show',form_item).trigger('click',[0]);
                if($('.js-page-change-scroll',form_item).length > 0) {
                    var item_scroll = $('.js-page-change-scroll',form_item);
                    setTimeout(function() {
                        item_scroll = Math.ceil(item_scroll.offset().top);
                        var item = 'html,body';
                        if($.browser.opera)
                            item = 'html';
                        $(item).animate({scrollTop:item_scroll},300);
                    },100);
                }
            }
        }

        if(typeof scroll_to != "undefined") {
            if(form_name == scroll_to.substring((scroll_to.indexOf("_")+1),scroll_to.lastIndexOf("_"))) {
                var to_scroll = $('#'+scroll_to).children().eq(0);
                $('.js-entry-box-show',form_item).trigger('click',[0])
                if($('.entry-new-error',form_item).length > 0) {
                    $("input[id=\"in-radio-new"+form_name+scroll_to.substr((scroll_to.lastIndexOf("_")+1))+"\"]",form_item).trigger('click',[0])
                    var to_scroll = $('.js-entry-new',form_item);
                }
                if($('.entry-new-success',form_item).length > 0) {
                    $('.js-entry-add-new',$('#'+scroll_to)).eq(0).after($('.entry-new-success',form_item).show(0));
                }
                if(to_scroll.length > 0) {
                    setTimeout(function() {
                        to_scroll = Math.ceil((to_scroll.offset().top + (to_scroll.outerHeight() / 2)) - ($(window).height() / 2));
                        var item = 'html,body';
                        if($.browser.opera)
                            item = 'html';
                        $(item).animate({scrollTop:to_scroll},300);
                    },100);
                }
            }
        }
    }); // each
});

