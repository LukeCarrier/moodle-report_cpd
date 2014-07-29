<?php
// This file is part of CPD Report for Moodle
//
// CPD Report for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CPD Report for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CPD Report for Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * This page lists CPD Activities the belong to the current user
 *
 * @package   admin-report-cpd
 * @copyright 2010 Kineo open Source
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once dirname(dirname(__DIR__)) . '/config.php';

require_once "{$CFG->libdir}/adminlib.php";
require_once "{$CFG->libdir}/tablelib.php";
require_once "{$CFG->libdir}/formslib.php";

require_once __DIR__ . '/cpd_filter_form.php';
require_once __DIR__ . '/lib.php';

require_login($SITE);
$usercontext = context_user::instance($USER->id);
require_capability('report/cpd:userview', $usercontext);

$PAGE->set_context($usercontext);
$PAGE->set_url($CFG->dirroot.'/report/cpd/index.php');
$output = $PAGE->get_renderer('report_cpd');

if ($delete_id = optional_param('delete', null, PARAM_INT)) {
    delete_cpd_record($delete_id);
}

$cpdyearid = optional_param('cpdyearid', null, PARAM_INT); // Current CPD year id
$download  = optional_param('download', null, PARAM_BOOL);
$print     = optional_param('print', null, PARAM_BOOL);

// CPD Report headers
$columns = array(
    'objective'        => get_string('objective', 'report_cpd'),
    'development_need' => get_string('developmentneed', 'report_cpd'),
    'activity_type'    => get_string('activitytype', 'report_cpd'),
    'activity'         => get_string('activity', 'report_cpd'),
    'due_date'         => get_string('datedue', 'report_cpd'),
    'start_date'       => get_string('datestart', 'report_cpd'),
    'end_date'         => get_string('dateend', 'report_cpd'),
    'status'           => get_string('status', 'report_cpd'),
    'timetaken'        => get_string('timetaken', 'report_cpd')
);

if (!empty($download) || !empty($print)) {
    $filter_data = (object) array(
        'cpdyearid'    => $cpdyearid,
        'filterbydate' => optional_param('filterbydate', null, PARAM_BOOL),
        'from'         => optional_param('from', null, PARAM_INT),
        'to'           => optional_param('to', null, PARAM_INT),
        'userid'       => $USER->id,
    );

    if (($cpd_records = get_cpd_records($filter_data)) && !empty($download)) {
        // Add disclaimer
        $cpd_records[] = array();
        $cpd_records[] = array(get_string('confirmstatement', 'report_cpd').':', '');
        $cpd_records[] = array(get_string('date').':', '');
        download_csv('cpd_record', $columns, $cpd_records);
        exit;
    }
} else {
    $columns['edit'] = get_string('edit');
    $columns['delete'] = get_string('delete');
}

$cpd_years = get_cpd_menu('years');
$userid = $USER->id;

$filter = new cpd_filter_form('index.php', compact('cpd_years', 'userid'), 'post', '', array('class' => 'cpdfilter'));
if (empty($cpd_records)) {
    $filter_data = $filter->get_data();
    if (empty($filter_data)) {
        $filter_data = (object) array(
            'userid'    => $USER->id,
            'cpdyearid' => empty($cpdyearid) ? get_current_cpd_year() : $cpdyearid, // Set cpd year id always needs to be set
            'from'      => null,
            'to'        => null,
        );
    }
    if (!$errors = validate_filter($filter_data)) {
        $cpd_records = get_cpd_records($filter_data, true);
    }
    $filter->set_data(compact('cpdyearid'));
} else {
    $filter->set_data((array) $filter_data);
}

$jsmodule = array(
    'name' => 'report_cpd',
    'fullpath' => '/report/cpd/module.js',
    'requires' => array('base', 'node')
);

if (empty($print)) {
    // Print the header.
    admin_externalpage_setup('cpdrecord');
    // Include styles
    $printparams = array_merge((array) $filter_data, array('print' => 1));
    $printlink   = new moodle_url('/report/cpd/index.php', $printparams);

    $PAGE->requires->string_for_js('printlandscape', 'report_cpd');
    $PAGE->requires->js_init_call('M.report_cpd.init', array(false, $printlink->out(false)));
} else {
    $PAGE->requires->css('/report/cpd/css/print.css');
    $PAGE->requires->js_init_call('M.report_cpd.init', array(true, null));
}
echo $OUTPUT->header();

if (!empty($errors)) {
    echo html_writer::tag('div', implode('<br />' , $errors), array('class' => 'box errorbox errorboxcontent'));
}
//$filter->set_data();
$filter->display();

// Add activity button
if ($cpd_years && $cpdyearid) {
    $buttonurl = new moodle_url('/report/cpd/edit_activity.php', array('cpdyearid' => $cpdyearid));
    echo $OUTPUT->single_button($buttonurl, get_string('addactivity', 'report_cpd'), 'get');
}

if (!empty($cpd_years[$cpdyearid])) {
    echo $OUTPUT->heading(get_string('cpdyeara', 'report_cpd', $cpd_years[$cpdyearid]), 4, 'printonly');
}

echo $OUTPUT->heading(fullname($USER), 3, 'printonly');
$table = new flexible_table('cpd');
$table->define_columns(array_keys($columns));
$table->define_headers(array_values($columns));
$table->column_style('edit', 'text-align', 'center');
$table->column_style('delete', 'text-align', 'center');
$table->column_class('edit', 'no_print_col');
$table->column_class('delete', 'no_print_col');
$table->define_baseurl($PAGE->url->out());

$table->sortable(false);
$table->collapsible(false);
$table->column_style_all('white-space', 'normal');
$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'attempts');
$table->set_attribute('class', 'generaltable boxalignleft cpd');

$table->setup();
if (!empty($cpd_records)) {
	foreach ($cpd_records as $cpd_record) {
	    $table->add_data($cpd_record);
	}
}

$table->finish_output();
if ($table->started_output) {
    if (!empty($print)) {
        // Disclaimer
        echo $output->disclaimer();
    }

    echo $output->export_controls($PAGE, $filter_data);
}

echo $OUTPUT->footer();
