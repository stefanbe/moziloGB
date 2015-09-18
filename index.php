<?php if(!defined('IS_CMS')) die();

class moziloGB extends Plugin {

    # Datenbank zu Ordnung
    # 0=name 1=mail 2=web 3=entry 4=comment 5=ip 6=host
    public $gblanguage;
    private $newnr = array('name' => 0,'mail' => 1,'web' => 2,'entry' => 3);
    private $inputs = array('name','entry','mail','asta','web','spam','number','privacy');
    private $new_entry;
    private $msg_error;
#    private $use_js = false;
    private $spamprotcalcs;
    private $adminLang;

    function getDefaultSettings() {
        return array(
            "active" => "true",
            "spamprotcalcs" => "3 + 7 = 10<br />5 - 3 = 2<br />1 plus 1 = 2<br />17 minus 7 = 10<br />4 * 2 = 8<br />3 x 3 = 9<br />2 / 2 = 1",
            "newentryemail" => "",
            "badwords" => "fuck,schei(ss|ß)e?",
            "goodword" => "******",
            "botwords" => "cialis,viagra,p(o|0)+rn,penis,enlarge,href",
            "entriesperpage" => 10,
            "entrymaxlength" => 1500,
            "iplocktime" => 60,
            "saveips" => "true",
            "showsmileys" => "true");
    }

    function getContent($value) {

        $this->msg_error = array();

        global $CMS_CONF;
        $this->gblanguage = new Language($this->PLUGIN_SELF_DIR."lang/".$CMS_CONF->get("cmslanguage").".txt");

        list($db_name,$tmpl,$use_js) = array_merge(explode(',',$value,3),array("true"));
        $db_name = trim($db_name);
        $tmpl = trim($tmpl);
        $use_js = trim($use_js);
        if($use_js === "true")
            $use_js = ' js-entries-form';
        else
            $use_js = NULL;

        $this->new_entry = array_fill(0, 7, "");
        $this->makeRandomStr($db_name);
        // Alles initialisieren
        require_once($this->PLUGIN_SELF_DIR."ShowEntries.php");
        $gbdb = new ShowEntries($db_name,$tmpl,$this->settings,$this->gblanguage);

        if($gbdb->db_error_status)
            return '<span class="entry-message-box entry-db-message">'.$this->gblanguage->getLanguageValue($gbdb->db_error_status).'</span>';

        # Für Bots: Das Feld "{GB_INPUT}asta" kann von menschlichen Besucher nicht gesehen werden.
        # Steht was drin, wars also ein Bot.
        # und Bots anhand verbotener Wörter herausfiltern
        $msg_success = "";
        $to_entry = false;
        if(!$gbdb->entry_no_response
                and false !== getRequestValue('submit',"post",false)
                and false !== getRequestValue($_SESSION['GB_INPUT_'.$db_name]['name'],"post",false)
                and false !== getRequestValue($_SESSION['GB_INPUT_'.$db_name]['entry'],"post",false)
                and getRequestValue($_SESSION['GB_INPUT_'.$db_name]['asta'],"post",false) !== false
                and strlen(getRequestValue($_SESSION['GB_INPUT_'.$db_name]['asta'],"post",false)) < 1
                and $this->filterNewEntry($gbdb,$db_name)) {
            $to_entry = $gbdb->getCleanNumber(getRequestValue($_SESSION['GB_INPUT_'.$db_name]['number'],"post"));
            $gbdb->to_entry_checked = $to_entry;

            if($this->checkRequest($db_name,$gbdb)) {
                if(false !== ($to_entry = $gbdb->addEntry($this->new_entry,$to_entry))) {
                    $msg_success = '<span class="entry-new-success">'.$this->gblanguage->getLanguageValue("msg_success")."</span>";
                    $this->newEntryMail($db_name);
                    $this->new_entry = array_fill(0, 7, "");
                    $gbdb->to_entry_checked = false;
                } else
                    $this->msg_error[] = $this->gblanguage->getLanguageValue("msg_save_error");
            }
        }

        $this->setRandomSpamTask($gbdb,$db_name);

        if(count($this->msg_error) > 0)
            $this->msg_error = '<span class="entry-new-error">'.implode('<br />',$this->msg_error).'</span>';
        else
            $this->msg_error = "";

        $is_maintenance = "";
        $no_response = "";
        if($gbdb->entry_no_response)
            $no_response = '<span class="entry-no-response">'.$this->gblanguage->getLanguageHtml("db_no_response").'</span>';
        if($gbdb->is_maintenance) {
            $is_maintenance = '<span class="entry-is-maintenance">'.$this->gblanguage->getLanguageHtml("db_is_maintenance").'</span>';
            $no_response = "";
        }

        $curent_page = 1;
        if(false !== getRequestValue($db_name,"get",false))
            $curent_page = getRequestValue($db_name,"get",false);

        if(getRequestValue($_SESSION['GB_INPUT_'.$db_name]['number'],"post") === "new")
            $curent_page = 1;

        $script = '';
        if($use_js) {
            global $syntax;
            $syntax->insert_in_tail('<script type="text/javascript" src="'.URL_BASE.PLUGIN_DIR_NAME.'/moziloGB/jq_comment.js"></script>');
            $syntax->insert_jquery_in_head('jquery');

            # wenn ein Fehler aufgetreten ist
            if($to_entry !== false)
                $script .= 'var scroll_to = "js-scroll_'.$db_name.'_'.$to_entry.'";';
            # die Seite wechsel deshalb die Box Aufklappen
            if(false !== getRequestValue($db_name,"get",false)
                    and $db_name == getRequestValue("moziloGB","get",false)
                    and false === getRequestValue('submit',"post",false))
                $script .= 'var change_page = "'.$db_name.'";';
            if(strlen($script) > 2)
                $script = '<script type="text/javascript">/*<![CDATA[*/'.$script.'/*]]>*/</script>';
        }

        $this->makeRandomStr($db_name,true);

        global $CatPage;
        $html = '<form accept-charset="{CHARSET}" class="entry-form'.$use_js.'" name="newentry_'.$db_name.'" method="post" action="'.$CatPage->get_Href(CAT_REQUEST,PAGE_REQUEST,$CatPage->get_Query($db_name."=".$curent_page)).'">'.$gbdb->get_template($curent_page).'</form>'.$script;

        if(!$gbdb->to_entry_checked and strlen($this->msg_error) > 2)
            $html = str_replace('entry-input-error-number','entry-input-error',$html);
        else
            $html = str_replace('entry-input-error-number','',$html);

        foreach($this->inputs as $name) {
            $html = str_replace('entry-input-error-'.$name,'',$html);
            $html = str_replace('{GB_INPUT}'.$name,$_SESSION['GB_INPUT_'.$db_name][$name],$html);
        }

        $html = str_replace(array('{NEW_ERROR}','{NEW_SUCCESS}','{NO_RESPONSE}','{MAINTENANCE_MODE}'),array($this->msg_error,$msg_success,$no_response,$is_maintenance),$html);

        return $html;
    }

