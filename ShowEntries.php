<?php if(!defined('IS_CMS')) die();

require_once(PLUGIN_DIR_REL."moziloGB/DatabaseGB.php");

class ShowEntries extends DatabaseGB {

    private $gbentries;
    public $dbname;

    public $template;
    public $template_new;
    public $template_event;
    private $template_event_comment;
    public $gbsettings;
    public $gblanguage;
    private $smileys;

    private $entry_img_mail;
    private $entry_img_web;
    private $entry_time_format;

    private $entry_number;
    public $to_entry_checked = false;
    public $botwords;
    public $badwords;

    // initialize settings variable
    function __construct($dbname,$tmpl,$gbsettings,$gblanguage) {
        $this->dbname = $dbname;


        $this->is_admin = false;
        if(defined('PLUGINADMIN'))
            $this->is_admin = true;

        if($dbname !== false)
            $this->DatabaseGB_init($dbname);

        $this->gbsettings = $gbsettings;
        $this->gblanguage = $gblanguage;

        if($this->loadTemplate($tmpl))
            $this->loadTemplateFiles($tmpl);
        else
            $this->db_error_status = "db_no_template";

        $this->smileys = false;
        if($this->gbsettings->get("showsmileys") == "true") {
            if($this->is_admin) {
                require_once(BASE_DIR_CMS."Smileys.php");
                $this->smileys = new Smileys(BASE_DIR_CMS."smileys");
            } else {
                global $smileys;
                $this->smileys = $smileys;
            }
        }
    }

    function get_template($curent_page = 1) {
        $entriesperpage = $this->gbsettings->get("entriesperpage");
        if($this->is_admin)
            $entriesperpage = 0;
        $this->gbentries = $this->getEntriesPages($curent_page,$entriesperpage);

        if($this->is_admin) {
            $this->replace_SmileyBar();
            $this->replace_no_events();
            $this->replace_events();
            return $this->template;
        }

        $this->replace_new();
        $this->replace_no_events();
        $this->replace_pages_link();
        $this->replace_events();

        return $this->template;
    }

    function findTagByAttrValue($attr,$value,$content = false) {
        if(!$content)
            return false;
        $find = array();
        # open close Tags
        if($attr == "class")
            preg_match_all('#<([a-z0-9]+)[^>]*?'.$attr.'=["\'][^"\']*?'.$value.'[^"\']*?["\'][^>]*?>(.*?)</\1>#is', $content, $match);
        else
            preg_match_all('#<([a-z0-9]+)[^>]*?'.$attr.'=["\']'.$value.'["\'][^>]*?>(.*?)</\1>#is', $content, $match);
        if(isset($match[0][0])) {
            foreach($match[0] as $key => $tmp) {
                $start_pos = strpos($content,$match[0][$key]);
                $start_ofs = $start_pos + strlen($match[0][$key]);
                $close_tag = '</'.$match[1][$key].'>';
                $op_items = substr_count($match[0][$key], '<'.$match[1][$key]);
                $cl_items = substr_count($match[0][$key], $close_tag);
                if($op_items > $cl_items) {
                    for($i = $cl_items;$i < $op_items;$i++) {
                        $start_ofs = (strpos($content,$close_tag,$start_ofs) + strlen($close_tag));
                    }
                    $match[0][$key] = substr($content,$start_pos,($start_ofs - $start_pos));
                }
            }
            $find = $match[0];
        }
        # no open close Tags
        if($attr == "class")
            preg_match_all('#<(area|base|br|col|command|embed|hr|img|input|keygen|link|meta|param|source|track|wbr){1,1}[^>]*?'.$attr.'=["\'][^"\']*?'.$value.'[^"\']*?["\'][^>]*?>#is', $content, $match);
        else
            preg_match_all('#<(area|base|br|col|command|embed|hr|img|input|keygen|link|meta|param|source|track|wbr){1,1}[^>]*?'.$attr.'=["\']'.$value.'["\'][^>]*?>#is', $content, $match);
        if(isset($match[0][0])) {
            if(count($find) > 0)
                $find = array_merge($find,$match[0]);
            else
                $find = $match[0];
        }
        # nur eins gefunden als string zurück
        if(count($find) == 1)
            return $find[0];
        elseif(count($find) > 0)
            return $find;
        return false;
    }

