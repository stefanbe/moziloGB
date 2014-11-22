<?php if(!defined('IS_ADMIN') or !IS_ADMIN) die();

# http://www.onsite.org/
function dat_convertGB($line) {
    if(substr_count($line,"#") == 7 or substr_count($line,"#") == 9) {
        global $specialchars;
        $line = str_replace("#","-pipe~",$line);
        $line = urldecode($line);
        $line = str_replace(array("\r\n","\r","\n"),"\n",$line);
        $entry = explode("-pipe~",trim($line));

        $new_entry = array();
        $new_entry["en"][0] = strtotime(trim($entry[4])." ".trim($entry[5]));
        $new_entry["en"][1] = trim($entry[0]);
        $new_entry["en"][1] = $specialchars->rebuildSpecialChars($new_entry["en"][1], true, false);
        $new_entry["en"][2] = trim($entry[1]);
        $new_entry["en"][3] = trim($entry[2]);
        $new_entry["en"][4] = str_replace("\n","<br />",trim($entry[3]));
        $new_entry["en"][4] = str_replace(array(":)",":-)",";)",";-)",":("),array(":lach:",":lach:",":zwinker:",":zwinker:",":traurig:"),$new_entry["en"][4]);
        $new_entry["en"][4] = str_replace(array('\\\"','\"'),'"',$new_entry["en"][4]);
        $new_entry["en"][4] = $specialchars->rebuildSpecialChars($new_entry["en"][4], true, false);
        $new_entry["en"][5] = "";
        if(isset($entry[8]) and strlen($entry[8]) > 2) {
            $new_entry["en"][5] = str_replace("\n","<br />",trim($entry[8]));
            $new_entry["en"][5] = str_replace(array(":)",":-)",";)",";-)",":("),array(":lach:",":lach:",":zwinker:",":zwinker:",":traurig:"),$new_entry["en"][5]);
            $new_entry["en"][5] = str_replace(array('\\\"','\"'),'"',$new_entry["en"][5]);
            $new_entry["en"][5] = $specialchars->rebuildSpecialChars($new_entry["en"][5], true, false);
        }
        $new_entry["en"][6] = trim($entry[7]);
        $new_entry["en"][7] = gethostbyaddr($new_entry["en"][6]);
        return $new_entry;
    }
    return false;
}

function convertGB($line) {
    global $specialchars;
    $line = toUtf($line);
    $line = str_replace("|","-pipe~",$line);
    $line = str_replace("[br]","\n",$line);
    $line = str_replace("[pipe]","|",$line);
    $line = str_replace("%7C","|",$line);
    $line = rawurldecode($line);
    $line = str_replace(array("\r\n","\r","\n"),"\n",$line);
    $entry = explode("-pipe~",trim($line));
    if(count($entry) !== 8)
        return false;
    $new_entry = array();
    $new_entry["en"][0] = trim($entry[0]);
    $new_entry["en"][1] = $specialchars->rebuildSpecialChars(trim($entry[3]), false, true);
    $new_entry["en"][2] = $specialchars->rebuildSpecialChars(trim($entry[4]), false, true);
    $new_entry["en"][3] = $specialchars->rebuildSpecialChars(trim($entry[5]), false, true);
    $new_entry["en"][4] = $specialchars->rebuildSpecialChars(trim($entry[6]), false, false);
    $new_entry["en"][4] = str_replace(array('&#058;',"\n"),array(':','<br />'),$new_entry["en"][4]);
    if(strpos($new_entry["en"][4],"<img ") !== false) {
        preg_match_all('#<img [^>]*?alt=["\'](.*)["\'][^>]*?>#is', $new_entry["en"][4], $match);
        if(isset($match[0][0])) {
            foreach($match[1] as $pos => $smiley) {
                if($smiley[0] == ":" and $smiley[(strlen($smiley)-1)] == ":")
                    $new_entry["en"][4] = str_replace($match[0][$pos],$smiley,$new_entry["en"][4]);
            }
        }
    }
    $new_entry["en"][5] = $specialchars->rebuildSpecialChars(trim($entry[7]), false, false);
    $new_entry["en"][5] = str_replace(array('&#058;',"\n"),array(':','<br />'),$new_entry["en"][5]);
    $new_entry["en"][6] = trim($entry[1]);
    $new_entry["en"][7] = trim($entry[2]);
    return $new_entry;
}

function toUtf($string) {
    if(!check_utf8($string)) {
        if(function_exists("iconv")) {
#            $string = iconv('ISO-8859-1', CHARSET.'//IGNORE',$string);
            $string = iconv('cp1252', CHARSET.'//IGNORE',$string);
        } elseif(function_exists("mb_convert_encoding")) {
            $string = mb_convert_encoding($string, CHARSET);
        } elseif(function_exists("utf8_encode")) {
            $string = utf8_encode($string);
        }
    }
    return $string;
}

function check_utf8($str) {
    $len = strlen($str);
    for($i = 0; $i < $len; $i++){
        $c = ord($str[$i]);
        if ($c > 128) {
            if (($c > 247)) return false;
            elseif ($c > 239) $bytes = 4;
            elseif ($c > 223) $bytes = 3;
            elseif ($c > 191) $bytes = 2;
            else return false;
            if (($i + $bytes) > $len) return false;
            while ($bytes > 1) {
                $i++;
                $b = ord($str[$i]);
                if ($b < 128 || $b > 191) return false;
                $bytes--;
            }
        }
    }
    return true;
}
?>