    function newEntryMail($db_name) {
        if(strlen($this->settings->get("newentryemail")) > 2) {
            require_once(BASE_DIR_CMS."Mail.php");
            if(isMailAvailable()) {
                global $CatPage;
                $mail = $this->gblanguage->getLanguageValue("newentryemail_page")." ".$CatPage->get_HrefText(CAT_REQUEST,PAGE_REQUEST)."\n\n";
                $mail .= $this->gblanguage->getLanguageValue("newentryemail_db")." ".$db_name."\n\n";
                $mail .= $this->gblanguage->getLanguageValue("newentryemail_name")." ".$this->new_entry[0]."\n\n";
                $mail .= $this->gblanguage->getLanguageValue("newentryemail_entry")." \n".str_replace("<br />","\n",$this->new_entry[3])."\n\n";
                sendMail($this->gblanguage->getLanguageValue("newentryemail_subject")." ".$_SERVER['SERVER_NAME'], $mail, $this->settings->get("newentryemail"), $this->settings->get("newentryemail"));
            }
        }
    }

    function filterNewEntry($gbdb,$db_name) {
        global $specialchars;
        $search = array('[',']','{','}','|');
        $replace = array('&#091;','&#093;','&#123;','&#125;','&#124;');
        foreach($this->newnr as $name => $pos) {
            if(($tmp = getRequestValue($_SESSION['GB_INPUT_'.$db_name][$name],"post"))) {
                $tmp = str_replace($search,$replace,$specialchars->rebuildSpecialChars(trim($tmp),false,false));
                if($gbdb->isBotwords($tmp)) return false;
                // "http://" vor den URL hängen, wenn nötig
                if($name === "web" and !stristr($tmp,"://"))
                    $tmp = "http://".$tmp;
                if($name === "name")
                    $tmp = $specialchars->rebuildSpecialChars($tmp,true,false);
                if($name === "entry") {
                    $tmp = $gbdb->badWordFilter($tmp);
                    $tmp = str_replace(array("\r\n","\r","\n"),"<br />",$tmp);
                    $tmp = $specialchars->rebuildSpecialChars($tmp,false,false);
                    $tmp = str_replace("  ","&nbsp;&nbsp;",$tmp);
                    $tmp = str_replace('&#058;',':',$tmp);
                }
                $this->new_entry[$pos] = $tmp;
            }
        }
        if($this->settings->get("saveips") == "true") {
            $this->new_entry[5] = htmlentities($_SERVER['REMOTE_ADDR'],ENT_COMPAT,CHARSET);
            $this->new_entry[6] = gethostbyaddr($this->new_entry[5]);
        }
       return true;
    }