    function replace_new() {
        if($this->is_maintenance or $this->entry_no_response)
            $this->template = str_replace("{TMPL_NEW_ENTRY}","",$this->template);
        else {
            $this->replace_SmileyBar();
            $this->template = str_replace("{TMPL_NEW_ENTRY}",$this->template_new,$this->template);
        }
    }

    function replace_no_events() {
        if($this->gbentries === false and false !== ($search = $this->findTagByAttrValue("class","no_entry_remove",$this->template)))
            $this->template = str_replace($search,"",$this->template);
        $this->template = str_replace("{NO_OF_ENTRIES}",$this->countEntries(),$this->template);
    }

    function replace_pages_link() {
        if(($this->gbentries === false or count($this->gbentries) < 2) and false !== ($search = $this->findTagByAttrValue("class","per_page_remove",$this->template)))
            $this->template = str_replace($search,"",$this->template);
        else {
            global $CatPage;
            $html = "";
            foreach($this->gbentries as $number => $pages) {
                if(is_array($pages))
                    $html .= '<span class="entry-page-link entry-page-link-activ">'.$number.'</span>';
                else
                    $html .= '<a class="entry-page-link" href="'.$CatPage->get_Href(CAT_REQUEST,PAGE_REQUEST,$CatPage->get_Query($this->dbname."=".$number."&moziloGB=".$this->dbname)).'">'.$number."</a>";
            }
            $this->template = str_replace("{ENTRIES_PER_PAGE}",$html,$this->template);
        }
    }

    function replace_events() {
        if($this->gbentries === false) {
            $this->template = str_replace("{TMPL_ENTRY}","",$this->template);
        } else {
            $html = "";
            $class = 'entry-db-'.$this->dbname;
            $class = '';
            if($this->is_admin)
                $class = ' entry-admin-db';
            foreach($this->gbentries as $pages) {
                if(is_array($pages)) {
                    $html = '<ul class="entry-ul entry-first-ul'.$class.'">';
                    foreach($pages as $number) {
                        $html .= $this->help_replace_events($number);
                    }
                    $html .= '</ul>';
                }
            }
            $this->template = str_replace("{TMPL_ENTRY}",$html,$this->template);
        }
    }

    function help_replace_events($number) {
        $html = "";
        if(!is_array($number))
            $number = array($number);
        $number = implode(GB_DELIMITER,$number);
        $tmp_number = $number;
        $html .= '<li id="js-scroll_'.$this->dbname.'_'.$tmp_number.'" class="entry-li">';
        $html .= $this->make_replace_event($number);
        $sub_ul = true;
        if(count($this->getEntryArray($number)) > 1) {
            $html .= '<ul class="entry-ul">';
            foreach($this->getEntryArray($number) as $pos => $data) {
                if($pos === "en") {
                    continue;
                } else {
                    $tmp_number = $tmp_number.GB_DELIMITER.$pos;
                    if(count($data) > 1) {
                        $html .= $this->help_replace_events($tmp_number);
                    } else {
                        $html .= '<li id="js-scroll_'.$this->dbname.'_'.$tmp_number.'" class="entry-li">';
                        $html .= $this->make_replace_event($tmp_number);
                        $html .= '</li>';
                    }
                    $tmp_number = $number;
                }
            }
            $html .= '</ul>';
        }
        $html .= '</li>';
        return $html;
    }

