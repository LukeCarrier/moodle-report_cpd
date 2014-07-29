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
 * Library functions used by the CPD Report
 *
 * @package   admin-report-cpd
 * @copyright 2010 Kineo open Source
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns CPD Report based on filter.
 *
 * @param object $filter Filters include CPD Year, Date and User
 * @param array $editable If true this will add 'Edit' and 'Delete' columns to the resultset
 * @param array $extra Extra columns include 'user_name'; to add Users' name to the resultset
 * @param array $returned returns the summary
 * @return array
 */
function get_cpd_records($filter = null, $editable = false, $extra = array(), &$returned = null)
{
	global $CFG, $USER, $DB, $OUTPUT;

	/*
	 * If we're editable, prepare some URLs and icons we'll need later
	 */

	$deleteicon = new action_link(new moodle_url('/report/cpd/index.php', array(
		'delete'    => null,
		'cpdyearid' => null,
	)), new pix_icon('t/delete', get_string('delete')));

	$editicon = new action_link(new moodle_url('/report/cpd/edit_activity.php', array(
		'id'        => null,
		'cpdyearid' => null,
	)), new pix_icon('t/edit', get_string('edit')));

	// Make sure we're working with an object, else we'll cause warnings later
	if ($returned === null) {
		$returned = new stdClass();
	}

	$sql = <<<EOS
	SELECT
		c.id,
		c.userid,
		c.objective,
		c.development_need,
		att.name AS activitytype,
		c.activity,
		c.duedate,
		c.startdate,
		c.enddate,
		s.name as status,
		c.timetaken,
		c.cpdyearid,
		u.firstname,
		u.lastname
	FROM
		{cpd} c
	JOIN
		{user} u
		ON c.userid = u.id
	LEFT JOIN
		{cpd_activity_type} att
		ON c.activitytypeid = att.id
	LEFT JOIN
		{cpd_status} s
		ON c.statusid = s.id
EOS;
	$where = null;
	$filter_sql = null;
	if ($filter)
	{
		if (!empty($filter->userid))
		{
			$where[] = "c.userid = {$filter->userid}";
		}
		if (!empty($filter->activitytypeid))
		{
			$where[] = "c.activitytypeid = {$filter->activitytypeid}
			";
		}
		if (!empty($filter->cpdyearid))
		{
			$where[] = "c.cpdyearid = {$filter->cpdyearid}
			";
		}
		if (!empty($filter->filterbydate) && (!empty($filter->from) || !empty($filter->to)))
		{
			if ($filter->from && empty($filter->to))
			{
				$where[] = "(
				c.duedate >= {$filter->from}
				or
				c.startdate >= {$filter->from}
				or
				c.enddate >= {$filter->from}
				)
				";
			}
			else if ($filter->to && empty($filter->from))
			{
				$to = $filter->to + ((60 * 60 * 24) - 1);
				$where[] = "(
				c.duedate < {$to}
				or
				c.startdate < {$to}
				or
				c.enddate < {$to}
				)
				";
			}
			else if ($filter->from && $filter->to)
			{
				$to = $filter->to + (60 * 60 * 24) - 1;
				if ($filter->from < $to)
				{
					$where[] = "(
						(c.duedate >= {$filter->from} AND c.duedate <= {$to})
						OR
						(c.startdate >= {$filter->from} AND c.startdate <= {$to})
						OR
						(c.enddate >= {$filter->from} AND c.enddate <= {$to})
						)
					";
				}
			}
		}
	}
	if (!is_null($where))
	{
		$filter_sql = " WHERE " . implode(" AND ", $where);
		$sql .= $filter_sql;
	}
	$sql .= "
	ORDER BY
		u.lastname,
		u.firstname,
		c.duedate,
		c.id
	";
	$results = $DB->get_records_sql($sql);
	$table_data = null;
	if ($results)
	{
		foreach ($results as $row)
		{
			$duedate = ($row->duedate) ? date("d-m-Y", $row->duedate) : '';
			$startdate = ($row->startdate) ? date("d-m-Y", $row->startdate) : '';
			$enddate = ($row->enddate) ? date("d-m-Y", $row->enddate) : '';
			$timetaken = '';
			if (!empty($row->timetaken)) {
				$minutes = $row->timetaken % 60;
				$hours = ($row->timetaken - $minutes) / 60;
				$timetaken = (($hours) ? $hours : '0') .':'. (($minutes) ? $minutes : '00');
			}

			$row_data = array();
			if (isset($extra['user_name']) && $extra['user_name'])
			{
				$row_data[] = "$row->firstname $row->lastname";
			}
			array_push($row_data, $row->objective, $row->development_need, $row->activitytype, $row->activity, $duedate, $startdate, $enddate, $row->status, $timetaken);

			if ($editable) {
				$deleteicon->url->param('delete', $row->id);
				$editicon->url->param('id', $row->id);

				$row_data[] = $OUTPUT->render($editicon);
				$row_data[] = $OUTPUT->render($deleteicon);
			}
			$table_data[] = $row_data;
		}
		
		// Set results count
		$returned->result_count = count($results);
		// Set user count
		$returned->user_count = $DB->count_records_sql("
				SELECT 	COUNT(*) 
				FROM	mdl_cpd c
				JOIN	{$CFG->prefix}user u
					ON c.userid = u.id
				LEFT JOIN
					{$CFG->prefix}cpd_activity_type att
					ON c.activitytypeid = att.id
				LEFT JOIN
					{$CFG->prefix}cpd_status s
					ON c.statusid = s.id 
				{$filter_sql}
				GROUP BY
					u.id");
	}
	return $table_data;
}