    function checkRequest($db_name,$gbdb) {
        $mantadory = false;
        $template_new = $gbdb->template_new;
        foreach($this->inputs as $name) {
            if($name === "asta")
                continue;
            if(false === ($input = $gbdb->findTagByAttrValue("name","{GB_INPUT}".$name,$gbdb->template_new)))
                continue;
            $request = getRequestValue($_SESSION['GB_INPUT_'.$db_name][$name],"post",false);
            if($name === "name" or $name === "mail" or $name === "web") {
                if("" != $request) {
                    $mail_web = $request;
                    $request = preg_replace('/value=(["\']).*["\']/U','value=${1}'.$request.'${1}',$input);
                    $template_new = str_replace($input,$request,$template_new);
                    if($name === "mail" and strpos($input,"entry-input-error-validate-mail") > 0
                            and !$this->checkMail($mail_web)) {
                        $template_new = str_replace('entry-input-error-validate-mail','entry-input-error',$template_new);
                        $this->msg_error[] = $this->gblanguage->getLanguageValue("msg_mail");
                    }
                    if($name === "web" and strpos($input,"entry-input-error-validate-web") > 0
                            and !$this->checkWeb($mail_web)) {
                        $template_new = str_replace('entry-input-error-validate-web','entry-input-error',$template_new);
                        $this->msg_error[] = $this->gblanguage->getLanguageValue("msg_web");
                    }
                }
                if($name === "name" and strlen($this->new_entry[$this->newnr[$name]]) < 1) {
                    $template_new = str_replace('entry-input-error-'.$name,'entry-input-error',$template_new);
                    $mantadory = true;
                } elseif(strpos($input,'entry-input-error-'.$name) > 2
                        and strlen($this->new_entry[$this->newnr[$name]]) < 1) {
                    $template_new = str_replace('entry-input-error-'.$name,'entry-input-error',$template_new);
                    $mantadory = true;
                }
            }
            if($name === "entry") {
                if("" != $request) {
                    $request = str_replace(':','&#058;',$request);
                    $request = str_replace('</textarea>',$request.'</textarea>',$input);
                    $template_new = str_replace($input,$request,$template_new);
                }
                if($this->settings->get("entrymaxlength") > 0 and strlen($this->new_entry[$this->newnr[$name]]) > $this->settings->get("entrymaxlength")) {
                    $this->msg_error[] = $this->gblanguage->getLanguageValue("msg_maxlength", $this->settings->get("entrymaxlength"));
                }
                if(strlen($this->new_entry[$this->newnr[$name]]) < 1) {
                    $template_new = str_replace('entry-input-error-'.$name,'entry-input-error',$template_new);
                    $mantadory = true;
                }
            }
            if($name === "number" and false === $gbdb->to_entry_checked)
                $this->msg_error[] = $this->gblanguage->getLanguageValue("msg_no_replay");
            if($name === "privacy") {
                if($request === "privacy") {
                    $request = str_replace('name="{GB_INPUT}'.$name.'"','name="{GB_INPUT}'.$name.'" checked="checked"',$input);
                    $template_new = str_replace($input,$request,$template_new);
                } elseif($request !== "privacy") {
                    $template_new = str_replace('entry-input-error-'.$name,'entry-input-error',$template_new);
                    $this->msg_error[] = $this->gblanguage->getLanguageValue("msg_privacy");
                }
            }
            if($name === "spam") {
                // Bots anhand der Sicherheitsfrage herausfiltern
                if(($request == "") or ($request != $_SESSION['calculation_result_'.$db_name])) {
                    $template_new = str_replace('entry-input-error-'.$name,'entry-input-error',$template_new);
                    $this->msg_error[] = $this->gblanguage->getLanguageValue("msg_wrong_calcresult");
                }
            }
        }
        // Spamfilter (Sperrzeit)
        if($this->isSpam($gbdb,$this->new_entry[5])) {
            $this->msg_error[] = $this->gblanguage->getLanguageValue("msg_spam");
        }
        if($mantadory)
            $this->msg_error[] = $this->gblanguage->getLanguageValue("msg_error");
        if(count($this->msg_error) > 0) {
            $gbdb->template_new = $template_new;
            return false;
        }
        return true;
    }

