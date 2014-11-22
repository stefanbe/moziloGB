<?php if(!defined('IS_CMS')) die();

# ein trick wenn ein string z.B. 13-1-en-5 an eine function
# übergeben wird und in der function ein explode darauf angewendet wir
# ist das en aus dem string im array eine constante
define("en","en");

define("GB_DELIMITER","-");

class DatabaseGB {

    private $filename;
    private $entries;
    private $wait = 100;# 10 = 1sec.
    public $is_admin;
    public $save = true;
    public $db_error_status;

    private $use_sub = false;
    private $use_comment = false;
    private $no_response = false;
    public $entry_use_sub;
    public $entry_use_comment;
    public $entry_no_response;
    public $is_maintenance = false;

    function DatabaseGB_init($filename) {
        $this->db_error_status = false;
        $this->filename = PLUGIN_DIR_REL."moziloGB/data/".$filename."_db.php";
        if(!is_writable($this->filename))
            $this->save = false;
        $this->entries = array();
        if(is_file(PLUGIN_DIR_REL."moziloGB/data/".$filename."_db_maintenance.php")) {
            $this->is_maintenance = true;
            if(!$this->is_admin)
                $this->save = false;
        }
        if((is_file($this->filename) or is_file($this->filename.".lock"))) {
            if(!$this->loadData())
                $this->db_error_status = "db_is_locket";
        } else
            $this->db_error_status = "db_not_find";
    }

    private function lockFile($lock) {
        $notexit = $this->wait;
        $status = false;
        if(!$lock) {
            if(is_file($this->filename.".lock") and rename($this->filename.".lock",$this->filename)) {
                $status = true;
            }
        } else {
            while($notexit > 0) {
                $notexit--;
                if(is_file($this->filename) and rename($this->filename,$this->filename.".lock")) {
                    $status = true;
                    $this->loadData(".lock");
                    break;
                }
                usleep(100000); # 1000000 = 1 sec.
            }
        }
        return $status;
    }

#!!!!!!!! das file locken noch mal prüfen

    # aus der datei die einträge holen und das array bilden
    private function loadData($lock = "") {
        $this->entries = array();
        $notexit = $this->wait;
        $status = false;
        while($notexit > 0) {
            $notexit--;
            if(is_file($this->filename.$lock)) {
                global $page_protect_search;
                $entries = file_get_contents($this->filename.$lock);
                $entries = str_replace($page_protect_search,"",$entries);
                $entries = trim($entries);
                $entries = unserialize($entries);
                $this->use_sub = $entries["subs"];
                $this->use_comment = $entries["comment"];
                $this->no_response = $entries["no_response"];
                $this->entries = $entries["data"];
                $status = true;
                break;
            }
            usleep(100000); # 1000000 = 1 sec.
        }
        $this->entry_use_sub = $this->use_sub;
        $this->entry_use_comment = $this->use_comment;
        $this->entry_no_response = $this->no_response;
        if($this->is_maintenance and !$this->is_admin)
            $this->entry_no_response = true;
        return $status;
    }

    # das komlette array mit den einträgen speichern
    private function saveData() {
        if(!$this->save)
            return;
        $lock = ".lock";
        if($this->is_admin)
            $lock = "";
        if(is_file($this->filename.$lock)) {
            global $page_protect;
            $entries = serialize(array("subs" => $this->use_sub,"comment" => $this->use_comment,"no_response" => $this->no_response,"data" => $this->entries));
            file_put_contents($this->filename.$lock,$page_protect.$entries,LOCK_EX);
            $this->loadData($lock);
        }
    }

    function changeSettings($sub,$comment,$no_response) {
        if(!$this->is_admin)
            return;
        $this->use_sub = $sub;
        $this->use_comment = $comment;
        $this->no_response = $no_response;
        $this->saveData();
    }

