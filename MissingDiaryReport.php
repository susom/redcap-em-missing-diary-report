<?php

namespace Stanford\MissingDiaryReport;
/** @var \Stanford\MissingDiaryReport\MissingDiaryReport $module */

use \Plugin as Plugin;
use \REDCap as REDCap;
use \Project as Project;
use \DateTime as DateTime;
use \Survey as Survey;
use \LogicTester as LogicTester;


class MissingDiaryReport extends \ExternalModules\AbstractExternalModule {



    public static function debug($obj) {
        // A really dumbed down logger for the template...
        error_log(json_encode($obj));
    }


    /**
     * @param $survey_fk_field
     * @param $all_surveys
     * @return array
     */
    static function getUniqueParticipants($survey_fk_field, $all_surveys) {
        //return unique single level array
        $pids = array();
        foreach ($all_surveys as $h) {
            $pids[] = $h[$survey_fk_field];
        }
        $unique_pids = array_unique($pids);

        return $unique_pids;

    }




    /**
     * Returns all surveys for a given record id
     *
     * @param $id  participant_id (if null, return all)
     * @param $cfg
     * @return mixed
     */
    static function getAllSurveys($id = null, $cfg) {
        // In the event the survey project is longitudinal, we need to use the event ID
        $survey_event_id = empty($cfg['SURVEY_EVENT_ARM_NAME']) ? NULL : StaticUtils::getEventIdFromName($cfg['SURVEY_PID'], $cfg['SURVEY_EVENT_ARM_NAME']);
        $survey_event_prefix = empty($cfg['SURVEY_EVENT_ARM_NAME']) ? "" : "[" . $cfg['SURVEY_EVENT_ARM_NAME'] . "]";

        if ($id == null) {
            $filter = null; //get all ids
        } else {
            $filter = $survey_event_prefix . "[{$cfg['SURVEY_FK_FIELD']}]='$id'";
        }

        $get_data = array(
            $cfg['SURVEY_PK_FIELD'],
            $cfg['SURVEY_FK_FIELD'],
            $cfg['SURVEY_TIMESTAMP_FIELD'],
            $cfg['SURVEY_DATE_FIELD'],
            $cfg['SURVEY_DAY_NUMBER_FIELD'],
            $cfg['SURVEY_FORM_NAME'] . '_complete'
        ) ;

        $q = REDCap::getData(
            $cfg['SURVEY_PID'],
            'json',
            NULL,
            $get_data,
            $survey_event_id,
            NULL,FALSE,FALSE,FALSE,
            $filter
        );

        $results = json_decode($q,true);
        //Plugin::log($results, "DEBUG", "RESULTS");
        return $results;
    }


    /**
     * @param $surveys
     * @param $portal_data
     * @param $portal_start_date_field
     * @param $survey_pk_field
     * @param $survey_fk_field
     * @param $survey_date_field
     * @param $survey_day_number_field
     * @param $survey_form_name_complete
     * @return array
     */
    static function arrangeSurveyByID($surveys, $portal_data, $portal_start_date_field,
                                      $survey_pk_field, $survey_fk_field, $survey_date_field,
                                      $survey_day_number_field, $survey_form_name_complete) {
        $arranged = array();

        foreach ($surveys as $c) {
            $id = $c[$survey_fk_field];
            $survey_date = $c[$survey_date_field];

            $arranged[$id][$survey_date] = array(
                "START_DATE"   => $portal_data[$id][$portal_start_date_field],
                "RECORD_NAME"  => $c[$survey_pk_field],
                "DAY_NUMBER"   => $c[$survey_day_number_field],
                "STATUS"       => $c[$survey_form_name_complete]
            );
        }

        return $arranged;

    }


    /**
     * @param $valid_day_number_array
     * @param $survey_data
     * @param $start_date
     * @return array
     */
    static function getValidDayNumbers($valid_day_number_array, $survey_data, $start_date) {
                                       //$start_field, $start_field_event, $valid_day_number_array) {
//        $start_date = StaticUtils::getFieldValue($pk, $project_id, $start_field, $start_field_event);
        Plugin::log($survey_data, "DEBUG", "VALID DAY NUMBERS");
        $valid_days = array();

        foreach ($valid_day_number_array as $day) {
            $date = self::getDateFromDayNumber($start_date,$day);
            Plugin::log($date, "DEBUG", "WORKING ON THIS DATE");
//            $valid_days[$date] = array(
//                "START_DATE" => $start_date,
//                "RECORD_NAME" => $pk . "-" . "D" . $day,
//                "DAY_NUMBER" => $day
//            );
            $valid_days[$date]['STATUS'] = isset($survey_data[$date]['STATUS']) ? $survey_data[$date]['STATUS'] : "-1";
            $valid_days[$date]['DAY_NUMBER'] = $day;
        }


        return $valid_days;
    }



    /**
     * Gets the date based on a start_date and days offset
     *
     * @param $start_date
     * @param $day
     * @param string $format
     * @return string
     */
    static function getDateFromDayNumber($start_date, $day, $format = "Y-m-d") {
        $this_dt = new \DateTime($start_date);
        $this_dt->modify("+$day day");
        return $this_dt->format($format);
    }