    function makeRandomStr($db_name,$new = false) {
        if(!$new and isset($_SESSION['GB_INPUT_'.$db_name]) and false !== getRequestValue('submit',"post",false))
            return $_SESSION['GB_INPUT_'.$db_name];

        $_SESSION['GB_INPUT_'.$db_name] = array();
        foreach($this->inputs as $name) {
            $_SESSION['GB_INPUT_'.$db_name][$name] = $this->help_makeRandomStr($name);
        }
    }

    function help_makeRandomStr($i) {
        $xyz = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $tmp = md5(microtime()+$i);
        $tmp = str_split($tmp);
        shuffle($tmp);
        return $xyz[(rand(0,(strlen($xyz) - 1)))].implode("",$tmp);
    }

    function isSpam($gbdb,$ip) {
        if($ip == ""
                or $this->settings->get("saveips") !== "true"
                or strlen($this->settings->get("iplocktime")) == 0)
            return false;

        # alle Einträge Suchen mit der ip in der Spalte 6
        $find = $gbdb->findInEntryRow($ip,6);
        if(count($find) < 1)
            return false;

        # den neusten Eintrag Suchen
        $max = $find[(count($find)-1)];
        foreach($find as $pos => $entry) {
            if($entry[0] > $max[0])
                $max = $entry;
        }

        if(($max[0] + $this->settings->get("iplocktime")) >= time())
            return true;
        return false;
    }

    function setRandomSpamTask($gbdb,$db_name) {
        if(!isset($this->spamprotcalcs))
            $this->spamprotcalcs = $this->spamprotcalcsToArray($this->settings->get('spamprotcalcs'));
        if(count($this->spamprotcalcs) < 1)
            return;
        $randnum = rand(0, count($this->spamprotcalcs)-1);
        $tmp = array_keys($this->spamprotcalcs);
        # keine gleiche frage benutzen
        if(isset($_SESSION['calculation_result_'.$db_name])
                and $_SESSION['calculation_result_'.$db_name] === $this->spamprotcalcs[$tmp[$randnum]]) {
            $this->setRandomSpamTask($gbdb,$db_name);
            return;
        }
        $_SESSION['calculation_result_'.$db_name] = $this->spamprotcalcs[$tmp[$randnum]];
        $gbdb->template_new = str_replace('{SPAM_TASK}',$tmp[$randnum],$gbdb->template_new);
    }

    function spamprotcalcsToArray($spamprotcalcs) {
        $lines = explode("<br />",$spamprotcalcs);
        $spamarray = array();
        foreach ($lines as $line) {
            if(preg_match("/^#/",$line) || preg_match("/^\s*$/",$line)) {
                continue;
            }
            if(preg_match("/^(.*)=(.*)/",$line,$matches)) {
                $spamarray[trim($matches[1])] = trim($matches[2]);
            }
        }
        return $spamarray;
    }

    function checkWeb($str) {
        if($str == "")
            return true;
        if(!stristr($str,"://"))
            $str = "http://".$str;
        # Regular Expression for URL validation
        # Author: Diego Perini
        return preg_match('%^(?:(?:https?)://)(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?:/[^\s]*)?$%iuS',$str);
    }

    function checkMail($str) {
        if($str == "")
            return true;
        return preg_match("/^.+@.+\..+$/", $str);
    }

