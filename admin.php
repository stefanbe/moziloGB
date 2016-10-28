<?php if(!defined('IS_ADMIN') or !IS_ADMIN) die();

if(!is_dir(PLUGIN_DIR_REL."moziloGB/data/")) {
    $error = mkdirMulti(PLUGIN_DIR_REL."moziloGB/data/");
    if($error !== true)
        return returnMessage(false, $error);
}

global $ADMIN_CONF;
if(is_file(PLUGIN_DIR_REL."moziloGB/lang/".$ADMIN_CONF->get("language").".txt"))
    $gblanguage = new Language(PLUGIN_DIR_REL."moziloGB/lang/".$ADMIN_CONF->get("language").".txt");
else
    die();

if(is_file(PLUGIN_DIR_REL."moziloGB/ShowEntries.php"))
    require_once(PLUGIN_DIR_REL."moziloGB/ShowEntries.php");
else
    die();

if(is_file(PLUGIN_DIR_REL."moziloGB/js_admin.js"))
    $PLUGIN_ADMIN_ADD_HEAD[] = '<script type="text/javascript" src="'.URL_BASE.PLUGIN_DIR_NAME.'/moziloGB/js_admin.js"></script>';
else
    die();

$PLUGIN_ADMIN_ADD_HEAD[] = '<script type="text/javascript">'
                                .'var admin_js_del_backup_text = "'.$gblanguage->getLanguageValue('admin_js_del_backup_text').'";'
                                .'var admin_js_del_entries_text = "'.$gblanguage->getLanguageValue('admin_js_del_entries_text').'";'
                        .'</script>';

class moziloGBAdmin extends ShowEntries {

    private $db_name_array;
    private $maintenance_array;
    private $backup_array;
    private $import_array;
    private $curent_db;
    private $is_post = false;
    private $gbmessages = "";

    function __construct($settings,$gblanguage) {

        $this->makeParas();

        $this->curent_db = false;
        if(count($this->db_name_array) > 0)
            $this->curent_db = $this->db_name_array[0];
        if(false !== ($tmp = getRequestValue("db","get")))
            $this->curent_db = $tmp;

        parent::__construct($this->curent_db,"",$settings,$gblanguage);

        # die gehen ohne maintenance mode
        if(false !== getRequestValue('curent_db',"post")) {
            if(false !== getRequestValue('newdbbutton',"post",false)) {
                $this->newDb(getRequestValue('newdb',"post",false));
            } elseif(false !== getRequestValue('importbutton',"post",false)) {
                $this->importDb(getRequestValue('importfile',"post",false),getRequestValue('importnewname',"post",false));
            } elseif(false !== getRequestValue('setmaintenance',"post",false)) {
                touch(PLUGIN_DIR_REL."moziloGB/data/".$this->curent_db."_db_maintenance.php");
                $this->maintenance_array[$this->curent_db] = true;
            } elseif(false !== getRequestValue('relmaintenance',"post",false)) {
                unlink(PLUGIN_DIR_REL."moziloGB/data/".$this->curent_db."_db_maintenance.php");
                $this->maintenance_array[$this->curent_db] = false;
            }
        }
        # die gehen nur mit maintenance mode
        if($this->is_maintenance and false !== getRequestValue('curent_db',"post")) {

            if(false !== getRequestValue('settingbutton',"post",false)) {
                $this->setSettings();
            } elseif(getRequestValue('deleteconfirm',"post") === "true") {
                $this->deleteDB();
            } elseif(false !== getRequestValue('backupbutton',"post",false)) {
                $this->makeBackup();
            } elseif(false !== getRequestValue('usebackupbutton',"post",false)) {
                $this->useBackup(getRequestValue('backupfile',"post"));
            } elseif(false !== getRequestValue('delbackupbutton',"post",false)) {
                $this->delBackup(getRequestValue('backupfile',"post"));
            } elseif(false !== getRequestValue('savecommentsbutton',"post",false)) {
                $this->saveComments();
            } elseif(false !== getRequestValue('deleteconfirmentry',"post",false)) {
                $this->deletEntrie();
            } elseif(false !== getRequestValue('movebutton',"post",false)) {
                if(false !== ($tmp1 = $this->getCleanNumber(getRequestValue('movefrom',"post"),true)) and false !== ($tmp2 = $this->getCleanNumber(getRequestValue('moveto',"post"),true))) {
                    $success = $this->moveEntryTo($tmp1,$tmp2);
                    if(!$success) {
                        $this->gbmessages .= returnMessage(false,$this->gblanguage->getLanguageValue('admin_entries_move_error',getRequestValue('movefrom',"post",false),getRequestValue('moveto',"post",false)));
                    }
                }
            }
        }
    }

