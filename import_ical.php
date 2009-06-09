<?php
/*
 * $Id: import_ical.php,v 1.14 2004/11/22 16:39:48 cknudsen Exp $
 *
 * File Description:
 *    This file incudes functions for parsing iCal data files during
 *    an import.
 *
 *    It will be included by import_handler.php.
 *
 * The iCal specification is available online at:
 *    http://www.ietf.org/rfc/rfc2445.txt
 *
 */

// Parse the ical file and return the data hash.

function clean_up ( $text ) {
//return utf8_decode($text);
return $text;
/*   $array = split (" ",$text) ;
   foreach ($array as $VAL)
       {
       $VAL = str_replace("&#1488","&#65533;",$VAL);
       $VAL = str_replace("&#1489","&#65533;",$VAL);
       $VAL = str_replace("&#1490","&#65533;",$VAL);
       $VAL = str_replace("&#1491","&#65533;",$VAL);
       $VAL = str_replace("&#1492","&#65533;",$VAL);
       $VAL = str_replace("&#1493","&#65533;",$VAL);
       $VAL = str_replace("&#1494","&#65533;",$VAL);
       $VAL = str_replace("&#1495","&#65533;",$VAL);
       $VAL = str_replace("&#1496","&#65533;",$VAL);
       $VAL = str_replace("&#1497","&#65533;",$VAL);
       $VAL = str_replace("&#1499","&#65533;",$VAL);
       $VAL = str_replace("&#1500","&#65533;",$VAL);
       $VAL = str_replace("&#1502","&#65533;",$VAL);
       $VAL = str_replace("&#1504","&#65533;",$VAL);
       $VAL = str_replace("&#1505","&#65533;",$VAL);
       $VAL = str_replace("&#1506","&#65533;",$VAL);
       $VAL = str_replace("&#1508","&#65533;",$VAL);
       $VAL = str_replace("&#1510","&#65533;",$VAL);
       $VAL = str_replace("&#1511","&#65533;",$VAL);
       $VAL = str_replace("&#1512","&#65533;",$VAL);
       $VAL = str_replace("&#1513","&#65533;",$VAL);
       $VAL = str_replace("&#1514","&#65533;",$VAL);
       $VAL = str_replace("&#1498","&#65533;",$VAL);
       $VAL = str_replace("&#1507","&#65533;",$VAL);
       $VAL = str_replace("&#1503","&#65533;",$VAL);
       $VAL = str_replace("&#1501","&#65533;",$VAL);
       $VAL = str_replace("&#1509","&#65533;",$VAL);
       $VAL = str_replace(";","",$VAL);
       $text .= $VAL." ";
      
       }
*/
//$text=iconv("UTF-8","ISO-8859-1",$text);
//$text=html_entity_decode($text, ENT_QUOTES, 'ISO-8859-1');
//return iconv("ISO-8859-1","UTF-8",$text);
//return htmlentities($text,ENT_QUOTES,'ISO-8859-1');
//return html_entity_decode(htmlentities($text), ENT_QUOTES, 'ISO-8859-1');
    //return substr($text, 0, strlen($text)-1);
}
function parse_ical ( $cal_file ) {
  global $tz, $errormsg;

  $ical_data = array();

  if (!$fd=@fopen($cal_file,"r")) {
    $errormsg .= "Can't read temporary file: $cal_file\n";
    exit();
  } else {

    // Read in contents of entire file first
    $data = '';
    while (!feof($fd) && !$error) {
      $line++;
      $data .= fgets($fd, 4096);
    }
    fclose($fd);
$data = clean_up($data);
    // Now fix folding.  According to RFC, lines can fold by having
    // a CRLF and then a single white space character.
    // We will allow it to be CRLF, CR or LF or any repeated sequence
    // so long as there is a single white space character next.
    //echo "Orig:<br><pre>$data</pre><br/><br/>\n";
    $data = preg_replace ( "/[\r\n]+ /", "", $data );
    $data = preg_replace ( "/[\r\n]+/", "\n", $data );
    //echo "Data:<br><pre>$data</pre><P>";

    // reflect the section where we are in the file:
    // VEVENT, VTODO, VJORNAL, VFREEBUSY, VTIMEZONE
    $state = "NONE";
    $substate = "none"; // reflect the sub section
    $subsubstate = ""; // reflect the sub-sub section
    $error = false;
    $line = 0;
    $event = '';

    $lines = explode ( "\n", $data );
    for ( $n = 0; $n < count ( $lines ) && ! $error; $n++ ) {
      $line++;
      $buff = $lines[$n];

      // parser debugging code...
      //echo "line = $line <br />";
      //echo "state = $state <br />";
      //echo "substate = $substate <br />";
      //echo "subsubstate = $subsubstate <br />";
      //echo "buff = " . htmlspecialchars ( $buff ) . "<br /><br />\n";

      if ($state == "VEVENT") {
          if ( ! empty ( $subsubstate ) ) {
            if (preg_match("/^END:(.+)$/i", $buff, $match)) {
              if ( $match[1] == $subsubstate ) {
                $subsubstate = '';
              }
            } else if ( $subsubstate == "VALARM" && 
              preg_match ( "/TRIGGER:(.+)$/i", $buff, $match ) ) {
              // Example: TRIGGER;VALUE=DATE-TIME:19970317T133000Z
              //echo "Set reminder to $match[1]<br />";
              // reminder time is $match[1]
            }
          }
          else if (preg_match("/^BEGIN:(.+)$/i", $buff, $match)) {
            $subsubstate = $match[1];
          }
           // we suppose ":" is on the same line as property name, this can perhaps cause problems
      else if (preg_match("/^SUMMARY[^:]*:(.+)$/i", $buff, $match)) {
              $substate = "summary";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^DESCRIPTION[^:]*:(.+)$/i", $buff, $match)) {
              $substate = "description";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^LOCATION[^:]*:(.+)$/i", $buff, $match)) {
              $substate = "location";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^URL(?:;VALUE=[^:]+)?:(.+)$/i", $buff, $match)) {
              $substate = "url";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^CLASS[^:]*:(.*)$/i", $buff, $match)) {
              $substate = "class";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^PRIORITY[^:]*:(.*)$/i", $buff, $match)) {
              $substate = "priority";
              $event[$substate] = $match[1];
      } elseif (preg_match("/^DTSTART[^:]*:\s*(\d+T\d+Z?)\s*$/i", $buff, $match)) {
              $substate = "dtstart";
              $event[$substate] = $match[1];
      } elseif (preg_match("/^DTSTART[^:]*:\s*(\d+)\s*$/i", $buff, $match)) {
              $substate = "dtstart";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^DTEND[^:]*:\s*(.*)\s*$/i", $buff, $match)) {
              $substate = "dtend";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^DURATION[^:]*:(.+)\s*$/i", $buff, $match)) {
              $substate = "duration";
              $durH = $durM = $durS = 0;
              if ( preg_match ( "/PT(?:([0-9]+)H)?(?:([0-9]+)M)?(?:([0-9]+)S)?/", $match[1], $submatch ) ) {
                  $durH = $submatch[1];
                  $durM = $submatch[2];
                  $durS = $submatch[3];
          }
              $event[$substate] = $durH * 60 + $durM + $durS / 60;
          } elseif (preg_match("/^RRULE[^:]*:(.+)$/i", $buff, $match)) {
              $substate = "rrule";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^EXDATE[^:]*:(.+)$/i", $buff, $match)) {
              $substate = "exdate";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^CATEGORIES[^:]*:(.+)$/i", $buff, $match)) {
              $substate = "categories";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^STATUS[^:]*:(.+)$/i", $buff, $match)) {
              $substate = "status";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^RECURRENCE-ID[^:]*:\s*(.*)\s*$/i", $buff, $match)) {
              $substate = "recurrence-id";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^UID[^:]*:(.+)$/i", $buff, $match)) {
              $substate = "uid";
              $event[$substate] = $match[1];
          } elseif (preg_match("/^END:VEVENT$/i", $buff, $match)) {
              $state = "VCALENDAR";
              $substate = "none";
              $subsubstate = '';
          $ical_data[] = format_ical($event);
              // clear out data for new event
              $event = '';

      // TODO: QUOTED-PRINTABLE descriptions

      // folded lines
          // TODO: This is not the best way to handle folded lines.
          // We should fix the folding before we parse...
          } elseif (preg_match("/^\s(\S.*)$/", $buff, $match)) {
              if ($substate != "none") {
                  $event[$substate] .= $match[1];
              } else {
                  $errormsg .= "iCal parse error on line $line:<br />$buff\n";
                  $error = true;
              }
          // For unsupported properties
      } else {
            $substate = "none";
          }
      } elseif ($state == "VCALENDAR") {
          if (preg_match("/^BEGIN:VEVENT/i", $buff)) {
            $state = "VEVENT";
          } elseif (preg_match("/^END:VCALENDAR/i", $buff)) {
            $state = "NONE";
          } else if (preg_match("/^BEGIN:VTIMEZONE/i", $buff)) {
            $state = "VTIMEZONE";
          } else if (preg_match("/^BEGIN:VALARM/i", $buff)) {
            $state = "VALARM";
          }
      } elseif ($state == "VTIMEZONE") {
        // We don't do much with timezone info yet...
        if (preg_match("/^END:VTIMEZONE$/i", $buff)) {
          $state = "VCALENDAR";
        }
      } elseif ($state == "NONE") {
         if (preg_match("/^BEGIN:VCALENDAR$/i", $buff))
           $state = "VCALENDAR";
      }
    } // End while
  }

  return $ical_data;
}

