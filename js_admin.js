var admin_input_smiley = false;
var dialog_db_delete = false;
$(function() {
    // die liste der Datenbanken Aktualiesieren
    $('#mozilo-gb-db-list', top.document).html($('#mozilo-admin-gb-db-list').html());

    $('body').append('<div id="dialog-db-delete"></div>');
    $("#dialog-db-delete").dialog({
        autoOpen: false,
        resizable: true,
        height: "auto",
        width: "auto",
        modal: true,
        title: mozilo_lang["dialog_title_delete"],
        create: function(event, ui) {
            dialog_db_delete = $(this);
            dialog_db_delete.data("confirm","false");
        },
        close: function(event, ui) {
            dialog_db_delete.data("confirm","false");
        },
        buttons: [{
            text: mozilo_lang["yes"],
            click: function() {
                $('form[name="newentry_'+$('input[name="curent_db"]').val()+'"]').append('<input type="hidden" name="'+dialog_db_delete.data('confirm')+'" value="true" />').submit();
                dialog_db_delete.dialog("close");
                }
            },{
            text: mozilo_lang["no"],
            click: function() {
                dialog_db_delete.data("confirm","false");
                dialog_db_delete.dialog("close");
            }
        }]
    });


    $('input[name="deletedbbutton"]').on('click', function(event) {
        dialog_db_delete.data("confirm","false");
        event.preventDefault();

        var tmp = "<b>"+$('input[name="curent_db"]').val()+"_db.php</b>";

        if($('select[name="backupfile"]').length > 0) {
            tmp += "<br /><br />"+admin_js_del_backup_text+"<ul style=\"list-style:none;margin-top:0;padding-left:1em;\">";
            $('select[name="backupfile"] option').each(function() {
                if($(this).val() != "false")
                    tmp += "<li><b>"+$(this).val()+"</b></li>";
            });
            tmp += "</ul>";
        }
        dialog_db_delete.data("confirm","deleteconfirm");
        dialog_db_delete.html(tmp);
        dialog_db_delete.dialog("open");
    });

    $('input[name="deleteentriesbutton"]').on('click', function(event) {
        dialog_db_delete.data("confirm","false");
        event.preventDefault();

        var tmp = admin_js_del_entries_text;

        if($('.entry-admin-delete:checked').length > 0) {
            tmp += "<ul style=\"list-style:none;margin-top:0;padding-left:1em;\">";
            $('.entry-admin-delete:checked').each(function() {
                var pos = $(this).val();
                if(pos != "false") {
                    pos = pos.split("-");
                    for(var i = 0; i < pos.length; i++)
                        pos[i]++;
                    pos = pos.join("-");
                    tmp += "<li><b>"+pos+"</b></li>";
                }
            });
            tmp += "</ul>";
            dialog_db_delete.data("confirm","deleteconfirmentry");
            dialog_db_delete.html(tmp);
            dialog_db_delete.dialog("open");
        }
    });

    if($('.entry-smileybar').length > 0) {
        $('.entry-smileybar').hide(0).append('<a class="entry-admin-smileybar-close entry-admin-border mo-ui-state-hover"><span class="ui-icon ui-icon-closethick">close</span></a>');

        $('.entry-admin-smileybar-close').click(function(event) {
            event.preventDefault();
            $('.entry-smileybar').hide(100);
            $('.entry-admin-comment').height('1em');
            admin_input_smiley = false;
        });

        $('body').on('click','.entry-admin-comment',function() {
            var smiley_box = $('.entry-smileybar');
            var that = $(this);
            if(admin_input_smiley == that.attr('name'))
                return false;
            $('.entry-admin-comment').height('1em');
            admin_input_smiley = that.attr('name');
            smiley_box.hide(100, function() {
                that.height('5em');
                that.after(smiley_box.show(300));
            });
        });
    }
});