    function getAdminContent() {
        # post anfrage
        if(false !== getRequestValue('curent_db',"post")) {
            # es gibt nee miteilung
            if(strlen($this->gbmessages) > 10)
                $_SESSION['gbmessages'] = $this->gbmessages;
            $db = '';
            if($this->curent_db)
                $db = '&db='.$this->curent_db;
            $url = $_SERVER['HTTP_HOST'].str_replace("&amp;","&",PLUGINADMIN_GET_URL).$db;
            # damit beim browserreload nicht wieder die daten gesendet werden senden wir eine get anfrage
            if(defined("HTTP"))
                header("Location: ".HTTP.$url);
            else
                header("Location: http://$url");
            exit;
        }
        # es gab nee miteilung jetzt ausgeben da keine post anfrage
        if(isset($_SESSION['gbmessages']) and strlen($_SESSION['gbmessages']) > 10) {
            global $message;
            $message .= $_SESSION['gbmessages'];
            unset($_SESSION['gbmessages']);
        }

        $html = '<form name="newentry_'.$this->curent_db.'" action="'.URL_BASE.ADMIN_DIR_NAME.'/index.php?db='.$this->curent_db.'" method="POST">';
        $html .= $this->getAdminMenue();
        $html .= $this->getAdminDBs();
        $html .= "</form>";
        if(count($this->db_name_array) < 1)
            $html .= '<span id="mozilo-admin-gb-db-list" style="display:none;">'.$this->gblanguage->getLanguageHtml('db_not_find')."</span>";
        else
            $html .= '<span id="mozilo-admin-gb-db-list" style="display:none;">'.implode(", ",$this->db_name_array)."</span>";
        return $html;
    }

