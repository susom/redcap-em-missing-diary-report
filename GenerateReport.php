<?php
namespace Stanford\MissingDiaryReport;
/** @var \Stanford\MissingDiaryReport\MissingDiaryReport $module */

use \REDCap as REDCap;
use \Plugin as Plugin;

//include "common.php";
include_once("classes/StaticUtils.php");

$begin = '';
$end = '';

//check if in context of record. if not report error
//Plugin::log($project_id, "DEBUG", "PROJECT ID");


if(isset($_POST['submit']))
{

    $begin = new \DateTime($_POST["start_date"]);
    $end = new \DateTime($_POST["end_date"]);
    $today = new \DateTime();
    if ($end > $today) {
        $end = $today;
    }
    $begin_str = $begin->format('Y-m-d');
    $end_str = $end->format('Y-m-d');

}


if ($end != '') {

    $interval = \DateInterval::createFromDateString('1 day');
    $period = new \DatePeriod($begin, $interval, $end);

    foreach ($period as $dt) {
        $dates[] = $dt->format("Y-m-d");
    }
    $cfg_orig  = $module->getProjectSettings($project_id);

    //convert the $cfg into the version like the em
    $cfg = convertConfigToArray($cfg_orig);
    //Plugin::log($cfg, "DEBUG", "CONFIG");


    ///////////// NEW WAY SINCE TAKING TOO LONG  /////////////////////
    //1. Get all survey data from survey project
    $surveys = MissingDiaryReport::getAllSurveys(null, $cfg);
    //Plugin::log($surveys, "DEBUG", "ALL SURVEYS");

    //2. Get list of participants from surveys
    $participants = MissingDiaryReport::getUniqueParticipants($cfg['SURVEY_FK_FIELD'], $surveys);
    //Plugin::log($participants, "DEBUG", "ALL PARTICIPANTS");

    //3. Get survey portal data from main project
    $portal_fields = array(REDCap::getRecordIdField(),$cfg['START_DATE_FIELD']);
    $portal_data_orig = StaticUtils::getFieldValues($project_id, $portal_fields, $cfg['START_DATE_EVENT']);
    //rearrange so that the id is the key
    $portal_data = StaticUtils::makeFieldArrayKey($portal_data_orig, REDCap::getRecordIdField());
    //Plugin::log($portal_data, "DEBUG", "ALL PORTAL DATA");

    //4. reorganize so that it's keyed by id - survey_date
    $surveys_by_id = MissingDiaryReport::arrangeSurveyByID($surveys, $portal_data, $cfg['START_DATE_FIELD'],
        $cfg['SURVEY_PK_FIELD'], $cfg['SURVEY_FK_FIELD'], $cfg['SURVEY_DATE_FIELD'], $cfg['SURVEY_DAY_NUMBER_FIELD'],
        $cfg['SURVEY_FORM_NAME'].'_complete');
    //Plugin::log($surveys_by_id, "DEBUG", "ARRANGED SURVEYS BY ID");

    $valid_day_number_array = StaticUtils::parseRangeString($cfg['VALID_DAY_NUMBERS']);


    //assemble the table and fill in the missed required days
    //participant on y axis
    //survey status on x axis
    $table_data = array();

    foreach ($participants as $participant) {
        $table_data[$participant] = MissingDiaryReport::getValidDayNumbers($valid_day_number_array,
            $surveys_by_id[$participant], $portal_data[$participant][$cfg['START_DATE_FIELD']]
            );
    }


    $table_header = array_merge(array("Participant"), $dates);

}


function convertConfigToArray($cfg) {
    $flattened = array();
    foreach ($cfg as $key => $val) {
        $flattened[strtoupper($key)] = $val['value'];
    }
    return $flattened;
}

/**
 * Renders straight table without attempting to decode
 * @param  $id
 * @param array $header
 * @param  $data
 * @return string
 */
function renderParticipantTable($id, $header = array(), $data, $date_window) {
    // Render table
    $grid = '<table id="' . $id . '" class="table table-striped table-bordered table-condensed" cellspacing="0" width="95%">';
    $grid .= renderHeaderRow($header, 'thead');
    $grid .= renderSummaryTableRows($data, $date_window);
    $grid .= '</table>';

    return $grid;
}