/**
 * Validates filters
 *
 * @param object $filter Filters include CPD Year, Date and User
 * @return array
 */
function validate_filter(&$filter)
{
	if (! $filter) { return false; }
	$errors = null;
	if ($filter->from && $filter->to)
	{
		if ($filter->from > $filter->to)
		{
			$errors[] = get_string('invaliddatefrom', 'cpd_report');
		}
	}
	return $errors;
}


/**
 * Deletes the specified CPD Activity
 *
 * @param int $id CPD Activity id
 * @return array
 */
function delete_cpd_record($id)
{
	global $USER, $DB;
	return $DB->delete_records('cpd', array('id'=>$id, 'userid'=>$USER->id));
}


/**
 * Creates and Downloads a CSV file
 *
 * @param string $filename Name of the CSV file. Do not include .csv extension.
 * @param int $headers CPD Activity id
 * @param int $data CPD Report Dataset
 * @return array
 */
function download_csv($filename, $headers, $data)
{
	$filename .= ".csv";

	header("Content-Type: application/download\n");
	header("Content-Disposition: attachment; filename=\"$filename\"");
	header("Expires: 0");
	header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
	header("Pragma: public");

	$header = '"' . implode('","', $headers).'"';
	echo $header . "\n";

	foreach ((array)$data as $row)
	{
		$text = '"' . implode('","', $row).'"';
		echo $text . "\n";
	}
	exit;
}

/**
 * Returns a CPD Metadata item as a list of id=>name pairs
 *
 * @param string $name	CPD Metadata item name
 * @return array
 */
function get_cpd_menu($name)
{
	global $DB;
	
	$cpd_menu = null;
	switch ($name)
	{
		case 'years':
			$cpd_years = $DB->get_records('cpd_year', null, 'startdate asc, enddate asc', '*');
			if ($cpd_years)
			{
				foreach ($cpd_years as $year)
				{
					$cpd_menu[$year->id] = date("d/m/Y", $year->startdate) . " - " . date("d/m/Y", $year->enddate);
				}
			}
		break;
		case 'activity_types':
			$cpd_activity_types = $DB->get_records('cpd_activity_type',null, 'name asc', '*');
			if ($cpd_activity_types)
			{
				foreach($cpd_activity_types as $cpd_activity_type)
				{
					$cpd_menu[$cpd_activity_type->id] = $cpd_activity_type->name;
				}
			}
		break;
		case 'statuses':
			$table = 'cpd_status';
			$cpd_statuses = $DB->get_records($table, null, 'display_order asc','*');
			if ($cpd_statuses)
			{
				foreach($cpd_statuses as $cpd_status)
				{
					$cpd_menu[$cpd_status->id] = $cpd_status->name;
				}
			}
		break;
	}
	return $cpd_menu;
}


/**
 * Returns current cpd year id
 *
 * @return int or false if the CPD Years table is empty
 */
function get_current_cpd_year()
{
	global $CFG, $DB;
	$sql = "
		SELECT	id
		FROM	{$CFG->prefix}cpd_year
		WHERE	unix_timestamp() BETWEEN startdate AND enddate
		ORDER BY
			startdate ASC
		LIMIT 1
	";
	$result = $DB->get_record_sql($sql);
	if (empty($result))
	{
		// If current cpd year is in the past
		$sql = "
			select	id
			from	{$CFG->prefix}cpd_year
			order by
				enddate desc
			limit 	1
		";
		$result = $DB->get_record_sql($sql);
	}

	if (empty($result))
	{
		return false; // Return false if still empty
	}
	else
	{
		return $result->id;
	}
}