    function make_replace_event($number) {
        global $specialchars;
        $tmp = array();
        $tmp[0] = array('{ENTRY_TIME}',
                        '{ENTRY_NAME}',
                        '{ENTRY_MAIL}',
                        '{ENTRY_WEB}',
                        '{ENTRY_RESPONSE}',
                        '{ENTRY_COMMENT}',
                        '{ADMIN_ENTRY_IP}',
                        '{ADMIN_ENTRY_DOMAIN}');
        $tmp[1] = $this->getEntry($number);
        $tmp[1][0] = strftime($this->entry_time_format, $tmp[1][0]);

        $input_disable = "";
        if($this->is_admin and !$this->is_maintenance)
            $input_disable = ' disabled="disabled"';

        if($this->is_admin) {
            if(strlen($tmp[1][2]) > 2)
                $tmp[1][2] = '<a href="mailto:'.$tmp[1][2].'" title="'.$tmp[1][2].'">@</a>';
            else
                $tmp[1][2] = '<span>@</span>';
        } else {
            if($this->entry_img_mail and strlen($tmp[1][2]) > 2) {
                $tmp_text = $this->gblanguage->getLanguageHtml('text_mail');
                $tmp[1][2] = '<a class="entry-icons entry-icons-mail" href="'.$specialchars->obfuscateAdress("mailto:".$tmp[1][2], 3).'" title="'.$tmp_text.'"><img src="'.URL_BASE.PLUGIN_DIR_NAME.'/moziloGB/'.$this->entry_img_mail.'" alt="'.$tmp_text.'" /></a>';
            } else
                $tmp[1][2] = "";
        }
        $web_name = "";
        if(strlen($tmp[1][1]) > 1) {
            $web_name = $tmp[1][1];
            if(strlen($tmp[1][3]) > 2)
                $web_name = '<a class="entry-name-web" href="'.$tmp[1][3].'" target="_blank" title="'.$tmp[1][3].'" rel="nofollow">'.$tmp[1][1].'</a>';
        }
        if($this->entry_img_web and strlen($tmp[1][3]) > 2) {
            $tmp_text = $this->gblanguage->getLanguageHtml('text_web');
            $tmp[1][3] = '<a class="entry-icons entry-icons-web" href="'.$tmp[1][3].'" target="_blank" title="'.$tmp_text.'" rel="nofollow"><img src="'.URL_BASE.PLUGIN_DIR_NAME.'/moziloGB/'.$this->entry_img_web.'" alt="'.$tmp_text.'" /></a>';
        } else
            $tmp[1][3] = "";

        if($this->is_admin)
            $tmp[1][5] = '<textarea rows="1" class="entry-admin-comment" name="comment['.$number.'-en-5]"'.$input_disable.'>'.str_replace("<br />","\n",$tmp[1][5]).'</textarea>';
#            $tmp[1][5] = '<input type="text" class="entry-admin-comment" value="'.$tmp[1][5].'" name="comment['.$number.'-en-5]"'.$input_disable.' />';

        // replace emoticons
        if($this->smileys !== false) {
            $tmp[1][4] = $this->smileys->replaceEmoticons($tmp[1][4]);
            if(!$this->is_admin)
                $tmp[1][5] = $this->smileys->replaceEmoticons($tmp[1][5]);
        }

        $tmp_html = $this->template_event;
        if(strlen($tmp[1][5]) < 2 and !$this->is_admin)
            $tmp_html = str_replace($this->template_event_comment,"",$tmp_html);

        $tmp_html = str_replace($tmp[0],$tmp[1],$tmp_html);
        $tmp_html = str_replace("{ENTRY_WEB_NAME}",$web_name,$tmp_html);
        $tmp_nr = array();
        foreach(explode(GB_DELIMITER,$number) as $pos => $nr)
            $tmp_nr[$pos] = $nr + 1;

        if(trim($this->entry_number) == "last")
            $number_string = $tmp_nr[(count($tmp_nr) - 1)];
        else
            $number_string = implode($this->entry_number,$tmp_nr);

        $tmp_html = str_replace('{ENTRY_NUMBER}',$number_string,$tmp_html);
        if($this->is_admin) {
            $tmp_html = str_replace('{ADMIN_ENTRY_DELETE}','<input class="entry-admin-delete" type="checkbox" name="delete[]" value="'.$number.'"'.$input_disable.' />',$tmp_html);
        }
        if($this->is_maintenance or $this->entry_no_response or !$this->entry_use_sub) {
            if(false !== ($tmp = $this->findTagByAttrValue("class","new_remove",$tmp_html)))
                $tmp_html = str_replace($tmp,"",$tmp_html);
            if(false !== ($tmp = $this->findTagByAttrValue("for","in-radio-new",$tmp_html)))
                $tmp_html = str_replace($tmp,"",$tmp_html);
            if(false !== ($tmp = $this->findTagByAttrValue("id","in-radio-new",$tmp_html)))
                $tmp_html = str_replace($tmp,"",$tmp_html);
        }
        if(!$this->is_admin) {
            $checked = "";
            if($this->entry_use_sub and $this->to_entry_checked !== false and $number === $this->to_entry_checked)
                $checked = ' checked="checked"';
            if(false !== ($tmp = $this->findTagByAttrValue("for","in-radio-new",$tmp_html))) {
                $tm = str_replace('for="in-radio-new"','for="in-radio-new'.$this->dbname.$number.'"',$tmp);
                $tmp_html = str_replace($tmp,$tm,$tmp_html);
            }
            if(false !== ($tmp = $this->findTagByAttrValue("id","in-radio-new",$tmp_html))) {
                $tm = str_replace('id="in-radio-new"','id="in-radio-new'.$this->dbname.$number.'"'.$checked,$tmp);
                $tm = preg_replace('/value=(["\']).*["\']/U','value=${1}'.$number.'${1}',$tm);
                $tmp_html = str_replace($tmp,$tm,$tmp_html);
            }
        }
        return $tmp_html;
    }

