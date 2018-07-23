<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 6/6/2018
 * Time: 4:23 PM
 */
function getUserList($projectList) {
	$userlist = array();
	$sql = "SELECT DISTINCT(d2.username),CONCAT(d2.user_firstname, ' ', d2.user_lastname) as name
		FROM redcap_user_rights d
		JOIN redcap_user_information d2
			ON d.username = d2.username
		WHERE d.project_id IN (".implode(",",$projectList).")";
	$result = db_query($sql);
	while ($row = db_fetch_assoc($result)) {
		$userlist[$row['username']] = $row['name'];
	}
	return $userlist;
}

function getRoleList($project_id) {
	$roleList = array();
	$sql = "SELECT role_id, role_name
		FROM redcap_user_roles
		WHERE project_id=$project_id";
	$result = db_query($sql);
	while ($row = db_fetch_assoc($result)) {
		$roleList[$row['role_id']] = $row['role_name'];
	}
	return $roleList;
}

function getDAGList($project_id) {
	$dagList = array();
	$sql = "SELECT group_id,group_name
		FROM redcap_data_access_groups
		WHERE project_id=$project_id";
	$result = db_query($sql);
	while ($row = db_fetch_assoc($result)) {
		$dagList[$row['group_id']] = $row['group_name'];
	}
	return $dagList;
}

function getRecordList($project_id,$recordField) {
	$recordList = array();
	$sql = "SELECT DISTINCT(record)
        FROM redcap_data
        WHERE project_id=$project_id";
	$result = db_query($sql);
	//$resultCount = 0;
	while ($row = db_fetch_assoc($result)) {
		$recordList[$row['record']] = $row['record'];
		//$resultCount++;
	}
	return $recordList;
}

function getRoleAccess($roleID) {
	$accessList = array();

	$sql = "SELECT data_entry
        FROM redcap_user_roles
        WHERE role_id=$roleID";
	$dataEntry = db_result(db_query($sql),0);
	$accessList[$roleID] = processDataEntry($dataEntry);

	return $accessList;
}

function processDataEntry($dataentry) {
	$accessByRole = array();
	preg_match_all("/\[(.*?)\]/",$dataentry,$matchRegEx);

	foreach ($matchRegEx[1] as $roleID => $accessLevel) {
		$splitAccess = explode(",",$accessLevel);
		$accessByRole[$splitAccess[0]] = $splitAccess[1];
	}
	return $accessByRole;
}

function generatePrefill($data,$suggestedAssignments) {
	$returnString = "";

	foreach ($data as $project_id => $rightsData) {
		if ($rightsData['dag'] != "") {
			$returnString .= "$('#dag_select_$project_id option[value=".$rightsData['dag']."]').attr('selected','selected').change();";
		}
		if ($rightsData['role'] != "") {
			$returnString .= "$('#role_select_$project_id option[value=".$rightsData['role']."]').attr('selected','selected').change();";
		}
		if ($suggestedAssignments['roles'][$project_id] != "") {
			$bgColor = getBackgroundColor($rightsData['role'],$suggestedAssignments['roles'][$project_id]);
			$roleName = getRoleName($suggestedAssignments['roles'][$project_id]);
			$returnString .= "$('#role_select_$project_id').parent().css('background-color','$bgColor').append('Suggested: $roleName');";
		}
		if ($suggestedAssignments['dags'][$project_id] != "") {
			$bgColor = getBackgroundColor($rightsData['dag'],$suggestedAssignments['dags'][$project_id]);
			$dagName = getDAGName($suggestedAssignments['dags'][$project_id]);
			$returnString .= "$('#dag_select_$project_id').parent().css('background-color','$bgColor').append('Suggested: $dagName');";
		}
	}
	/*foreach ($dags as $dagIndex => $dagID) {
		$returnString .= "$('input[id^=\"dagid_$dagID\"][value=\"".$dagID."\"]').prop('checked',true);";
	}*/
	return $returnString;
}

function getBackgroundColor($userAssignment,$suggestedAssignment) {
	$color = "green";
	if ($userAssignment == "" && $suggestedAssignment != "") {
		$color = "red";
	}
	elseif ($userAssignment != "" && $suggestedAssignment != "" && $userAssignment != $suggestedAssignment) {
		$color = "yellow";
	}
	return $color;
}

function getSuggestedAssignments($userID, $accessProjectID, $roleProjectID) {
	$returnArray = array();
	$sql = "SELECT d2.value
			FROM redcap_data d 
			JOIN redcap_data d2
				ON d.project_id=d2.project_id AND d.event_id=d2.event_id AND d.record=d2.record AND d2.field_name='role'
			WHERE d.project_id=$accessProjectID
			AND d.field_name='user_name'
			AND d.value='$userID'
			LIMIT 1";
	$roleID = db_result(db_query($sql),0);
	//TODO Need to include checks at some point that the person is not expired and has an active role in the repeating instrument
	if ($roleID != "") {
		$roleData = array();
		$sql2 = "SELECT field_name,value
		FROM redcap_data
		WHERE project_id=$roleProjectID
		AND record='$roleID'";
		$result = db_query($sql2);
		while ($row = db_fetch_assoc($result)) {
			$roleData[$row['field_name']] = $row['value'];
		}
		if ($roleData['role_active'] == "1") {
			$returnArray['roles'] = json_decode($roleData['suggested_roles'],true);
			$returnArray['dags'] = json_decode($roleData['suggested_dags'],true);
		}
	}
	return $returnArray;
}

