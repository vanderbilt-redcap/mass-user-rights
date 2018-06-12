<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 6/6/2018
 * Time: 4:23 PM
 */
function getUserList($project_id) {
	$userlist = array();
	$sql = "SELECT d2.username,CONCAT(d2.user_firstname, ' ', d2.user_lastname) as name
		FROM redcap_user_rights d
		JOIN redcap_user_information d2
			ON d.username = d2.username
		WHERE d.project_id=$project_id";
	$result = db_query($sql);
	while ($row = db_fetch_assoc($result)) {
		$userlist[$row['username']] = $row['name'];
	}
	return $userlist;
}

/*function getDAGList($project_id) {
	$dagList = array();
	$sql = "SELECT group_id, group_name
		FROM redcap_data_access_groups
		WHERE project_id=$project_id";
	$result = db_query($sql);
	while ($row = db_fetch_assoc($result)) {
		$dagList[$row['group_id']] = $row['group_name'];
	}
	return $dagList;
}*/

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

function generatePrefill($data) {
	$returnString = "";

	foreach ($data as $index => $rightsData) {
		$returnString .= "$('#roles_addbutton').click();
				var rowCount = $('.add_new_right_table').length;
				$('#add_new_right_role_'+rowCount).val('" . $index . "').trigger('onchange').ready(function() {setTimeout(function() {\n";
				foreach ($rightsData as $projectID => $roledID) {
					$returnString .= "$(\"#projectid_\"+rowCount+\"_$projectID\").prop(\"checked\",true);\n";
				}
				$returnString .= "},350);});";
		/*if ($rightsData['exempt'] == "on") {
		    $returnString .= "$('#exempt_check_'+rowCount).prop('checked',true);";
        }*/
	}
	/*foreach ($dags as $dagIndex => $dagID) {
		$returnString .= "$('input[id^=\"dagid_$dagID\"][value=\"".$dagID."\"]').prop('checked',true);";
	}*/
	return $returnString;
}

function getProjectName($projectID) {
	$sql = "SELECT app_title 
            FROM redcap_projects
            WHERE project_id=$projectID";
	return db_result(db_query($sql),0);
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
	$roleInfo = array();
	$sql = "SELECT * FROM redcap_user_roles WHERE project_id=$projectID AND role_id=$roleID";
	$roleInfo = db_fetch_assoc(db_query($sql),0);

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
		$updateFields .= $column."='".$value."'";
		$insertFields .= "'".$value."'";
		$insertColumns .= $column;
		$firstRow = false;
	}

	$returnArray = array();
	foreach ($userIDs as $userID) {
		$insertsql = "INSERT INTO redcap_user_rights ($insertColumns,username)
			VALUES ($insertFields,'$userID')
			ON DUPLICATE KEY UPDATE $updateFields";
		$returnArray[] = db_query($insertsql);
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
	$sql = "SELECT d.role_id,d2.role_name
			FROM redcap_user_rights d
			LEFT JOIN redcap_user_roles d2
				ON d.project_id=d2.project_id AND d.role_id=d2.role_id
			WHERE d.username='$userID'
			AND d.project_id IN ('".implode("','",$projectListing)."')";
	//echo "$sql<br/>";
	$result = db_query($sql);
	while ($row = db_fetch_assoc($result)) {
		$currentRoleName = $row['role_name'];
		$currentRoleID = $row['role_id'];
		if (!in_array($currentRoleName,$roleNamesFound) && $currentRoleName != "" && $currentRoleID != "") {
			$roleNamesFound[] = $currentRoleName;
			$currentRolesWithName = getRolesWithName($currentRoleID);
			$returnArray[$currentRoleID] = $currentRolesWithName;
		}
	}
	return $returnArray;
}