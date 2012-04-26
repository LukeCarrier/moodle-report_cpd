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
 * Updates CPD Report
 *
 * @package   admin-report-cpd                                               
 * @copyright 2010 Kineo open Source                                         
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
function xmldb_report_cpd_upgrade($oldversion = 0) {
	global $CFG, $DB;
	
	$result = true;
	if ($oldversion < 2009080103) {
		//$result = install_from_xmldb_file(dirname(__FILE__).'/install.xml');
	}
	
	// Add default statuses
	if ($result) {
		$statuses = array (
				'Not Started',
				'Started',
				'Objective Met' //'Completed'
				);
		
		foreach ($statuses as $status) {
			if (!$DB->record_exists('cpd_status', array('name' => $status))) {
				$data = new stdClass;
				$data->name = $status;
				$result = $result && $DB->insert_record('cpd_status', $data, false);
			}
		}
	}
	
	if ($result && $oldversion < 2010012700) {
		$cpd_status = new XMLDBTable('cpd_status');
		$display_order = new XMLDBField('display_order');
		// Add order column
		if (! field_exists($cpd_status, $display_order)) {
			$result = table_column('cpd_status', null, 'display_order', 'integer', '10', 'unsigned', '0', 'null', '');
			if ($result) {
				// Set the display order (this is something specific for MySQL)
				execute_sql('set @row = 0');
				$result = execute_sql("update {$CFG->prefix}cpd_status set display_order = (@row := @row + 1)");
			}
		}
	}
	
	// Add Time taken field
	if ($result && $oldversion < 2010011402) {
		$table = new XMLDBTable('cpd');
		$field = new XMLDBField('timetaken');
		$field->setAttributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, null, null, 'cpdyearid');
		$result = $result && $DB->add_field($table, $field);
	}
	
	// Add default Activity Types
	if ($result && $oldversion < 2010012800) {
		$activity_types = array (
				'Attendence in college/university',
				'Computer based training',
				'Conferences',
				'Discussions',
				'Examination',
				'Individual informal study',
				'Mentoring',
				'On-the-job training',
				'Professional Institute',
				'Reading',
				'Self-managed learning',
				'Seminars',
				'Structured discussions',
				'Training course');
		
		foreach ($activity_types as $at) {
			if (!$DB->record_exists('cpd_activity_type', array('name' => $at))) {
				$data = new stdClass;
				$data->name = $at;
				$result = $result && $DB->insert_record('cpd_activity_type', $data, false);
			}
		}
	}
	
	// Add default CPD Years
	if ($result && $oldversion < 2010012801) {
		$cpd_years = array (
				array ('startdate' => 1262304000, 'enddate' => 1293839999),
				array ('startdate' => 1293840000, 'enddate' => 1325375999),
				array ('startdate' => 1325376000, 'enddate' => 1356998399)
				);
		
		foreach ($cpd_years as $year) {
			$year = (object) $year;
			if (!$DB->record_exists('cpd_year', array('startdate' => $year->startdate, 'enddate' => $year->enddate))) {
				$result = $result && $DB->insert_record('cpd_year', $year, false);
			}
		}
	}

	return $result;
}
?>