function getProjectName($projectID) {
	$sql = "SELECT app_title 
            FROM redcap_projects
            WHERE project_id=$projectID";
	return db_result(db_query($sql),0);
}

function getRoleName($roleID) {
	$returnString = "";
	$sql = "SELECT role_name
	FROM redcap_user_roles
	WHERE role_id='$roleID'";
	$returnString = db_result(db_query($sql),0);
	return $returnString;
}

function getDAGName($dagID) {
	$returnString = "";
	$sql = "SELECT group_name
	FROM redcap_data_access_groups
	WHERE group_id='$dagID'";
	$returnString = db_result(db_query($sql),0);
	return $returnString;
}

function getRolesWithName($roleID) {
	$roleList = array();
	$sql = "SELECT project_id,role_id,role_name
			FROM redcap_user_roles
			WHERE role_name = (SELECT role_name FROM redcap_user_roles WHERE role_id=$roleID)";
	$result = db_query($sql);
	while ($row = db_fetch_assoc($result)) {
		$roleList[$row['project_id']] = $row['role_id'];
	}
	return $roleList;
}

function updateUserRole($userIDs,$roleID,$projectID) {
	$returnArray = array();
	if ($projectID != "" && is_numeric($projectID)) {
		if ($roleID != "" && is_numeric($roleID)) {
			$sql = "SELECT * FROM redcap_user_roles WHERE project_id='".db_real_escape_string($projectID)."' AND role_id='".db_real_escape_string($roleID)."'";
			$roleInfo = db_fetch_assoc(db_query($sql), 0);

			$updateFields = "";
			$insertFields = "";
			$insertColumns = "";
			$firstRow = true;
			foreach ($roleInfo as $column => $value) {
				if ($column == "role_name") continue;
				if ($updateFields != "") {
					$updateFields .= ",";
				}
				if (!$firstRow) {
					$insertFields .= ",";
				}
				if ($insertColumns != "") {
					$insertColumns .= ",";
				}
				$updateFields .= $column . "='" . $value . "'";
				$insertFields .= "'" . $value . "'";
				$insertColumns .= $column;
				$firstRow = false;
			}
		} else {
			$insertColumns = "role_id,project_id";
			$insertFields = "NULL,'".db_real_escape_string($projectID)."'";
			$updateFields = "role_id = NULL";
		}

		foreach ($userIDs as $userID) {
			$insertsql = "INSERT INTO redcap_user_rights ($insertColumns,username)
			VALUES ($insertFields,'" . db_real_escape_string($userID) . "')
			ON DUPLICATE KEY UPDATE $updateFields";
			//echo "$insertsql<br/>";
			$returnArray[] = db_query($insertsql);
		}
	}
	return $returnArray;
}

function updateUserDAG($userIDs,$dagID,$projectID) {
	$returnArray = array();
	if ($projectID != "" && is_numeric($projectID)) {
		$dagValue = "NULL";
		if ($dagID != "" && is_numeric($dagID)) {
			$dagValue = "'".db_real_escape_string($dagID)."'";
		}
		$insertColumns = "group_id,project_id";
		$insertFields = "$dagValue,'".db_real_escape_string($projectID)."'";
		$updateFields = "group_id = $dagValue";

		foreach ($userIDs as $userID) {
			$insertsql = "INSERT INTO redcap_user_rights ($insertColumns,username)
			VALUES ($insertFields,'$userID')
			ON DUPLICATE KEY UPDATE $updateFields";
			$returnArray[] = db_query($insertsql);
		}
	}
	return $returnArray;
}

function getFullProjectList($projectIDs, $fieldsWithProjects) {
	global $Proj;
	$projectListing = $projectIDs;
	$projectID = $Proj->project_id;
	$recordList = getRecordList($projectID,$Proj->table_pk);

	foreach ($recordList as $recordID) {
		$recordData = \REDCap::getData($projectID, 'array',array($recordID),$fieldsWithProjects);
		foreach ($recordData[$recordID] as $projectIDFields) {
			foreach ($fieldsWithProjects as $fieldWithProjectID) {
				if ($projectIDFields[$fieldWithProjectID] != "" && is_numeric($projectIDFields[$fieldWithProjectID])) {
					$projectListing[] = $projectIDFields[$fieldWithProjectID];
				}
			}
		}
	}
	return $projectListing;
}

function getProjectWithRoleName($roleID) {
	$projectList = array();

	$sql = "SELECT d.role_id, d.project_id,d2.app_title
			FROM redcap_user_roles d
			LEFT JOIN redcap_projects d2
				ON d.project_id=d2.project_id
			WHERE d.role_name=(SELECT role_name FROM redcap_user_roles WHERE role_id=$roleID)";
	$result = db_query($sql);
	//echo "$sql<br/>";
	while ($row = db_fetch_assoc($result)) {
		$projectList[$row['project_id']] = $row['app_title'];
	}
	return $projectList;
}

function userAssignedProjects($userID) {
	global $projectListing;
	$returnArray = array();
	$roleNamesFound = array();
	$sql = "SELECT project_id,role_id,group_id
			FROM redcap_user_rights
			WHERE username='$userID'
			AND project_id IN ('".implode("','",$projectListing)."')";
	//echo "$sql<br/>";
	$result = db_query($sql);
	while ($row = db_fetch_assoc($result)) {
		$returnArray[$row['project_id']]['role'] = $row['role_id'];
		$returnArray[$row['project_id']]['dag'] = $row['group_id'];
	}
	return $returnArray;
}