    function getConfig() {
        $tmpl_db = '<div id="mozilo-gb-db-list" style="margin-left:2em;color:red;font-weight:bold;">'.$this->adminLang->getLanguageHtml('db_not_find').'</div>';
        $dbs = array();
        if(is_dir($this->PLUGIN_SELF_DIR."data/") and false !== ($dir = scandir($this->PLUGIN_SELF_DIR."data/"))) {
            foreach($dir as $file) {
                if($file[0] != "." and substr($file,-7) === "_db.php") {
                    $dbs[] = substr($file,0,-7);
                }
            }
            if(count($dbs) > 0)
                $tmpl_db = '<div id="mozilo-gb-db-list" style="margin-left:2em;">'.implode(", ",$dbs).'</div>';
        }
        $tmpl_layouts = '<div style="margin-left:2em;color:red;font-weight:bold;">'.$this->adminLang->getLanguageHtml('tmpl_not_find').'</div>';
        $layouts = array();
        if(is_dir($this->PLUGIN_SELF_DIR."layouts/") and false !== ($dir = scandir($this->PLUGIN_SELF_DIR."layouts/"))) {
            foreach($dir as $file) {
                if($file[0] != "." and is_dir($this->PLUGIN_SELF_DIR."layouts/".$file)) {
                    $layouts[] .= $file;
                }
            }
            if(count($layouts) > 0)
                $tmpl_layouts = '<div style="margin-left:2em;">'.implode(", ",$layouts).'</div>';
        }

        $config = array();
        $config["--admin~~"] = array(
            "buttontext" => $this->adminLang->getLanguageValue("button_admin"),
            "description" => $this->adminLang->getLanguageValue("text_admin"),
            "datei_admin" => "admin.php"
            );

        $config['spamprotcalcs'] = array(
            "type" => "textarea",
            "rows" => "7",
            "description" => $this->adminLang->getLanguageValue("spamprotcalcs"),
            "template" => $this->adminLang->getLanguageValue("available").'<br />'
                    .$this->adminLang->getLanguageValue("available_db").$tmpl_db
                    .$this->adminLang->getLanguageValue("available_tmp").$tmpl_layouts.'</div>
                </li>
                <li class="mo-in-ul-li mo-inline ui-widget-content ui-corner-all ui-helper-clearfix">
                    <div class="mo-in-li-l">{spamprotcalcs_description}</div>
                    <div class="mo-in-li-r">{spamprotcalcs_textarea}'
        );

        $config['newentryemail']  = array(
            "type" => "text",
            "description" => $this->adminLang->getLanguageValue("newentryemail"),
            "maxlength" => "255",
            "regex" => "/^(.+@.+\..+)?$/",
            "regex_error" => $this->adminLang->getLanguageValue("regex_error_mail")
            );

        $config['badwords']  = array(
            "type" => "text",
            "description" => $this->adminLang->getLanguageValue("badwords"),
            "maxlength" => "255",
            "template" => '{badwords_description}<br />{badwords_text}'
            );
        $config['goodword']  = array(
            "type" => "text",
            "description" => $this->adminLang->getLanguageValue("goodword"),
            "maxlength" => "100"
            );
        $config['botwords']  = array(
            "type" => "text",
            "description" => $this->adminLang->getLanguageValue("botwords"),
            "maxlength" => "255",
            "template" => '{botwords_description}<br />{botwords_text}'
            );
        $config['entriesperpage']  = array(
            "type" => "text",
            "description" => $this->adminLang->getLanguageValue("entriesperpage"),
            "maxlength" => "6",
            "size" => "6",
            "regex" => "/^[\d]+?$/",
            "regex_error" => $this->adminLang->getLanguageValue("regex_error_nr")
            );
        $config['entrymaxlength']  = array(
            "type" => "text",
            "description" => $this->adminLang->getLanguageValue("entrymaxlength"),
            "maxlength" => "6",
            "size" => "6",
            "regex" => "/^[\d]+?$/",
            "regex_error" => $this->adminLang->getLanguageValue("regex_error_nr")
            );
        $config['iplocktime']  = array(
            "type" => "text",
            "description" => $this->adminLang->getLanguageValue("iplocktime"),
            "maxlength" => "6",
            "size" => "6",
            "regex" => "/^[\d]+?$/",
            "regex_error" => $this->adminLang->getLanguageValue("regex_error_nr")
            );
        $config['saveips'] = array(
            "type" => "checkbox",
            "description" => $this->adminLang->getLanguageValue("saveips")
            );
        $config['showsmileys'] = array(
            "type" => "checkbox",
            "description" => $this->adminLang->getLanguageValue("showsmileys")
            );
        return $config;
    }

    function getInfo() {
        global $ADMIN_CONF;
        $this->adminLang = new Language($this->PLUGIN_SELF_DIR."lang/admin_".$ADMIN_CONF->get("language").".txt");

        $lang = "deDE";
        if(is_file($this->PLUGIN_SELF_DIR."lang/layouts_".$ADMIN_CONF->get("language").".htm"))
            $lang = $ADMIN_CONF->get("language");

        $info = array(
            // Plugin-Name
            $this->adminLang->getLanguageValue("info_name","19"),
            // Plugin-Version
            "2.0",
            // Kurzbeschreibung
            $this->adminLang->getLanguageValue("info_description",$lang,$this->PLUGIN_SELF_URL."lang/"),
            // Name des Autors
           "stefanbe",
            // Download-URL
            array("http://www.mozilo.de/forum/index.php?action=media","Templates und Plugins"),
            # Platzhalter => Kurtzbeschreibung
            array($this->adminLang->getLanguageValue("info_description1") => $this->adminLang->getLanguageValue("info_description2"))
            );

        return $info;
    }
}
?>