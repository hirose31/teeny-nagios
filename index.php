<?php
/*
  "Teeny Nagios"

  Teeny web interface for Nagios with smartphone(iPhone, Android)

  https://github.com/hirose31/teeny-nagios

 */

ini_set("track_errors", TRUE);
ini_set("date.timezone", "Asia/Tokyo");

$STATUS_FILE  = getenv("TN_STATUS_FILE")  ? getenv("TN_STATUS_FILE")  : "/var/log/nagios/status.dat";
$COMMAND_FILE = getenv("TN_COMMAND_FILE") ? getenv("TN_COMMAND_FILE") : "/var/log/nagios/rw/nagios.cmd";

define("HOST_UP",          0);
define("HOST_DOWN",        1);
define("HOST_UNREACHABLE", 2);

define("STATE_OK",       0);
define("STATE_WARNING",  1);
define("STATE_CRITICAL", 2);
define("STATE_UNKNOWN",  3);

$HOST_STATUS_BY = array(
                        HOST_UP          => "Up",
                        HOST_DOWN        => "Down",
                        HOST_UNREACHABLE => "Unreachable",
                        );
$SERVICE_STATUS_BY = array(
                           STATE_OK       => "OK",
                           STATE_WARNING  => "Warning",
                           STATE_CRITICAL => "Critical",
                           STATE_UNKNOWN  => "Unknown",
                           );

$BASE_URL = $_SERVER["SCRIPT_NAME"];

$status = parse_status_file($STATUS_FILE);
$global_stats = calc_global_stats($status);

switch ($_REQUEST["page"]) {
case NULL:
    view_main($global_stats, $status);
    break;
case "downtime":
    view_downtime($global_stats, $status);
    break;
case "schedule_downtime":
    command_schedule_downtime($global_stats, $status);
    break;
default:
    trigger_error("unknown page: ".htmlspecialchars($_GET["page"], ENT_QUOTES), E_USER_NOTICE);
}