function renderHeaderRow($header = array(), $tag) {
    $row = '<' . $tag . '><tr>';
    foreach ($header as $col_key => $this_col) {
        $row .= '<th>' . $this_col . '</th>';
    }
    $row .= '</tr></' . $tag . '>';
    return $row;
}

function renderSummaryTableRows($row_data, $date_window) {

    $rows = '';

    foreach ($row_data as $participant => $dates) {
        $rows .= '<tr><td>' . $participant. '</td>';

        foreach ($date_window as $required_date) {

            $status = $dates[$required_date]['STATUS'];
            $day_num = $dates[$required_date]['DAY_NUMBER'];

            $status_unscheduled = '';
            $status_blue = '<button type="button" class="btn btn-info btn-circle"><i class="glyphicon"></i><b>'.$day_num.'</b></button>';
            $status_yellow = '<button type="button" class="btn btn-warning btn-circle"><i class="glyphicon"></i><b>'.$day_num.'</b></button>';
            $status_green = '<button type="button" class="btn btn-success btn-circle"><i class="glyphicon"></i><b>'.$day_num.'</b></button>';
            $status_red = '<button type="button" class="btn btn-danger btn-circle"><i class="glyphicon"></i><b>'.$day_num.'</b></button>';

            switch ($status) {
                    case "-1":
                        $rows .= '<td>' . $status_red .  '</td>';
                        break;
                    case '0':
                        $rows .= '<td>' . $status_yellow . '</td>';
                        break;
                    case '1':
                        $rows .= '<td>' . $status_blue . '</td>';
                        break;
                    case '2':
                        $rows .= '<td>' . $status_green . '</td>';
                        break;
                    default:
                        $rows .= '<td>' . $status_unscheduled . '</td>';
                }


        }
        $rows .= '</tr>';
    }
    return $rows;
}

//display the table
//include "pages/report_page.php";

?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $module->getModuleName()?></title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>

    <!-- Bootstrap core CSS -->
    <link rel="stylesheet" type="text/css" media="screen" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php print $module->getUrl("favicon/stanford_favicon.ico",false,true) ?>">

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="<?php print $module->getUrl("js/jquery-3.2.1.min.js",false,true) ?>"></script>

    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js'></script>

    <!-- Bootstrap Date-Picker Plugin -->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>


    <!-- Add local css and js for module -->
</head>
<body>
<div class="container">
    <div class="jumbotron">
        <h3>Missed Diary Report</h3>
    </div>
    <form method="post">
    <div class="well">
        <div class="container">
            <div class='col-md-4'>
                <div class="form-group">
                    <label>START</label>
                    <div class='input-group date' id='datetimepicker6'>
                        <input name="start_date" type='text' class="form-control" />
                        <span class="input-group-addon">
                    <span class="glyphicon glyphicon-calendar"></span>
                </span>
                    </div>
                </div>
            </div>
            <div class='col-md-4'>
                <div class="form-group">
                    <label>END</label>
                    <div class='input-group date' id='datetimepicker7'>
                        <input name="end_date" type='text' class="form-control" />
                        <span class="input-group-addon">
                    <span class="glyphicon glyphicon-calendar"></span>
                </span>
                    </div>
                </div>
            </div>
        </div>
        <input class="btn btn-primary" type="submit" value="START" name="submit">
    </div>
    </form>

</div>

<div class="container">
    <?php print renderParticipantTable("summary", $table_header, $table_data, $dates) ?>
</div>
</body>

<script type = "text/javascript">

    $(document).ready(function(){
        console.log("hello");
        $('#datetimepicker6').datepicker({
            format: 'yyyy-mm-dd'

        });
        $('#datetimepicker7').datepicker({
            format: 'yyyy-mm-dd'
        });

        $('input[name="start_date"]').val("<?php echo $begin_str?>");
        $('input[name="end_date"]').val("<?php echo $end_str?>");

    });

</script>