/**
 * Prints a button used to print a CPD Report
 *
 * @param string $page Page name
 * @param object $filter_data Current filter data
 */
function print_print_button($page, $filter_data = null) {
	global $CFG;
	$link = $page;
	if (!empty($filter_data)) {
		$link .= '?' . get_query_string(array("print" => 1) + ((array)$filter_data));
	}

	echo '<form action="'.$page.'" method="get" target="_blank" onsubmit="return false;">';
	echo '<input type="hidden" name="print" value="1" />';
	echo '<input id="print_button" type="submit" value="Print" onclick="window.open(\''.$link.'\', \'\', \'resizable=yes toolbar=no, location=no\');" />';
	echo '</form>';
}


/**
 * Helper function which returns a URL query string of the specified params
 *
 * @param array $params
 * @return string
 */
function get_query_string($params = array()){
	$arr = array();
	foreach ($params as $key => $val){
	   $arr[] = urlencode($key)."=".urlencode($val);
	}
	return implode($arr, "&amp;");
}


/**
 * Processes a CPD Metadata item form.
 *
 * @param string $frm	CPD Metadata form name
 * @return boolean Result
 */
function process_meta_form($frm)
{
	global $CFG, $DB;
	switch ($frm)
	{
		case 'activitytype':
			$name = optional_param('activitytype', NULL,PARAM_RAW);
			if ( empty($name) )
			{
				$errors[] = get_string('invalidtype', 'report_cpd');
				break;
			}
			$data = new stdClass;
			$data->name = $name;
			$frmid = optional_param('frmid', NULL, PARAM_INT);
			if ( empty($frmid) )
			{
				$DB->insert_record('cpd_activity_type', $data);
			}
			else
			{
				$data->id = $frmid;
				$DB->update_record('cpd_activity_type', $data);
			}
			break;
		case 'cpdyears':
			$startday = optional_param('startday','',PARAM_RAW);
			$startmonth = optional_param('startmonth','',PARAM_RAW);
			$startyear = optional_param('startyear','',PARAM_RAW);
			$endday = optional_param('endday','',PARAM_RAW);
			$endmonth = optional_param('endmonth','',PARAM_RAW);
			$endyear = optional_param('endyear','',PARAM_RAW);
			
			$starttime = strtotime("{$startday}-{$startmonth}-{$startyear}");
			$endtime = strtotime("{$endday}-{$endmonth}-{$endyear}");

			if ($starttime && $endtime)
			{
				$endtime += ((60 * 60 * 24) -1); //Add 23:59:59
				if ($starttime > $endtime)
				{
					$errors[] = get_string('invaliddatestart', 'report_cpd');
					break;
				}
				$data = new stdClass;
				$data->startdate = $starttime;
				$data->enddate = $endtime;
				$frmid = optional_param('frmid', NULL,PARAM_INT);
				if ( empty($frmid) )
				{
					$DB->insert_record('cpd_year', $data);
				}
				else
				{
					$data->id = $frmid;
					$DB->update_record('cpd_year', $data);
				}
			}
			else
			{
				$errors[] = get_string('invaliddate', 'report_cpd');
			}
			break;
		case 'status':
			$name = optional_param('status', NULL, PARAM_RAW);
			if ( empty($name) )
			{
				$errors[] = get_string('emptystatus', 'report_cpd');
				break;
			}
			else if ($old_status = $DB->get_record('cpd_status', array('name'=>$name)))
			{
				$errors[] = get_string('uniquestatus', 'report_cpd');
				break;
			}
			$data = new stdClass;
			$data->name = $name;
			$frmid = optional_param('frmid', NULL, PARAM_INT);
			if ( empty($frmid) )
			{
				// Set display order as well
				$results = $DB->get_records_sql("select (max(display_order) + 1) as bottom from {cpd_status}");
				$data->display_order = current($results) ? current($results)->bottom : 1;
				$DB->insert_record('cpd_status', $data);
			}
			else
			{
				$data->id = $frmid;
				$DB->update_record('cpd_status', $data);
			}
	}

	if (isset($errors))
	{
		return $errors;
	}
}

/**
 * Deletes the specified CPD Metadata item
 *
 * @param string $table Metadata table name
 * @param string $id Metadata item id
 * @return boolean Result
 */
function delete_meta_record($table, $id)
{
	global $DB;
	if (empty($id)) { return false; }
	$result = false;

	switch ($table)
	{
		case 'activitytype':
			$result = $DB->delete_records('cpd_activity_type', array('id'=>$id));
			break;
		case 'year':
			$result = $DB->delete_records('cpd_year', array('id'=>$id));
			break;
		case 'status':
			$result = $DB->delete_records('cpd_status', array('id'=>$id));
			break;
	}
	return $result;
}