// Convert ical format (yyyymmddThhmmssZ) to epoch time
function icaldate_to_timestamp ($vdate, $plus_d = '0', $plus_m = '0',
  $plus_y = '0') {
  global $TZoffset;

  $y = substr($vdate, 0, 4) + $plus_y;
  $m = substr($vdate, 4, 2) + $plus_m;
  $d = substr($vdate, 6, 2) + $plus_d;
  $H = substr($vdate, 9, 2);
  $M = substr($vdate, 11, 2);
  $S = substr($vdate, 13, 2);
  $Z = substr($vdate, 15, 1);

  if ($Z == 'Z') {
    $TS = gmmktime($H,$M,$S,$m,$d,$y);
  } else {
    // Problem here if server in different timezone
    $TS = mktime($H,$M,$S,$m,$d,$y);
  }

  return $TS;
}


// Put all ical data into import hash structure
function format_ical($event) {

  // Start and end time
  $fevent['StartTime'] = icaldate_to_timestamp($event['dtstart']);
  if ( isset ( $event['dtend'] ) ) {
    $fevent['EndTime'] = icaldate_to_timestamp($event['dtend']);
  } else {
    if ( isset ( $event['duration'] ) ) {
      $fevent['EndTime'] = $fevent['StartTime'] + $event['duration'] * 60;
    } else {
      $fevent['EndTime'] = $fevent['StartTime'];
    }
  }

  // Calculate duration in minutes
  if ( isset ( $event['duration'] ) ) {
    $fevent['Duration'] = $event['duration'];
  } else if ( empty ( $fevent['Duration'] ) ) {
    $fevent['Duration'] = ($fevent['EndTime'] - $fevent['StartTime']) / 60;
  }

  if ( $fevent['Duration'] == '1440' ) {
    // All day event... nothing to do here :-)
  } else if ( preg_match ( "/\d{8}$/",
    $event['dtstart'], $pmatch ) ) {
    // Untimed event
    $fevent['Duration'] = 0;
    $fevent['Untimed'] = 1;
  }

  $fevent['Summary'] = format_ical_text($event['summary']);
  $fevent['Description'] = format_ical_text($event['description']);
  $fevent['Location'] = format_ical_text($event['location']);
  $fevent['URL'] = format_ical_text($event['url']);
  $fevent['Private'] = preg_match("/private|confidential/i", $event['class']) ? '1' : '0';
  $fevent['UID'] = $event['uid'];

  $fevent['Status'] = format_ical_text($event['status']);
  if ( isset( $event['recurrence-id'] ) ) {
    $fevent['RecurrenceID'] = icaldate_to_timestamp($event['recurrence-id']);
  }

  // Repeats
  //
  // Handle RRULE
  if ($event['rrule']) {
    // first remove and EndTime that may have been calculated above
    unset ( $fevent['Repeat']['EndTime'] );
    //split into pieces
    //echo "RRULE line: $event[rrule] <br />\n";
    $RR = explode ( ";", $event['rrule'] );

    // create an associative array of key-value paris in $RR2[]
    for ( $i = 0; $i < count ( $RR ); $i++ ) {
      $ar = explode ( "=", $RR[$i] );
      $RR2[$ar[0]] = $ar[1];
    }

    for ( $i = 0; $i < count ( $RR ); $i++ ) {
      //echo "RR $i = $RR[$i] <br />";
      if ( preg_match ( "/^FREQ=(.+)$/i", $RR[$i], $match ) ) {
        if ( preg_match ( "/YEARLY/i", $match[1], $submatch ) ) {
          $fevent['Repeat']['Interval'] = 5;
        } else if ( preg_match ( "/MONTHLY/i", $match[1], $submatch ) ) {
          $fevent['Repeat']['Interval'] = 2;
        } else if ( preg_match ( "/WEEKLY/i", $match[1], $submatch ) ) {
          $fevent['Repeat']['Interval'] = 2;
        } else if ( preg_match ( "/DAILY/i", $match[1], $submatch ) ) {
          $fevent['Repeat']['Interval'] = 1;
        } else {
          // not supported :-(
          if (ICAL_EVENTS_DEBUG) echo "Unsupported iCal FREQ value \"$match[1]\"<br />\n";
        }
      } else if ( preg_match ( "/^INTERVAL=(.+)$/i", $RR[$i], $match ) ) {
        $fevent['Repeat']['Frequency'] = $match[1];
      } else if ( preg_match ( "/^UNTIL=(.+)$/i", $RR[$i], $match ) ) {
        // specifies an end date
        $fevent['Repeat']['EndTime'] = icaldate_to_timestamp ( $match[1] );
      } else if ( preg_match ( "/^COUNT=(.+)$/i", $RR[$i], $match ) ) {
        // NOT YET SUPPORTED -- TODO
        if (ICAL_EVENTS_DEBUG) echo "Unsupported iCal COUNT value \"$RR[$i]\"<br />\n";
      } else if ( preg_match ( "/^BYSECOND=(.+)$/i", $RR[$i], $match ) ) {
        // NOT YET SUPPORTED -- TODO
        if (ICAL_EVENTS_DEBUG) echo "Unsupported iCal BYSECOND value \"$RR[$i]\"<br />\n";
      } else if ( preg_match ( "/^BYMINUTE=(.+)$/i", $RR[$i], $match ) ) {
        // NOT YET SUPPORTED -- TODO
        if (ICAL_EVENTS_DEBUG) echo "Unsupported iCal BYMINUTE value \"$RR[$i]\"<br />\n";
      } else if ( preg_match ( "/^BYHOUR=(.+)$/i", $RR[$i], $match ) ) {
        // NOT YET SUPPORTED -- TODO
        if (ICAL_EVENTS_DEBUG) echo "Unsupported iCal BYHOUR value \"$RR[$i]\"<br />\n";
      } else if ( preg_match ( "/^BYMONTH=(.+)$/i", $RR[$i], $match ) ) {
        // this event repeats during the specified months
        $months = explode ( ",", $match[1] );
        if ( count ( $months ) == 1 ) {
          // Change this to a monthly event so we can support repeat by
          // day of month (if needed)
          // Frequency = 3 (by day), 4 (by date), 6 (by day reverse)
          if ( ! empty ( $RR2['BYDAY'] ) ) {
            if ( preg_match ( "/^-/", $RR2['BYDAY'], $junk ) )
              $fevent['Repeat']['Interval'] = 6; // monthly by day reverse
            else
              $fevent['Repeat']['Interval'] = 3; // monthly by day
            $fevent['Repeat']['Frequency'] = 12; // once every 12 months
          } else {
            // could convert this to monthly by date, but we will just
            // leave it as yearly.
            //$fevent['Repeat']['Interval'] = 4; // monthly by date
          }
        } else {
          // WebCalendar does not support this
          if (ICAL_EVENTS_DEBUG) echo "Unsupported iCal BYMONTH value \"$match[1]\"<br />\n";
        }
      } else if ( preg_match ( "/^BYDAY=(.+)$/i", $RR[$i], $match ) ) {
        $fevent['Repeat']['RepeatDays'] = rrule_repeat_days( explode(',', $match[1]) );
      } else if ( preg_match ( "/^BYMONTHDAY=(.+)$/i", $RR[$i], $match ) ) {
        // NOT YET SUPPORTED -- TODO
        if (ICAL_EVENTS_DEBUG) echo "Unsupported iCal BYMONTHDAY value \"$RR[$i]\"<br />\n";
      } else if ( preg_match ( "/^BYSETPOS=(.+)$/i", $RR[$i], $match ) ) {
        // NOT YET SUPPORTED -- TODO
        if (ICAL_EVENTS_DEBUG) echo "Unsupported iCal BYSETPOS value \"$RR[$i]\"<br />\n";
      }
    }

    // Repeating exceptions?
    if ($event['exdate']) {
      $fevent['Repeat']['Exceptions'] = array();
      $EX = explode(",", $event['exdate']);
      foreach ( $EX as $exdate ){
        $fevent['Repeat']['Exceptions'][] = icaldate_to_timestamp($exdate);
      }
    }
  } // end if rrule

  return $fevent;
}