    # Sucht in einer Eintrags Spalte ($row) einen Begriff ($search)
    # return ist ein array mit den Einträgen
    function findInEntryRow($search,$row,$entries = false,$find = array()) {
        if(!$entries)
            $entries = $this->entries;
        foreach($entries as $pos => $tmp) {
            if(isset($entries[$pos]["en"][$row]) and strpos($entries[$pos]["en"][$row],$search) !== false)
                $find[] = $entries[$pos]["en"];
            if($pos != "en" and count($entries[$pos]) > 1)
                $find = $this->findInEntryRow($search,$row,$entries[$pos],$find);
        }
        return $find;
    }
    # gibt nur die Eintragsnummern der ersten Ebene zurück
    # wird für die Erstelung Einträge pro Seite gebraucht
    function returnEntriesNumberAsArray() {
        if(count($this->entries) < 1)
            return array();
        return array_combine(array_keys($this->entries),array_keys($this->entries));
    }

    function getEntriesPages($page_activ,$entriesperpage) {
        # keine events
        if(count($this->entries) < 1)
            return false;
        $tmp = array_combine(array_keys($this->entries),array_keys($this->entries));

        // set $entriesperpage to max if "0" given
        if($entriesperpage == 0)
            $entriesperpage = count($tmp);

        $max_pages = ceil(count($tmp) / $entriesperpage);

        if(false === $page_activ or !is_numeric($page_activ) or $page_activ > $max_pages)
            $page_activ = 1;

        $pages = array_fill(1, $max_pages, false);
        $pages[$page_activ] = array_slice($tmp,($entriesperpage * ($page_activ - 1)) , $entriesperpage, true);
        return $pages;
    }

    # gibt die Anzahl aller Einträge zurück
    function countEntries($entries = false,$count = 0) {
        if(!$entries)
            $entries = $this->entries;
        foreach($entries as $pos => $tmp) {
            if(isset($tmp["en"])) {
                unset($tmp["en"]);
                $count++;
            }
            if(count($tmp) > 0)
                $count = $this->countEntries($tmp,$count);
        }
        return $count;
    }

    # fügt einen eintrag hinzu
    function addEntry($entryarray,$to_entry = false) {
        if(!$this->save)
            return false;
        if($this->lockFile(true)) {
            array_unshift($entryarray,time());
            $new_pos = false;
            $to_entry = str_replace(GB_DELIMITER,"][",$to_entry);
            # das ist in einer sub ebene
            if(strpos($to_entry,"][") !== false or is_numeric($to_entry)) {
                eval('array_push($this->entries['.$to_entry.'], array("en" => $entryarray));');
                $new_pos = $to_entry.GB_DELIMITER.eval('end($this->entries['.$to_entry.']); return key($this->entries['.$to_entry.']);');
                eval('krsort($this->entries['.$to_entry.'],SORT_NUMERIC);');

            # das ist die ersten ebene
            } elseif($to_entry === "new") {
                array_push($this->entries, array("en" => $entryarray));
                end($this->entries);
                $new_pos = key($this->entries);
                krsort($this->entries,SORT_NUMERIC);
            } else {
                $this->lockFile(false);
                return false;
            }
            $this->saveData();
            $this->lockFile(false);
            return str_replace("][",GB_DELIMITER,$new_pos);
        }
        return false;
    }

    # gibt einen entrag zurück inkl. der subs als array
    function getEntryArray($number) {
        # eintrag aus einer sub ebene
        if(strpos($number,GB_DELIMITER) !== false)
            return eval('return $this->entries['.str_replace(GB_DELIMITER,"][",$number).'];');
        # eintrag aus der ersten ebene
        return $this->entries[$number];
    }

    # gibt den eintrag zurück
    function getEntry($number) {
        # der eintrag aus einer sub ebene
        if(strpos($number,GB_DELIMITER) !== false)
            return eval('return $this->entries['.str_replace(GB_DELIMITER,"][",$number).']["en"];');
        # der eintrag aus der ersten ebene
        return $this->entries[$number]["en"];
    }