    function loadTemplate($tmpl) {
        $this->template = "";
        $this->template_new = "";
        $this->template_event = "";
        $this->template_event_comment = "";
        $this->entry_number = GB_DELIMITER;
        if($this->is_admin) {
            $this->entry_time_format = '%d.%m.%Y<span></span>%H:%M:%S';
            $this->template = '<p class="mo-bold">'.$this->gblanguage->getLanguageHtml('admin_entries_no_of').' {NO_OF_ENTRIES}</p>{TMPL_ENTRY}';

            $this->template_event = '<div class="entry-admin-border entry-admin-box entrytable">
                <div class="entry-admin-head">
                    <span>{ENTRY_NUMBER}</span>
                    <span class="entry-admin-time">{ENTRY_TIME}</span>
                    <span>{ENTRY_WEB_NAME}</span>
                    <span class="entry-admin-mail">{ENTRY_MAIL}</span>
                    <span>{ADMIN_ENTRY_DOMAIN}</span>
                    <span>{ADMIN_ENTRY_IP}</span>
                    {ADMIN_ENTRY_DELETE}
                </div>
                <div class="entry-admin-border entry-admin-box entry-admin-inbox">{ENTRY_RESPONSE}</div>';
            if($this->entry_use_comment) {
                $this->template_event .= '<div class="entry-admin-inbox">'.$this->gblanguage->getLanguageHtml('text_comment').'<br />{ENTRY_COMMENT}</div>';
                $this->template .= '{SMILEYS}';
            }
            $this->template_event .= '</div>';
            return true;
        }

        if(is_dir(PLUGIN_DIR_REL."moziloGB/layouts/".$tmpl)) {
            if(is_file(PLUGIN_DIR_REL."moziloGB/layouts/".$tmpl."/template.html")) {
                $this->template = file_get_contents(PLUGIN_DIR_REL."moziloGB/layouts/".$tmpl."/template.html");
                $this->template = trim($this->template);
            } else
                return false;

            if(is_file(PLUGIN_DIR_REL."moziloGB/layouts/".$tmpl."/entry.html")) {
                $this->template_event = file_get_contents(PLUGIN_DIR_REL."moziloGB/layouts/".$tmpl."/entry.html");
                $this->template_event = trim($this->template_event);
            } else
                return false;

            if(is_file(PLUGIN_DIR_REL."moziloGB/layouts/".$tmpl."/new_entry.html")) {
                $this->template_new = file_get_contents(PLUGIN_DIR_REL."moziloGB/layouts/".$tmpl."/new_entry.html");
                $this->template_new = trim($this->template_new);
            } else
                return false;
        } else {
            return false;
        }

        foreach($this->gblanguage->LANG_CONF->toArray() as $lang => $tmp) {
            if(strpos($lang,"text_") !== 0)
                continue;
            $this->template = str_replace('{'.strtoupper($lang).'}',$this->gblanguage->getLanguageHtml($lang),$this->template);
            $this->template_event = str_replace('{'.strtoupper($lang).'}',$this->gblanguage->getLanguageHtml($lang),$this->template_event);
            $this->template_new = str_replace('{'.strtoupper($lang).'}',$this->gblanguage->getLanguageHtml($lang),$this->template_new);
        }
        $this->template = str_replace('{IMG_SRC}',URL_BASE.PLUGIN_DIR_NAME.'/moziloGB/layouts/'.$tmpl.'/',$this->template);
        $this->template_event = str_replace('{IMG_SRC}',URL_BASE.PLUGIN_DIR_NAME.'/moziloGB/layouts/'.$tmpl.'/',$this->template_event);
        $this->template_new = str_replace('{IMG_SRC}',URL_BASE.PLUGIN_DIR_NAME.'/moziloGB/layouts/'.$tmpl.'/',$this->template_new);

        if(preg_match("/\<!--entry_time_format=(.*)-->/U",$this->template,$tmp)) {
            $this->template = str_replace($tmp[0],"",$this->template);
            $this->entry_time_format = $tmp[1];
        } else {
            global $language;
            $this->entry_time_format = $language->getLanguageValue("_dateformat_0");
        }

        if(preg_match("/\<!--entry_number=(.*)-->/U",$this->template,$tmp)) {
            $this->template = str_replace($tmp[0],"",$this->template);
            $this->entry_number = $tmp[1];
        }

        if(false !== ($tmp = $this->findTagByAttrValue("class","comment_remove",$this->template_event))) {
            if($this->entry_use_comment)
                $this->template_event_comment = $tmp;
            else
                $this->template_event = str_replace($tmp,"",$this->template_event);
        }

        if($this->entry_use_sub and !$this->entry_no_response) {
            $checked = "";
            if($this->to_entry_checked === "new")
                $checked = ' checked="checked"';
            if(false !== ($tmp = $this->findTagByAttrValue("for","in-radio-new",$this->template_new))) {
                $tm = str_replace('for="in-radio-new"','for="in-radio-new'.$this->dbname.'new"',$tmp);
                $this->template_new = str_replace($tmp,$tm,$this->template_new);
            }
            if(false !== ($tmp = $this->findTagByAttrValue("id","in-radio-new",$this->template_new))) {
                $tm = str_replace('id="in-radio-new"','id="in-radio-new'.$this->dbname.'new"'.$checked,$tmp);
                $this->template_new = str_replace($tmp,$tm,$this->template_new);
            } else
                $this->template_new = '<input type="hidden" name="{GB_INPUT}number" value="new" />'.$this->template_new;
        } else {
            if(false !== ($tmp = $this->findTagByAttrValue("class","new_remove",$this->template_new)))
                $this->template_new = str_replace($tmp,"",$this->template_new);
            if(false !== ($tmp = $this->findTagByAttrValue("for","in-radio-new",$this->template_new)))
                $this->template_new = str_replace($tmp,"",$this->template_new);
            if(false !== ($tmp = $this->findTagByAttrValue("id","in-radio-new",$this->template_new)))
                $this->template_new = str_replace($tmp,"",$this->template_new);
            if(!$this->entry_use_sub and (!$this->entry_no_response or !$this->is_maintenance))
                $this->template_new = '<input type="hidden" name="{GB_INPUT}number" value="new" />'.$this->template_new;
        }
        return true;
    }