function begin_html() {
    global $BASE_URL;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>Teeny Nagios</title>
<link rel="apple-touch-icon" href="nagios.png" />
<link rel="stylesheet" href="http://code.jquery.com/mobile/1.0a4.1/jquery.mobile-1.0a4.1.css" />
<script src="http://code.jquery.com/jquery-1.5.2.min.js"></script>
<script src="http://code.jquery.com/mobile/1.0a4.1/jquery.mobile-1.0a4.1.js"></script>
<style>
body {
    font-family: Helvetica;
}
h2 {
    margin-bottom: 0;
}
h2:first-child {
    margin-top: 0;
}
ul.overview li {
    font-size: 12px;
}
div.rounded {
    -webkit-border-radius: 4px;
    -webkit-box-shadow: rgba(0,0,0,.3) 1px 1px 3px;
    background:   #ccc;
    margin: 5px 10px 10px;
    padding: 6px 10px;
    text-shadow: none;
}
div.rounded ul, div.rounded dl {
    color: #333;
    border: 0;
    font: normal 12px Helvetica;
    margin:  0;
    padding: 0;
}

div.rounded ul li, div.rounded dl dt, div.rounded dl dd {
    color: #333;
    border: 0;
    margin:  0;
    padding: 0;
    background: transparent;
}

div.rounded dl dt {
    font-size: 120%;
    font-weight: bold;
}

div.result {
    background:   #ccc;
    text-align: center;
    font-weight: bold;
    line-height: 3em;
    color: #333;
    text-shadow: none;
    margin: 0 -15px;
}

.critical {
    background: -webkit-gradient(linear, 0% 20%, 0% 100%, from(transparent), to(#f00)) !important;
}

.warning {
    background: -webkit-gradient(linear, 0% 20%, 0% 100%, from(transparent), to(#ff0)) !important;
}

.downtime .ui-btn-text a {
    color: #888;
    text-shadow: none;
}

.ui-field-contain {
    border-bottom-width: 0;
    padding: 0.5em 0;
}

</style>

<script>
$(document).ready(function(){
    var TN_DEBUG = true;
    var debug = TN_DEBUG ? console : { log: function(){}, debug: function(){}, warn: function(){}, info: function(){} };

    $('.submitDowntimeButton').live('click', function(e){
        e.stopPropagation();
        var that = $(this);

        var dt_id = that.data("dt_id");
        var form_id = '#dt_' + dt_id + '_form';
        var form = $(form_id);
        var result_id = '#dt_' + dt_id + '_result';
        debug.log("dt_id: "+dt_id);
        debug.log("form_id: "+form_id);
        debug.log("result_id: "+result_id);

        var dt_host     = form.data("dt_host");
        var dt_comment ;
        var dt_end_date;
        var dt_end_time;


        var dt_service  = form.data("dt_service");
        var dt_comment
        var dt_comment  = form.find('#dt_'+dt_id+'_comment').val();
        var dt_end_date = form.find('#dt_'+dt_id+'_end_date option:selected').val();
        var dt_end_time = form.find('#dt_'+dt_id+'_end_time option:selected').val();
        debug.log("host: "+dt_host+", service: "+dt_service+", comment: "+dt_comment+", end_date: "+dt_end_date+", end_time: "+dt_end_time);

        that.addClass('ui-disabled')
            .removeClass('ui-btn-active');
        $(result_id).html('<p>Processing...</p>');
        $.ajax({
            type: 'POST',
            cache: false,
            url: '<?php echo $BASE_URL ?>',
            dataTYpe: 'json',
            data: {
                host     : dt_host,
                service  : dt_service,
                comment  : dt_comment,
                end_date : dt_end_date,
                end_time : dt_end_time,
                page     : 'schedule_downtime'
            },
            success: function(ret) {
                $(result_id).html('<p>Done</p>');
                that.removeClass('ui-disabled');
            },
            error: function(xhr) {
                $(result_id).html('<p>Error ('+xhr.code+')</p>');
                that.removeClass('ui-disabled');
            }
        });

        return false;
    });
});


</script>

</head>
<body>
<?php
}

function end_html() {
?>
</body>
</html>
<?php
}
?>

<?php
function view_main($global_stats, $status) {
    global $STATUS_FILE, $COMMAND_FILE;
    global $HOST_STATUS_BY, $SERVICE_STATUS_BY;
    global $BASE_URL;
    begin_html();
?>
<!-- home ============================================================ -->
<div data-role="page" id="home" data-theme="a">
  <div data-role="header" data-backbtn="false">
    <h1>Teeny Nagios</h1>
    <a href="#about" data-icon="info" data-iconpos="notext" data-rel="dialog" data-transition="flip" class="ui-btn-right">About</a>
  </div>

  <div data-role="content">
    <h2>Current Status</h2>
    <ul data-role="listview" data-inset="true" data-theme="a">
      <li><a href="#hosts" id="loadHosts">Hosts</a><span class="ui-li-count"><?php echo count($status["hosts"]) ?></span></li>
    </ul>

    <h2>Overview</h2>

    <div class="information rounded">
      <dl>
        <dt>Hosts</dt>
        <dd><?php echo $global_stats["host_up"] ?> Up</dd>
        <dd class="<?php echo class_by_state($global_stats["host_down"] > 0 ? STATE_CRITICAL : STATE_OK) ?>"><?php echo $global_stats["host_down"] ?> Down</dd>
        <dd class="<?php echo class_by_state($global_stats["host_unreachable"] > 0 ? STATE_CRITICAL : STATE_OK) ?>"><?php echo $global_stats["host_unreachable"] ?> Unreachable</dd>
        <dt>Services</dt>
        <dd><?php echo $global_stats["service_ok"] ?> OK</dd>
        <dd class="<?php echo class_by_state($global_stats["service_warning"] > 0 ? STATE_WARNING : STATE_OK) ?>"><?php echo $global_stats["service_warning"] ?> Warning</dd>
        <dd class="<?php echo class_by_state($global_stats["service_critical"] > 0 ? STATE_CRITICAL : STATE_OK) ?>"><?php echo $global_stats["service_critical"] ?> Critical</dd>
        <dd class="<?php echo class_by_state($global_stats["service_unknown"] > 0 ? STATE_CRITICAL : STATE_OK) ?>"><?php echo $global_stats["service_unknown"] ?> Unknown</dd>
      </dl>
    </div>
  </div>
</div>

<div data-role="page" id="about" data-theme="a">
  <div data-role="header">
    <h1>About</h1>
  </div>

  <div data-role="content">
    <h2>Teeny Nagios</h2>
    <p>Teeny web interface for Nagios with smartphone(iPhone, Android)</p>
    <p>
    <a href="https://github.com/hirose31/teeny-nagios" rel="external">https://github.com/hirose31/teeny-nagios</a>
    </p>
  </div>
</div>

<!-- hosts =========================================================== -->
<div data-role="page" id="hosts" data-theme="a">

  <div data-role="header">
    <h1>Hosts</h1>
    <a href="#home" data-icon="home" data-iconpos="notext"  data-direction="reverse" class="ui-btn-right">Home</a>
  </div>

  <div data-role="content">
    <ul data-role="listview" data-inset="true" data-theme="a">
<?php
foreach ($status["hosts"] as $host_id => $host_status) {
    $host = $host_status["host_name"];
    $class = class_by_state( service_state_of($host_status['services']) );
    $count = count($host_status['services']);
    print("    <li class=\"$class\"><a href=\"#$host_id\">$host</a><span class=\"ui-li-count\">$count</span></li>\n");
}
?>
    </ul>
  </div>
</div>

<!-- host ============================================================ -->
<?php
foreach ($status["hosts"] as $host_id => $host_status) {
    $host = $host_status["host_name"];
?>
<div data-role="page" id="<?php echo $host_id ?>" data-theme="a">
  <div data-role="header">
    <h1><?php echo $host ?></h1>
    <a href="#home" data-icon="home" data-iconpos="notext"  data-direction="reverse" class="ui-btn-right">Home</a>
  </div>

  <div data-role="content">
    <h2>Services</h2>
    <ul data-role="listview" data-inset="true" data-theme="a">
<?php
    if (isset($host_status["services"])) {
        foreach ($host_status["services"] as $service_id => $service_status) {
            $class = class_by_state( service_state_of(array($service_id => $service_status)) );
            if ($service_status["scheduled_downtime_depth"] > 0) {
                $class = " downtime";
            }
            print("    <li class=\"$class\"><a href=\"#${host_id}_${service_id}\">".$service_status["service_description"]."</a></li>\n");
        }
    }
?>
    </ul>

    <h2>Information</h2>
    <div class="information rounded">
      <dl>
        <dt>Status
        <dd class="<?php echo class_by_state($host_status["current_state"] > 0 ? STATE_CRITICAL : STATE_OK) ?>"><?php echo $HOST_STATUS_BY[ $host_status["current_state"] ] ?> (for <?php echo stringize_duration(get_time_breakdown($status, $host_status)) ?>)
        <dt>Status Information
        <dd><?php echo $host_status["plugin_output"] ?>
        <dt>Last Check Time
        <dd><?php echo date("c",$host_status["last_check"]) ?>
        <dt>Last Update
        <dd><?php echo date("c",$host_status["last_update"]) ?>
        <dt>Notifications
        <dd><?php echo $host_status["notifications_enabled"] == 1 ? "Enabled" : "Disabled" ?>
        <dt>Scheduled Downtime
        <dd><?php echo $host_status["scheduled_downtime_depth"] > 0 ? "Yes" : "No" ?>
      </dl>
    </div>

    <h2>Commands</h2>
    <ul data-role="listview" data-inset="true" data-theme="a">
      <li><a href="<?php echo $BASE_URL ?>?page=downtime&host_id=<?php echo $host_id ?>" data-transition="flip">Schedule downtime</a></li>
    </ul>

  </div>
</div>
<?php
}
?>

<!-- service ========================================================= -->
<?php
foreach ($status["hosts"] as $host_id => $host_status) {
    $host = $host_status["host_name"];
    if (isset($host_status["services"])) {
        foreach ($host_status["services"] as $service_id => $service_status) {
?>
<div data-role="page" id="<?php echo "${host_id}_${service_id}" ?>" data-theme="a">
  <div data-role="header">
    <h1><?php printf("%s - %s",$host,$service_status["service_description"]) ?></h1>
    <a href="#home" data-icon="home" data-iconpos="notext"  data-direction="reverse" class="ui-btn-right">Home</a>
  </div>

  <div data-role="content">
    <h2>Information</h2>
    <div class="information rounded">
      <dl>
        <dt>Status
        <dd class="<?php echo class_by_state($service_status["current_state"]) ?>"><?php echo $SERVICE_STATUS_BY[ $service_status["current_state"] ] ?> (for <?php echo stringize_duration(get_time_breakdown($status, $service_status)) ?>)
        <dt>Status Information
        <dd><?php echo $service_status["plugin_output"] ?>
        <dt>Last Check Time
        <dd><?php echo date("c",$service_status["last_check"]) ?>
        <dt>Last Update
        <dd><?php echo date("c",$service_status["last_update"]) ?>
        <dt>Notifications
        <dd class="<?php echo class_by_state($service_status["notifications_enabled"] == 1 ? STATE_OK : STATE_CRITICAL) ?>"><?php echo $service_status["notifications_enabled"] == 1 ? "Enabled" : "Disabled" ?>
        <dt>Scheduled Downtime
        <dd><?php echo $service_status["scheduled_downtime_depth"] > 0 ? "Yes" : "No" ?>
      </dl>
    </div>

    <h2>Commands</h2>
    <ul data-role="listview" data-inset="true" data-theme="a">
      <li><a href="<?php echo $BASE_URL ?>?page=downtime&host_id=<?php echo $host_id ?>&service_id=<?php echo $service_id ?>" data-transition="flip">Schedule downtime</a></li>
    </ul>

  </div>
</div>
<?php
        }
    }
}
?>

<?php
end_html();
}
?>

<?php
function view_downtime($global_stats, $status) {
    global $STATUS_FILE, $COMMAND_FILE;
    global $HOST_STATUS_BY, $SERVICE_STATUS_BY;
    global $BASE_URL;
    $host_id     = $_GET["host_id"];
    $host_status = $status["hosts"][$host_id];
    $host        = $host_status["host_name"];
    $service_id = $_GET["service_id"];
    $service_status;
    if (! isset($service_id)) {
        $id             = $host_id;
    } else {
        $id             = "${host_id}_${service_id}";
        $service_status = $host_status["services"][$service_id];
    }
    begin_html();
?>
<div data-role="page" id="dt_<?php echo $id ?>" data-theme="a">

  <div data-role="header">
    <h1>downtime</h1>
    <a href="<?php echo $BASE_URL ?>" data-icon="home" data-iconpos="notext"  data-direction="reverse" class="ui-btn-right">Home</a>
  </div>

  <div data-role="content">
    <h2>Information</h2>
    <div class="information rounded">
      <dl>
        <dt>Host</dt>
        <dd><?php echo $host ?></dd>
<?php
    if(isset($service_id)) {
        print "
        <dt>Serivice</dt>
        <dd>{$service_status['service_description']}</dd>";
    }
?>
      </dl>
    </div>

    <h2>Command Options</h2>
    <form
          id="dt_<?php echo $id ?>_form"
          data-dt_host="<?php echo $host ?>"
          data-dt_service="<?php echo isset($service_id) ? $service_status['service_description'] : '' ?>"
    >
      <div data-role="fieldcontain">
        <label for="dt_<?php echo $id ?>_comment">Comment:</label>
        <input type="text" name="dt_<?php echo $id ?>_comment" id="dt_<?php echo $id ?>_comment" value="schedule down" />
      </div>

      <div data-role="fieldcontain">
        <label for="dt_<?php echo $id ?>_end_date">End Time:</label>
        <select id="dt_<?php echo $id ?>_end_date">
<?php
for ($i=0, $t = time(); $i < 7; $t += 86400, $i++) {
    printf("      <option>%s</option>\n", strftime("%Y-%m-%d", $t));
}
?>
        </select>
        <select id="dt_<?php echo $id ?>_end_time">
<?php
$now_h = strftime("%H");
$now_m = strftime("%M");
if ($now_m < 30) {
    $now_m = 30;
} else {
    $now_h = intval( ($now_h+1)%24 );
    $now_m = 0;
}
$time_selected = sprintf("%02d:%02d", $now_h, $now_m);
$minutes = array(0,30);
for ($h=0; $h<24; $h++) {
    foreach ($minutes as $m) {
        $tm = sprintf("%02d:%02d", $h, $m);
        printf("      <option %s>%s</option>\n", ($tm === $time_selected ? "selected" : ""), $tm);
    }
}
?>
        </select>
      </div>
      <a href="#"
         class="submitDowntimeButton"
         data-role="button"
         data-theme="e"
         data-dt_id="<?php echo $id ?>"
      >Commit</a>
    </form>
    <div id="dt_<?php echo $id ?>_result" class="result">&nbsp;</div>
  </div>
</div>
<?php
end_html();
}
?>

<?php
function command_schedule_downtime($global_stats, $status) {
    global $COMMAND_FILE;

    if (! isset($_REQUEST["service"]) || $_REQUEST["service"] === "" ) {
        $command_elm = array("SCHEDULE_HOST_DOWNTIME",
                             $_REQUEST["host"],
            );
    } else {
        $command_elm = array("SCHEDULE_SVC_DOWNTIME",
                             $_REQUEST["host"],
                             $_REQUEST["service"],
            );
    }

    $end_time_str = $_REQUEST["end_date"]." ".$_REQUEST["end_time"];
    $end_time = strtotime($end_time_str);

    $command_elm = array_merge($command_elm,
                               array(
                                   time(),
                                   $end_time,
                                   1,
                                   0,
                                   0,
                                   'iPhone',
                                   $_REQUEST["comment"],
                                   ));
    $command = sprintf("[%lu] ",time()).implode(";", $command_elm);

    header("Content-Type: application/json; charset=UTF-8");
    $fh = @fopen($COMMAND_FILE, "w");
    if (!$fh) {
        trigger_error("unable to open file ($COMMAND_FILE): $php_errormsg", E_USER_WARNING);
        header("HTTP/1.0 500 Internal Server Error");
        print "{\"result\":false}\n";
        return;
    };
    fwrite($fh, $command."\n");
    fclose($fh);

    print "{\"result\":true}\n";
}
?>

<?php
function parse_status_file($status_file) {
    $status;

    $fh = fopen($status_file, "r");
    if (!$fh) {
        exit("unable to open file ($STATUS_FILE)");
    }

    $in_block = false;
    $block    = array();
    while (!feof($fh)) {
        $line = fgets($fh, 256);

        if (preg_match("/(\w+)\s+{/", $line, $matches)) {
            ### begin block
            #print($matches[1]."\n");
            $in_block = $matches[1];
            switch ($in_block) {
            case "contactstatus":
                # skip this block
                while (!feof($fh)) {
                    $line = fgets($fh, 256);
                    if (preg_match("/^\s*}/", $line)) {
                        break;
                    }
                }
                $in_block = false;
                break;
            }
            continue;
        } elseif (preg_match("/^\s*}/", $line)) {
            ### end block
            #var_dump($block);
            switch ($in_block) {
            case "info":
                $status["info"] = $block;
                break;
            case "programstatus":
                $status["program"] = $block;
                break;
            case "hoststatus":
                $host_id = strtr($block["host_name"], ".", "_");
                if (isset( $status["hosts"][ $host_id ] )) {
                    $status["hosts"][ $host_id ] = array_merge($status["hosts"][ $host_id ], $block);
                } else {
                    $status["hosts"][ $host_id ] = $block;
                }
                break;
            case "servicestatus":
                # suppose service_description is unique. so don't array_merge.
                $host_id    = strtr($block["host_name"], ".", "_");
                $service_id = strtr($block["service_description"], ". ", "__");
                $status["hosts"][ $host_id ]["services"][ $service_id ] = $block;
                break;
            case "servicecomment":
            case "servicedowntime":
            case "hostcomment":
            case "hostdowntime":
                # ignore
                break;
            default:
                trigger_error("unknown in_block: ".var_export($in_block, TRUE), E_USER_NOTICE);
                break;
            }
            $in_block = false;
            $block = array();
        } elseif ($in_block) {
            ### in block
            $line = trim($line);
            list ($k,$v) = explode("=", $line, 2);

            switch ($in_block) {
            case "info":
            case "programstatus":
                $block[$k] = $v;
                break;
            case "hoststatus":
                switch ($k) {
                case "host_name":
                case "current_state":
                case "last_check":
                case "last_update":
                case "last_state_change":
                case "notifications_enabled":
                case "check_command":
                case "plugin_output":
                case "scheduled_downtime_depth":
                    $block[$k] = $v;
                    break;
                }
                break;
            case "servicestatus":
                switch ($k) {
                case "host_name":
                case "current_state":
                case "service_description":
                case "plugin_output":
                case "last_check":
                case "notifications_enabled":
                case "last_update":
                case "scheduled_downtime_depth":
                    $block[$k] = $v;
                    break;
                }
                break;
            case "servicecomment":
            case "servicedowntime":
            case "hostcomment":
            case "hostdowntime":
                # ignore
                break;
            default:
                trigger_error("unknown in_block: ".var_export($in_block, TRUE), E_USER_NOTICE);
                break;
            }

        } else {
            ### ignore
            #trigger_error("ignore: ".var_export($line, TRUE), E_USER_NOTICE);
            continue;
        }

    }
    fclose($fh);

    return $status;
}

function calc_global_stats($status) {
    $global_stats = array(
        "host_up"          => 0,
        "host_down"        => 0,
        "host_unreachable" => 0,
        "service_ok"       => 0,
        "service_warning"  => 0,
        "service_critical" => 0,
        "service_unknown"  => 0,
        );

    while (list($host,$host_status) = each($status["hosts"])) {
        switch ($host_status["current_state"]) {
        case HOST_UP:
            $global_stats["host_up"]++;
            break;
        case HOST_DOWN:
            $global_stats["host_down"]++;
            break;
        case HOST_UNREACHABLE:
            $global_stats["host_unreachable"]++;
            break;
        }
        if (isset($host_status["services"])) {
            while (list($service_desc,$service_status) = each($host_status["services"])) {
                switch ($service_status["current_state"]) {
                case STATE_OK:
                    $global_stats["service_ok"]++;
                    break;
                case STATE_WARNING:
                    $global_stats["service_warning"]++;
                    break;
                case STATE_CRITICAL:
                    $global_stats["service_critical"]++;
                    break;
                case STATE_UNKNOWN:
                    $global_stats["service_unknown"]++;
                    break;
                }
            }
        }
    }

    return $global_stats;
}

function get_time_breakdown($status, $a_status) {
    $duration = array(
        days    => 0,
        horus   => 0,
        minutes => 0,
        seconds => 0,
        );
    $time = time();
    if ($a_status["last_state_change"] == 0) {
        $time -= $status["program"]["program_start"];
    } else {
        $time -= $a_status["last_state_change"];
    }

    $duration["days"] = intval($time/86400);
    $time -= $duration["days"]*86400;
    $duration["hours"] = intval($time/3600);
    $time -= $duration["hours"]*3600;
    $duration["minutes"] = intval($time/60);
    $time -= $duration["minutes"]*60;
    $duration["seconds"] = intval($time);

    return $duration;
}

function stringize_duration($duration) {
    return sprintf("%dd %dh %dm %ds", $duration["days"], $duration["hours"], $duration["minutes"], $duration["seconds"]);
}

function class_by_state($value) {
    switch ($value) {
    case STATE_OK:
        return "";
        break;
    case STATE_WARNING:
        return "warning";
        break;
    case STATE_CRITICAL:
        return "critical";
        break;
    case STATE_UNKNOWN:
        return "critical";
        break;
    default:
        return "unknown";
        break;
    }
}

function service_state_of($services) {
    $state = STATE_OK;
    foreach ($services as $service_id => $prop) {
        if (isset($prop['current_state'])) {
            if ($prop['current_state'] > $state) {
                $state = $prop['current_state'];
            }
        }
    }
    return $state + 0;
}

?>