    function getAdminMenue() {
        $input_disable = "";
        if(!$this->save) {
            $this->gbmessages .= returnMessage(true,$this->gblanguage->getLanguageValue('admin_db_save_error',$this->curent_db."_db.php"));
            $input_disable = ' disabled="disabled"';
        }
        if(!$this->is_maintenance)
            $input_disable = ' disabled="disabled"';

        $html = '<input type="hidden" name="pluginadmin" value="'.PLUGINADMIN.'" />'
            .'<input type="hidden" name="action" value="'.ACTION.'" />'
            .'<input type="hidden" name="curent_db" value="'.$this->curent_db.'" />'
            .'<div id="entry-admin-kopf" class="ui-widget ui-widget-content ui-corner-all mo-margin-bottom mo-help-box">'
            .'<p class="entry-admin-kopf-first-p">'
            .$this->gblanguage->getLanguageHtml('admin_newdb_text')
            .'<input class="entry-admin-in-newdb entry-admin-margin-left" type="text" name="newdb" value="" />'
            .'<input class="entry-admin-margin-left" type="submit" name="newdbbutton" value="'.$this->gblanguage->getLanguageHtml('admin_newdb_button').'" />'
            .'<input class="entry-admin-margin-left" type="submit" name="deletedbbutton" value="'.$this->gblanguage->getLanguageHtml('admin_db_button_delete').'"'.$input_disable.' />';
        if(count($this->db_name_array) > 0) {
            $m_css = " entry-admin-button-maintenance-off";
            if(false !== array_search(true,$this->maintenance_array))
                $m_css = " entry-admin-button-maintenance-on";
            $html .= '<span class="ui-corner-all'.$m_css.'">';
            if($this->is_maintenance)
                $html .= '<input type="submit" name="relmaintenance" value="'.$this->gblanguage->getLanguageHtml('admin_maintenance_button_rel').'" />';
            else
                $html .= '<input type="submit" name="setmaintenance" value="'.$this->gblanguage->getLanguageHtml('admin_maintenance_button_set').'" />';
            $html .= '</span>';
        }
        $html .= '</p>';

        if(count($this->import_array) > 0) {
            $html .= '<p>'
                .$this->gblanguage->getLanguageHtml('admin_import_text1').' <select class="entry-admin-margin-right" name="importfile">';
            foreach($this->import_array as $file) {
                $html .= '<option value="'.$file.'">'.$file.'</option>';
            }
            $html .= '</select>'
                .$this->gblanguage->getLanguageHtml('admin_import_text2').' <input class="entry-admin-in-newdb" type="text" name="importnewname" value="" />'
                .'<input class="entry-admin-margin-left" type="submit" name="importbutton" value="'.$this->gblanguage->getLanguageHtml('admin_import_button').'" /> '
                .'</p>';
        }

        if(count($this->db_name_array) > 0) {
            $html .= '<p>';
            $html .= $this->gblanguage->getLanguageHtml('admin_backup_text1').'<input class="entry-admin-margin-right" type="submit" name="backupbutton" value="'.$this->gblanguage->getLanguageHtml('admin_backup_button').'"'.$input_disable.' />';
            if(isset($this->backup_array[$this->curent_db])) {
                rsort($this->backup_array[$this->curent_db]);
                $html .= $this->gblanguage->getLanguageHtml('admin_backup_text2').'<select name="backupfile"'.$input_disable.'>'
                    .'<option value="false">'.$this->gblanguage->getLanguageHtml('admin_backup_text1').'</option>';
                foreach($this->backup_array[$this->curent_db] as $file) {
                    $html .= '<option value="'.$file.'">'.date('Y-m-d&\n\b\s\p;&\n\b\s\p;&\n\b\s\p;H:i:s',substr($file,-14,10)).'</option>';
                }
                $html .= '</select>'
                    .'<input class="entry-admin-margin-left" type="submit" name="usebackupbutton" value="'.$this->gblanguage->getLanguageHtml('admin_backup_button_use').'"'.$input_disable.' /> '
                    .'<input class="entry-admin-margin-left" type="submit" name="delbackupbutton" value="'.$this->gblanguage->getLanguageHtml('admin_backup_button_del').'"'.$input_disable.' /> ';
            }
            $html .= '</p>';

            $html .= '<p>';
            $check_subs = '';
            $check_comment = '';
            $check_no_response = '';
            if($this->entry_use_sub)
                $check_subs = ' checked="checked"';
            if($this->entry_use_comment)
                $check_comment = ' checked="checked"';
            if($this->entry_no_response)
                $check_no_response = ' checked="checked"';

            $html .= $this->gblanguage->getLanguageHtml('admin_settings_text')
                .'<input id="id-setting-subs" class="entry-admin-checkbox" type="checkbox" name="setting_subs" value="true"'.$input_disable.''.$check_subs.' />'
                .'<label for="id-setting-subs" class="mo-bold">'.$this->gblanguage->getLanguageHtml('admin_settings_sub').'</label>'
                .'<input id="id-setting-comment" class="entry-admin-checkbox entry-admin-margin-left" type="checkbox" name="setting_comment" value="true"'.$input_disable.''.$check_comment.' />'
                .'<label for="id-setting-comment" class="mo-bold">'.$this->gblanguage->getLanguageHtml('admin_settings_comment').'</label>'
                .'<input id="id-setting-no_response" class="entry-admin-checkbox entry-admin-margin-left" type="checkbox" name="setting_no_response" value="true"'.$input_disable.''.$check_no_response.' />'
                .'<label for="id-setting-no_response" class="mo-bold">'.$this->gblanguage->getLanguageHtml('admin_settings_no_response').' </label>'
                .'<input class="entry-admin-margin-left" type="submit" name="settingbutton" value="'.$this->gblanguage->getLanguageHtml('admin_settings_button').'"'.$input_disable.' /> ';
            $html .= '</p>';
            $html .= '<p>'
                .'<input type="submit" name="deleteentriesbutton" value="'.$this->gblanguage->getLanguageHtml('admin_entries_button_del').'"'.$input_disable.' /> ';
            if($this->entry_use_comment)
                $html .= '<input class="entry-admin-margin-left" type="submit" name="savecommentsbutton" value="'.$this->gblanguage->getLanguageHtml('admin_comments_button_save').'"'.$input_disable.' /> ';

            $html .= '</p>';
            if($this->entry_use_sub) {
                $html .= '<p>'
                    .$this->gblanguage->getLanguageHtml('admin_entries_text_move_from')
                    .' <input class="entry-admin-in-text entry-admin-margin-right" type="text" name="movefrom" value=""'.$input_disable.' /> '
                    .$this->gblanguage->getLanguageHtml('admin_entries_text_move_to')
                    .' <input class="entry-admin-in-text entry-admin-margin-right" type="text" name="moveto" value=""'.$input_disable.' />'
                    .'<input type="submit" name="movebutton" value="'.$this->gblanguage->getLanguageHtml('admin_entries_button_move').'"'.$input_disable.' /> '
                    .'</p>';
            }
        }
        $html .= '</div>';
        return $html;
    }