    function loadTemplateFiles($tmpl) {
        if($this->is_admin)
            return;

        global $ALOWED_IMG_ARRAY;
        $test_jquery = false;
        $tmp = $ALOWED_IMG_ARRAY;
        $tmp[] = ".css";
        $this->entry_img_web = false;
        $this->entry_img_mail = false;

        foreach(getDirAsArray(PLUGIN_DIR_REL.'moziloGB/layouts/'.$tmpl,$tmp) as $file) {
            if(strtolower(substr($file,0,4)) == "web.")
                $this->entry_img_web = "layouts/".$tmpl.'/'.$file;
            if(strtolower(substr($file,0,5)) == "mail.")
                $this->entry_img_mail = "layouts/".$tmpl.'/'.$file;
            if(strtolower(substr($file,-4)) == ".css") {
                global $syntax;
                $syntax->insert_in_head('<style type="text/css"> @import "'.URL_BASE.PLUGIN_DIR_NAME.'/moziloGB/layouts/'.$tmpl.'/'.$file.'"; </style>');
            }
        }
    }

    function isBotwords($str) {
        # keine wörter in conf
        if(strlen($this->gbsettings->get("botwords")) < 2 or strlen($str) < 2) return false;
        if(!isset($this->botwords)) {
            // Array von Bot-Phrasen
            $botwords = explode(",", $this->gbsettings->get("botwords"));
            $this->botwords = "/(";
            foreach ($botwords as $botword)
                $this->botwords .= rtrim($botword)."|";
            $this->botwords = substr($this->botwords, 0, strlen($this->botwords)-1).")/i";
        }
        return preg_match($this->botwords, $str);
    }