    /**
     * Gets the date based on a start_date and days offset
     * @param $start_date
     * @param $this_date
     * @return string
     */
    static function getDayNumberFromDate($start_date, $this_date) {
        $start_dt = new \DateTime($start_date);
        $this_dt = new \DateTime($this_date);
        $delta = $start_dt->diff($this_dt);
        $day = $delta->format('%r%a');	// Get the datediff in days
        return $day;
    }

    function startBootstrapPage($title, $header = '') {

        $html=<<<EOD
<!DOCTYPE html>
    <html>
        <head>
            <title>$title</title>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <meta name='apple-mobile-web-app-title' content='$title'>
            <link rel='apple-touch-icon' href='favicon/apple-touch-icon-iphone-60x60.png'>
            <link rel='apple-touch-icon' sizes='60x60' href='favicon/apple-touch-icon-ipad-76x76.png'>
            <link rel='apple-touch-icon' sizes='114x114' href='favicon/apple-touch-icon-iphone-retina-120x120.png'>
            <link rel='apple-touch-icon' sizes='144x144' href='favicon/apple-touch-icon-ipad-retina-152x152.png'>

            <!-- Bootstrap core CSS -->
            <link href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css' rel='stylesheet' media='screen'>
            <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
            <!--[if lt IE 9]>
                <script src='https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js'></script>
                <script src='https://oss.maxcdn.com/respond/1.4.2/respond.min.js'></script>
            <![endif]-->
            <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
            <script src='https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js'></script>
            $header
        </head>
        <body>
EOD;
        return $html;
    }

    function endBootstrapPage($footer = "") {
        $html=<<<EOD
            <!-- Include all compiled plugins (below), or include individual files as needed -->
            <script src='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js'></script>
            $footer
        </body>
    </html>
EOD;
        return $html;
    }

# When there is an error - display a nice message to end-user in bootstrap format and exit
    function exitMessage($msg, $title="", $include_bookmark_help = false) {

        $body = "
            <div class='container'>
                <div class='jumbotron text-center'>
                    <p>$msg</p>
                </div>
            </div>";
        if ($include_bookmark_help) $body .= $this->getBookmarkHelp();

        print $this->startBootstrapPage($title) . $body . $this->endBootstrapPage();
        exit();
    }

    /**
     * Get the bookmark html
     * @return string
     */
    function getBookmarkHelp() {
        $html=<<<EOD
    <div class='container text-center'>
        <p>You may bookmark this page to your home screen for faster access in the future.</p>
        <div class='text-center'>
            <span><a href='#Instructions' class='btn btn-default' data-toggle='collapse'>Show Instructions</a></span>
            <div id='Instructions' class='collapse'>
                <br/>
                <div class='panel panel-default'>
                    <div class='panel-body text-left'>
                        <p><strong>On an iOS phone</strong></p>
                         <ol>
                             <li>If you do not see the toolbar at the bottom of your Safari window, tap once at the bottom of your screen</li>
                             <li>Click on the Action button in the center of the toolbar (a square box with an upward arrow)</li>
                             <li>Scroll the lower row of options to the right until you see 'Add to Home Screen' (a box with a plus sign).</li>
                         </ol>
                         <p><strong>On an Android phone</strong></p>
                         <ol>
                             <li>Open the Chrome menu <img src="//lh3.googleusercontent.com/vOgJaWNbkf_Y0kOEQXe4wSlufkMuTb8NqGMIXSP-mRm72oR4ABGkR1L4sXyMmb7lBHnz=h18" width="auto" height="18" alt="More" title="More">.</li>
                             <li>Tap the star icon <img src="//lh3.ggpht.com/SEdDjoaQ-qufNcDGhJh5KXW0q3-tABnuWjM5fpqE9kbOyJaXN3co5MEcQu7kqoCIqHA5O84=w20" width="20" height="18" alt="Bookmark" title="Bookmark">.</li>
                             <li>Optional: If you want to edit the bookmark's name and URL or change the folder, go to the bottom bar and tap&nbsp;<strong>Edit</strong>.</li>
                             <li>When you're done, tap the checkmark .</li>
                             <li>Optional:  If you want to make the bookmark appear on your home screen (like an app) open the bookmarks folder and <strong>press and hold</strong> your finger on the bookmark.  A new menu will appear with an option to Add to Home screen</li>
                         </ol>
                         <p><strong>On an Android tablet</strong></p>
                         <ol>
                             <li>In the address bar at the top, tap the star icon&nbsp;<img src="//lh3.ggpht.com/SEdDjoaQ-qufNcDGhJh5KXW0q3-tABnuWjM5fpqE9kbOyJaXN3co5MEcQu7kqoCIqHA5O84=w20" width="20" height="18" alt="Bookmark" title="Bookmark">.</li>
                             <li>Optional: If you want to edit the bookmark's name and URL or change the folder, go to the bottom bar and tap&nbsp;<strong>Edit</strong>.</li>
                             <li>When you're done, tap the checkmark .</li>
                             <li>Optional:  If you want to make the bookmark appear on your home screen (like an app) open the bookmarks folder and <strong>press and hold</strong> your finger on the bookmark.  A new menu will appear with an option to Add to Home screen</li>
                         </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
EOD;
        return $html;
    }


}