    # einträge anhand eines arrays ($number) löschen
    # return ein array mit einträgen die nicht gelöscht wurden
    function deleteEntrys($number) {
        if(!$this->save or !$this->is_admin)
            return $number;
        $success = false;
        # alle übergebene einträge löschen
        foreach($number as $pos => $entry) {
            $entry = str_replace(GB_DELIMITER,"][",$number[$pos]);
            if(eval('return isset($this->entries['.$entry.']);')) {
                eval('unset($this->entries['.$entry.']);');
                if(eval('return isset($this->entries['.$entry.']);') === false) {
                    $success = true;
                    unset($number[$pos]);
                }
            }
        }
        if($success) {
            # Es wurden nicht alle gelöscht, die gelöschten speichern
            if(isset($number))
                $this->saveData();
            # Es wurden alle gelöscht. Die function speichert auch
            else
                $this->reorderKeys();
        }
        # Es wurden nicht alle gelöscht
        if(isset($number))
            return $number;
        return array();
    }

    # Ändert eine Spalte im Eintrag
    # $number = (eintrags nr.)-en-(spalte) z.B. 13-1-en-5
    function changeEntryRowValue($number, $value) {
        if(!$this->save or !$this->is_admin)
            return false;
        $number = str_replace(GB_DELIMITER,"][",$number);
        $entry_value = false;
        if(eval('return isset($this->entries['.$number.']);')) {
            eval('$entry_value = & $this->entries['.$number.'];');
            # nur wenn der Eintrag sich geändert hat
            if($entry_value !== $value) {
                $entry_value = $value;
                $this->saveData();
                return true;
            }
            return null;
        }
        return false;
    }

    function reorderKeys($entries = false) {
        if(!$this->save or !$this->is_admin)
            return false;
        $newentries = array();
        $sort_date = array();
        $save = false;
        if(!$entries) {
            $save = true;
            $entries = $this->entries;
        }
        $newpos = 0;
        foreach($entries as $pos => $daten) {
            $sort_date[$newpos] = $daten["en"][0];
            $newentries[$newpos]["en"] = $daten["en"];
            unset($daten["en"]);
            if(isset($daten) and count($daten) > 0) {
                $newentries[$newpos] += $this->reorderKeys($daten);
            }
            $newpos++;
        }
        if(count($sort_date) > 1 and count($newentries) == count($sort_date)) {
            array_multisort($sort_date, SORT_NUMERIC, $newentries);
            krsort($newentries,SORT_NUMERIC);
        }
        if($save) {
            $this->entries = $newentries;
            $this->saveData();
            return true;
        }
        return $newentries;
    }

    function moveEntryTo($entry,$to) {
        if(!$this->save or !$this->is_admin)
            return false;
        $entry = str_replace(GB_DELIMITER,"][",$entry);
        if(strlen($entry) < 1) $entry = -1;
        if(eval('return isset($this->entries['.$entry.']);')) {
            $to = str_replace(GB_DELIMITER,"][",$to);
            if(strlen($to) < 1) $to = -1;
            if(strpos($to,"][") === false) {
                if(eval('return isset($this->entries['.$to.']);'))
                    $to = '['.$to.']';
                else
                    $to = '';
            } elseif(eval('return isset($this->entries['.$to.']);'))
                $to = '['.$to.']';
            else
                return false;
            eval('$this->entries'.$to.'[] = $this->entries['.$entry.'];
                  unset($this->entries['.$entry.']);');
        } else
            return false;
        $this->reorderKeys();
        return true;
    }

    function getCleanNumber($number,$minus = false) {
        if(!is_string($number)) return false;
        $number = trim($number);
        if($number === "new") return 'new';
        if(preg_match('/[^\d\-en]/',$number)) return false;
        if(strlen($number) == 0) return '';
        if(is_numeric($number)) {
            if($minus) return $number - 1;
            return $number;
        }
        preg_match_all('/([\d]+|[e][n])+([\-])?/',$number,$match);
        if(!isset($match[1]) or (isset($match[1]) and count($match[1]) < 1))
            return false;
        if(implode(GB_DELIMITER,$match[1]) != $number) return false;
        if($minus) {
            foreach($match[1] as $pos => $value) {
                if(is_numeric($value)) $value = $value - 1;
                $match[1][$pos] = $value;
            }
        }
        return implode(GB_DELIMITER,$match[1]);
    }

}
?>