    function getAdminDBs() {
        if(count($this->db_name_array) < 1)
            $html = '<span class="entry-message-box entry-new-error">'.$this->gblanguage->getLanguageValue('db_not_find')."</span>";
        else {
            $html = '<div id="entry-admin-content" class="ui-tabs ui-widget ui-widget-content ui-corner-all mo-ui-tabs">';
            $html .= $this->dbSubMenu();
            $html .= '<div class="mo-ui-tabs-panel ui-widget-content ui-corner-bottom mo-no-border-top">';
            if(!$this->is_maintenance)
                $html .= '<span class="entry-message-box entry-new-error">'.$this->gblanguage->getLanguageValue('admin_maintenance_text')."</span>";
            $html .= $this->get_template();
            $html .= '</div>';
            $html .= '</div>';
        }
        return $html;
    }

    function dbSubMenu() {
        $min_height = "";
        if(count($this->db_name_array) < 1)
            $min_height = ' style="height:2em;"';
        $submenu = '<ul class="mo-menu-tabs ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-top"'.$min_height.'>';
        foreach($this->db_name_array as $subnav) {
            $mode = "";
            if($this->maintenance_array[$subnav])
                $mode = ' entry-admin-tabs-maintenance';

            $cssaktiv = " mo-ui-state-hover";
            if($this->curent_db == $subnav)
                $cssaktiv = " ui-tabs-selected ui-state-active";
            $submenu .= '<li class="ui-state-default ui-corner-top'.$cssaktiv.'">'.'<a href="'.PLUGINADMIN_GET_URL.'&amp;db='.$subnav.'" class="entry-admin-tabs '.$mode.'">'.$subnav.'</a>'.'</li>';
        }
        $submenu .= '</ul>';
        return $submenu;
    }

    function setSettings() {
        $this->entry_use_sub = false;
        $this->entry_use_comment = false;
        $this->entry_no_response = false;
        if(getRequestValue('setting_subs',"post") === "true")
            $this->entry_use_sub = true;
        if(getRequestValue('setting_comment',"post") === "true")
            $this->entry_use_comment = true;
        if(getRequestValue('setting_no_response',"post") === "true")
            $this->entry_no_response = true;
        $this->changeSettings($this->entry_use_sub,$this->entry_use_comment,$this->entry_no_response);
    }

    function saveComments() {
        $error_message = "";
        foreach(getRequestValue('comment',"post") as $pos => $comment) {
            if(false !== ($pos = $this->getCleanNumber($pos))) {
                global $specialchars;
                $comment = trim($specialchars->rebuildSpecialChars($comment,false,false));
                $comment = str_replace(array("\r\n","\r","\n"),"<br />",$comment);
                $comment = str_replace('&#058;',':',$comment);
                $comment = $this->badWordFilter($comment);
                $success = $this->changeEntryRowValue($pos, $comment);
            } else
                $success = false;

            if($success === false)
                $error_message .= "<br />".substr($pos,0,(strpos($pos,"-en")));
        }
        if($error_message)
            $this->gbmessages .= returnMessage(false,$this->gblanguage->getLanguageHtml('admin_comment_save_error').$error_message);
    }