// Figure out days of week for weekly repeats
function rrule_repeat_days($RA) {
  $T = count($RA);
  $sun = $mon = $tue = $wed = $thu = $fri = $sat = 'n';
  for ($i = 0; $i < $T; $i++) {
    if ($RA[$i] == 'SU') {
      $sun = 'y';
    } elseif ($RA[$i] == 'MO') {
      $mon = 'y';
    } elseif ($RA[$i] == 'TU') {
      $tue = 'y';
    } elseif ($RA[$i] == 'WE') {
      $wed = 'y';
    } elseif ($RA[$i] == 'TH') {
      $thu = 'y';
    } elseif ($RA[$i] == 'FR') {
      $fri = 'y';
    } elseif ($RA[$i] == 'SA') {
      $sat = 'y';
    }
  }
  return $sun.$mon.$tue.$wed.$thu.$fri.$sat;
}


// Calculate repeating ending time
function rrule_endtime($int,$freq,$start,$end) {

  // if # then we have to add the difference to the start time
  if (preg_match("/^#(.+)$/i", $end, $M)) {
    $T = $M[1] * $freq;
    $plus_d = $plus_m = $plus_y = '0';
    if ($int == '1') {
      $plus_d = $T;
    } elseif ($int == '2') {
      $plus_d = $T * 7;
    } elseif ($int == '3') {
      $plus_m = $T;
    } elseif ($int == '4') {
      $plus_m = $T;
    } elseif ($int == '5') {
      $plus_y = $T;
    } elseif ($int == '6') {
      $plus_m = $T;
    }
    $endtime = icaldate_to_timestamp($start,$plus_d,$plus_m,$plus_y);

  // if we have the enddate
  } else {
    $endtime = icaldate_to_timestamp($end);
  }
  return $endtime;
}

// Replace RFC 2445 escape characters
function format_ical_text($value) {
  $output = str_replace(
    array('\\\\', '\;', '\,', '\N', '\n'),
    array('\\',   ';',  ',',  "\n", "\n"),
    $value
  );

  return $output;
}

?>

