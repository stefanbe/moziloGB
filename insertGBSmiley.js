
function insertGBSmiley(aTag,gb_name,gb_entry) {
    var input = document.forms['newentry_'+gb_name].elements[gb_entry];
    var scrolltop = input.scrollTop;
    input.focus();
    /* für Internet Explorer */
    if(typeof document.selection != 'undefined') {
        /* Einfügen des Formatierungscodes */
        var range = document.selection.createRange();
        var insText = range.text;
            range.text = aTag;
        /* Anpassen der Cursorposition */
        range = document.selection.createRange();
        range.move('character', 0);
        range.select();
    }
    /* für neuere auf Gecko basierende Browser */
    else if(typeof input.selectionStart != 'undefined') {
        /* Einfügen des Formatierungscodes */
        var start = input.selectionStart;
        var end = input.selectionEnd;
        var insText = input.value.substring(start, end);
        input.value = input.value.substr(0, start) + aTag + input.value.substr(end);
        /* Anpassen der Cursorposition */
        var pos;
        pos = start + aTag.length;
        input.selectionStart = pos;
        input.selectionEnd = pos;
    }
    /* für die Übrigen Browser */
    else {
        /* Abfrage der Einfügeposition */
        var pos;
        var re = new RegExp('^[0-9]{0,3}$');
        while(!re.test(pos)) {
            pos = prompt("Einfügen an Position (0.." + input.value.length + "):", "0");
        }
        if(pos > input.value.length) {
            pos = input.value.length;
        }
        /* Einfügen des Formatierungscodes */
        var insText = prompt("Bitte geben Sie den zu formatierenden Text ein:");
        input.value = input.value.substr(0, pos) + aTag + insText + input.value.substr(pos);
    }
    input.scrollTop = scrolltop;
}

function smiley_show(id) {
    document.getElementById(id).style.display = "";
}