    function deletEntrie() {
        if(false === ($tmp_e = getRequestValue('delete',"post",false)))
            return;
        sort($tmp_e);
        $entrie_del = array();
        foreach($tmp_e as $nr) {
            if(false === ($nr = $this->getCleanNumber($nr)))
                continue;
            $entrie_del[] = $nr;
        }
        if(count($entrie_del) > 0) {
            $deleted = $this->deleteEntrys($entrie_del);
            if(count($deleted) > 0)
                $this->gbmessages .= returnMessage(false,$this->gblanguage->getLanguageHtml('admin_entries_del_error')."<br />".implode("<br />",$deleted));
        }
    }

    function makeParas() {
        $this->db_name_array = array();
        $this->maintenance_array = array();
        $this->backup_array = array();
        $this->import_array = array();
        if(false !== ($dir = scandir(PLUGIN_DIR_REL."moziloGB/data/"))) {
            foreach($dir as $file) {
                if($file[0] != "." and substr($file,-4) === ".php") {
                    $tmp = substr($file,0,-7);
                    if(strpos($file,"_db.php") !== false) {
                        $this->db_name_array[] = $tmp;
                        if(is_file(PLUGIN_DIR_REL."moziloGB/data/".$tmp."_db_maintenance.php"))
                            $this->maintenance_array[$tmp] = true;
                        else
                            $this->maintenance_array[$tmp] = false;
                    }
                    if(is_numeric(substr($file,-14,10))) {
                        $this->backup_array[substr($file,0,-18)][] = $file;
                    }
                }
            }
        }
        if(is_file(PLUGIN_DIR_REL."moziloGB/importGB.php")
                and is_dir(PLUGIN_DIR_REL."moziloGB/import/")
                and false !== ($dir = scandir(PLUGIN_DIR_REL."moziloGB/import/"))) {
            foreach($dir as $file) {
                if($file[0] == ".")
                    continue;
                elseif(substr($file,-4) === ".txt") {
                    if(filesize(PLUGIN_DIR_REL."moziloGB/import/".$file) > 20 and false !== ($tmp = file_get_contents(PLUGIN_DIR_REL."moziloGB/import/".$file, NULL, NULL, 0, 20))) {
                        $tmp = trim($tmp);
                        if(is_numeric(substr($tmp,0,10)))
                            $this->import_array[] = $file;
                    }
                    clearstatcache();
                } elseif(substr($file,-8) === ".dat.php") {
                    $this->import_array[] = $file;
                }
            }
        }
    }

    function newDb($new_db) {
        if(false !== $new_db and strlen($new_db) > 1) {
            $new_db = str_replace(" ","_",$new_db);
            $new_db = preg_replace('/[^a-zA-Z0-9\-\_]/',"",$new_db);
            $new_db_dir = PLUGIN_DIR_REL."moziloGB/data/".$new_db."_db.php";
            if(!is_file($new_db_dir)) {
                global $page_protect;
                mo_file_put_contents($new_db_dir,$page_protect.serialize(array("subs" => false,"comment" => false,"no_response" => false,"data" => array())));
                $this->curent_db = $new_db;
                return $new_db;
            } else {
                $this->gbmessages .= returnMessage(false,$this->gblanguage->getLanguageValue('admin_newdb_error',$new_db));
            }
        }
        return false;
    }