/**
 * Changes display order of a CPD Metadata item
 *
 * @param string $table Metadata table name
 * @param string $table Metadata item id
 * @param string $move should be 'up' or 'down'
 */
 // TODO: Should be applied to all Metadata items. This only works with Statuses for the moment
function change_display_order($table, $id, $move)
{
	global $DB;
	if (empty($id)) { return false; }

	$table_name = NULL;
	switch ($table)
	{
		case 'status':
			$table_name = 'cpd_' . $table;
			$results = $DB->get_records($table_name, null,'display_order asc','*');
			$update_row1 = NULL;
			$update_row2 = NULL;
			$row = current($results);
			while ($row)
			{
				if ($row->id == $id)
				{
					$update_row1 = $row;
					if ($move == 'up')
					{
						$update_row2 = prev($results);
					}
					else if ($move == 'down')
					{
						$update_row2 = next($results);
					}
					break;
				}
				$row = next($results);
			}
	}

	if (!empty($table_name) && !empty($update_row1) && !empty($update_row2))
	{
		$new_display_order = $update_row2->display_order;
		// Swap the order
		$update_row2->display_order = $update_row1->display_order;
		$update_row1->display_order = $new_display_order;
		
		$DB->update_record($table_name, $update_row1);
		$DB->update_record($table_name, $update_row2);
	}
}


/**
 * Returns a specified Metadata item
 *
 * @param string $table Metadata item table name
 * @param int $id Metadata item id
 * @return array
 */
function get_meta_records($table, $id)
{
	global $DB;
	if (empty($id)) { return false; }

	switch ($table)
	{
		case 'activitytype':
			return $DB->get_record('cpd_activity_type', array('id'=>$id));
		case 'year':
			return $DB->get_record('cpd_year', array('id'=>$id));
		case 'status':
			return $DB->get_record('cpd_status', array('id'=>$id));
	}
}


/**
 * Processes CPD Activity form
 *
 * @param object $data CPD Activity form data
 * @param string $redirect Redirects to this URL if the form was processed successfully.
 * @return array An array of errors (if any)
 */
function process_activity_form(&$data, $redirect)
{
	global $USER, $CFG, $DB;

	$data->userid = $USER->id;
	
	if (! $cpdyear = $DB->get_record('cpd_year', array('id'=>$data->cpdyearid))) {
		error('Invalid CPD Year');
	}
	
	if ($status = $DB->get_record('cpd_status', array('id'=>$data->statusid)))
	{
		if (strtoupper($status->name) == 'OBJECTIVE MET' && empty($data->enddate))
		{
			$data->enddate = time(); // Set end date to today
		}
		else
		{
			$data->enddate = NULL; // Set end date to null if Status isn't 'completed'
		}
	}
	if (checkdate($data->duedate['m'], $data->duedate['d'], $data->duedate['Y']))
	{
		$data->duedate = strtotime("{$data->duedate['Y']}-{$data->duedate['m']}-{$data->duedate['d']}");
		if ($data->duedate < $cpdyear->startdate || $data->duedate > $cpdyear->enddate)
		{
			$errors[] = get_string('duedatewithin', 'report_cpd').' ('.date("d M Y", $cpdyear->startdate) . ' - ' .  date("d M Y", $cpdyear->enddate).').';
		}
	}
	if (checkdate($data->startdate['m'], $data->startdate['d'], $data->startdate['Y']))
	{
		$data->startdate = strtotime("{$data->startdate['Y']}-{$data->startdate['m']}-{$data->startdate['d']}");
		if ($data->startdate < $cpdyear->startdate || $data->startdate > $cpdyear->enddate)
		{
			$errors[] = get_string('startdatewithin', 'report_cpd').' ('.date("d M Y", $cpdyear->startdate) . ' - ' .  date("d M Y", $cpdyear->enddate).').';
		}
	}
	if (!empty($data->timetaken['hours']) || !empty($data->timetaken['minutes'])) {
		// Covert to minutes
		$data->timetaken = ($data->timetaken['hours'] * 60) + $data->timetaken['minutes'];
	} else {
		$data->timetaken = null;
	}
	if (empty($errors))
	{
		if (!empty($data->id)) // Just make sure
		{
			$result = $DB->update_record('cpd', $data);
		}
		else
		{
			$result = $DB->insert_record('cpd', $data);
		}
		if ($result)
		{
			redirect($redirect);
			exit;
		}
		else
		{
			$errors[] = get_string('noupdate', 'report_cpd');
		}
	}
	return $errors;
}
?>