    function badWordFilter($str) {
        # keine wörter in conf
        if(strlen($this->gbsettings->get("goodword")) < 2 or strlen($this->gbsettings->get("badwords")) < 2)
            return $str;
        // Das "gute Wort"
        $goodword = $this->gbsettings->get("goodword");
        if(!isset($this->badwords)) {
            // "Böse-Wörter"-Array
            $badwords = explode(",", $this->gbsettings->get("badwords"));
            // Badword-RegEx zusammenbauen
            $this->badwords = "/(";
            foreach ($badwords as $badword)
                $this->badwords .= rtrim($badword)."|";
            $this->badwords = substr($this->badwords, 0, strlen($this->badwords)-1).")/i";
        }
        // ersetze durch "Gutes Wort"
        return preg_replace($this->badwords, $goodword, $str);
    }

    function replace_SmileyBar() {
        $tmpl = "template_new";
        if($this->is_admin)
            $tmpl = "template";
        if($this->smileys !== false and strpos($this->$tmpl,'{SMILEYS}') !== false and is_file(BASE_DIR.PLUGIN_DIR_NAME.'/moziloGB/insertGBSmiley.js')) {
            $name_entry = "admin_input_smiley";
            if(!$this->is_admin) {
                $name_entry = "'{GB_INPUT}entry'";
            }
            $content = '<div id="smileybar-show-'.$this->dbname.'" class="entry-smileybar" style="display:none;">';
            $search = array(":",'"');
            $replace = array("&#058;","&quot;");
            foreach($this->smileys->getSmileysArray() as $icon => $emoticon) {
                $content .= '<img class="entry-smiley" title="&#058;'.$icon.'&#058;" alt="'.str_replace($search,$replace,$emoticon).'" src="'.URL_BASE.CMS_DIR_NAME.'/smileys/'.$icon.'.gif" onclick="insertGBSmiley(\' &#058;'.$icon.'&#058; \',\''.$this->dbname.'\','.$name_entry.')" />';
            }
            $content .= "</div>";
            if($this->is_admin)
                $content .= '<script type="text/javascript" src="'.URL_BASE.PLUGIN_DIR_NAME.'/moziloGB/insertGBSmiley.js"></script>';
            else {
                global $syntax;
                $syntax->insert_in_head('<script type="text/javascript" src="'.URL_BASE.PLUGIN_DIR_NAME.'/moziloGB/insertGBSmiley.js"></script>');
                $content .= '<script type="text/javascript">/*<![CDATA[*/smiley_show("smileybar-show-'.$this->dbname.'");/*]]>*/</script>';
            }

            $this->$tmpl = str_replace('{SMILEYS}',$content,$this->$tmpl);
        } else
            $this->$tmpl = str_replace('{SMILEYS}','',$this->$tmpl);
    }
}
?>