    function importDb($file_name,$name) {
        $file = PLUGIN_DIR_REL."moziloGB/import/".$file_name;
        if(false !== ($name = $this->newDb($name))) {
            $new_file = PLUGIN_DIR_REL."moziloGB/data/".$name."_db.php";
            if(is_file($file)) {
                if(false === ($content = file($file))) {
                    $this->gbmessages .= returnMessage(false,$this->gblanguage->getLanguageValue('admin_import_error'));
                    return false;
                }
                $import_art = "mozilo";
                if($file_name == "gb.dat.php")
                    $import_art = "onsite";
                require_once(PLUGIN_DIR_REL."moziloGB/importGB.php");
                $new_db = array();
                foreach ($content as $line) {
                    if(strlen($line) < 7)
                        continue;
                    if($import_art == "mozilo") {
                        if(false !== ($tmp = convertGB($line)))
                            $new_db[$tmp['en'][0]] = $tmp;
                        else {
                            $this->gbmessages .= returnMessage(false,$this->gblanguage->getLanguageValue('admin_import_error'));
                            return false;
                        }
                    } elseif($import_art == "onsite") {
                        if(false !== ($tmp = dat_convertGB($line)))
                            $new_db[$tmp['en'][0]] = $tmp;
                        else {
                            $this->gbmessages .= returnMessage(false,$this->gblanguage->getLanguageValue('admin_import_error'));
                            return false;
                        }
                    }
                }
                ksort($new_db);
                $new_db = array_values($new_db);
                krsort($new_db);
                global $page_protect;
                if(false !== mo_file_put_contents($new_file,$page_protect.serialize(array("subs" => false,"comment" => true,"no_response" => false,"data" => $new_db)))) {
                    if(!@unlink($file)) {
                        $this->gbmessages .= returnMessage(false,$this->gblanguage->getLanguageValue('admin_import_del_error',$file_name));
                    }
                    $this->curent_db = $name;
                } else {
                    $this->gbmessages .= returnMessage(false,$this->gblanguage->getLanguageValue('admin_import_save_error',$name));
                }
            }
        }
    }

    function delBackup($del_db) {
        if(false !== $del_db) {
            $del_file = PLUGIN_DIR_REL."moziloGB/data/".$del_db;
            if(is_file($del_file) and false !== ($tmp = array_search($del_db,$this->backup_array[$this->curent_db]))) {
                unlink($del_file);
            }
        }
    }

    function makeBackup() {
        if(false !== $this->curent_db) {
            $time = time();
            $curen_file = PLUGIN_DIR_REL."moziloGB/data/".$this->curent_db."_db.php";
            $backup_file = PLUGIN_DIR_REL."moziloGB/data/".$this->curent_db."_db_".$time.".php";
            if(is_file($curen_file) and !is_file($backup_file)) {
                copy($curen_file,$backup_file);
            }
        }
    }

    function useBackup($use_db) {
        if(false !== $use_db and false !== $this->curent_db) {
            $time = time();
            $curen_file = PLUGIN_DIR_REL."moziloGB/data/".$this->curent_db."_db.php";
            $newbackup_file = PLUGIN_DIR_REL."moziloGB/data/".$this->curent_db."_db_".$time.".php";
            $backup_file = PLUGIN_DIR_REL."moziloGB/data/".$use_db;
            if(is_file($curen_file) and is_file($backup_file) and !is_file($newbackup_file)) {
                copy($curen_file,$newbackup_file);
                copy($backup_file,$curen_file);
            }
        }
    }

    function deleteDB() {
        $dir = PLUGIN_DIR_REL."moziloGB/data/";
        $del = array();
        if(isset($this->backup_array[$this->curent_db])) {
            $del = $this->backup_array[$this->curent_db];
        }
        $del[] = $this->curent_db."_db.php";
        if(is_file($dir.$this->curent_db."_db_maintenance.php"))
            $del[] = $this->curent_db."_db_maintenance.php";

        foreach($del as $pos => $file) {
            if(is_file($dir.$file) and @unlink($dir.$file))
                unset($del[$pos]);
            # die actuelle datenbank konte nicht gelöscht werden damit die
            # ???maintenance.php nicht gelöscht wird brechen wir ab
            elseif($file == $this->curent_db."_db.php" and is_file($dir.$file))
                break;
        }

        if(isset($del) and count($del) > 0) {
            $this->gbmessages .= returnMessage(false,$this->gblanguage->getLanguageValue('admin_db_delete_error')."<br /><br /><b>".implode("<br />",$del)."</b>");
        }

        if(!is_file($dir.$this->curent_db."_db.php")) {
            $this->curent_db = false;
        }
    }
}

$moziloGBAdmin = new moziloGBAdmin($plugin->settings,$gblanguage);
return $moziloGBAdmin->getAdminContent();

?>