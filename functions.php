<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 6/6/2018
 * Time: 4:23 PM
 */
function getUserList($projectList) {
    global $module;
	$userlist = array();
	$sql = "SELECT DISTINCT(d2.username),CONCAT(d2.user_lastname, ', ', d2.user_firstname) as name
		FROM redcap_user_rights d
		JOIN redcap_user_information d2
			ON d.username = d2.username
		WHERE d.project_id IN (".implode(",",$projectList).")
		ORDER BY username";
	//echo "$sql<br/>";
	$result = $module->query($sql);
	while ($row = db_fetch_assoc($result)) {
		$userlist[$row['username']] = $row['name'];
	}
	return $userlist;
}

function getRoleList($project_id) {
    global $module;
	$roleList = array();
	$sql = "SELECT role_id, role_name
		FROM redcap_user_roles
		WHERE project_id=$project_id
		ORDER BY role_name";
	$result = $module->query($sql);
	while ($row = db_fetch_assoc($result)) {
		$roleList[$row['role_id']] = $row['role_name'];
	}
	return $roleList;
}

function getDAGList($project_id) {
    global $module;
	$dagList = array();
	$sql = "SELECT group_id,group_name
		FROM redcap_data_access_groups
		WHERE project_id=$project_id
		ORDER BY group_name";
	$result = $module->query($sql);
	while ($row = db_fetch_assoc($result)) {
		$dagList[$row['group_id']] = $row['group_name'];
	}
	return $dagList;
}

function getRecordList($project_id,$recordField) {
    global $module;
	$recordList = array();
	$sql = "SELECT DISTINCT(record)
        FROM redcap_data
        WHERE project_id=$project_id";
	$result = $module->query($sql);
	//$resultCount = 0;
	while ($row = db_fetch_assoc($result)) {
		$recordList[$row['record']] = $row['record'];
		//$resultCount++;
	}
	return $recordList;
}

function getRoleAccess($roleID) {
    global $module;
	$accessList = array();

	$sql = "SELECT data_entry
        FROM redcap_user_roles
        WHERE role_id=$roleID";
	$dataEntry = db_result($module->query($sql),0);
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
		elseif ($rightsData['role'] == "") {
		    $returnString .= "$('#role_select_$project_id option[value=\"\"]').attr('selected','selected').change();";
        }
	}
	foreach ($suggestedAssignments['roles'] as $project_id => $roleID) {
        if ($roleID != "") {
            $bgColor = getBackgroundColor($data[$project_id]['role'], $roleID);
            $roleName = getRoleName($roleID);
            $returnString .= "$('#role_select_$project_id').parent().css('background-color','$bgColor').append('Suggested: $roleName');";
        }
    }
    foreach ($suggestedAssignments['dags'] as $project_id => $dagID) {
        if ($dagID != "") {
            $bgColor = getBackgroundColor($data[$project_id]['dag'], $dagID);
            $dagName = getDAGName($dagID);
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
    global $module;
    $accessProject = new \Project($accessProjectID);
    $accessEventID = $accessProject->firstEventId;
	$returnArray = array();
	/*$sql = "SELECT d2.value
			FROM redcap_data d 
			JOIN redcap_data d2
				ON d.project_id=d2.project_id AND d.event_id=d2.event_id AND d.record=d2.record AND d2.field_name='custom_role'
			WHERE d.project_id=$accessProjectID
			AND d.field_name='user_name'
			AND d.value='$userID'
			LIMIT 1";
	$roleID = db_result($module->query($sql),0);*/
	$sql = "SELECT record
	    FROM redcap_data
	    WHERE project_id=$accessProjectID
	    AND field_name='user_name'
	    AND value='$userID'";
	$recordID = db_result(db_query($sql),0);

    $returnData = \REDCap::getData($accessProjectID, 'array', array($recordID));

    $roleID = "";
    foreach ($returnData[$recordID] as $event_id => $eventData) {
        if ($event_id == "repeat_instances") {
            foreach ($eventData as $instanceEvent => $formData) {
                foreach ($formData['user_modifications'] as $instanceID => $instanceData) {
                    if ($instanceData['role_v2'] != "") {
                        if ($instanceData['expire_date'] != "" && strtotime($instanceData['expire_date']) < time()) continue;
                        $roleID = $instanceData['role_v2'];
                    }
                }
            }
        }
        else {
            if ($eventData['custom_role'] != "") {
                $roleID = $eventData['custom_role'];
            }
        }
    }

	if ($roleID != "") {
		$roleData = array();
		$sql2 = "SELECT field_name,value
		FROM redcap_data
		WHERE project_id=$roleProjectID
		AND record='$roleID'";
		$result = $module->query($sql2);
		while ($row = db_fetch_assoc($result)) {
			$roleData[$row['field_name']] = $row['value'];
		}

		//TODO Make sure the modification date on the instances are in order, need to take most recent modification over others
		if ($roleData['role_active'] == "1") {
		    $suggestAssignments = json_decode($roleData['project_role'],true);

		    foreach ($suggestAssignments as $projectID => $suggested) {
		        if ($suggested['role'] != "") {
		            $returnArray['roles'][$projectID] = $suggested['role'];
                }
                if ($suggested['dag'] != "") {
                    $returnArray['dags'][$projectID] = $suggested['dag'];
                }
            }
			/*$returnArray['roles'] = json_decode($roleData['suggested_roles'],true);
			$returnArray['dags'] = json_decode($roleData['suggested_dags'],true);*/
		}
	}
	return $returnArray;
}

function getProjectName($projectID) {
    global $module;
	$sql = "SELECT app_title 
            FROM redcap_projects
            WHERE project_id=$projectID";
	return db_result($module->query($sql),0);
}

function getRoleName($roleID) {
    global $module;
	$returnString = "";
	$sql = "SELECT role_name
	FROM redcap_user_roles
	WHERE role_id='$roleID'";
	$returnString = db_result($module->query($sql),0);
	return $returnString;
}

function getDAGName($dagID) {
    global $module;
	$returnString = "";
	$sql = "SELECT group_name
	FROM redcap_data_access_groups
	WHERE group_id='$dagID'";
	$returnString = db_result($module->query($sql),0);
	return $returnString;
}

function getRolesWithName($roleID) {
    global $module;
	$roleList = array();
	$sql = "SELECT project_id,role_id,role_name
			FROM redcap_user_roles
			WHERE role_name = (SELECT role_name FROM redcap_user_roles WHERE role_id=$roleID)";
	$result = $module->query($sql);
	while ($row = db_fetch_assoc($result)) {
		$roleList[$row['project_id']] = $row['role_id'];
	}
	return $roleList;
}

function updateUserRole($userIDs,$roleID,$projectID) {
    global $module;
	$returnArray = array();
	if ($projectID != "" && is_numeric($projectID)) {
        if ($roleID != "" && is_numeric($roleID)) {
            $sql = "SELECT * FROM redcap_user_roles WHERE project_id='" . db_real_escape_string($projectID) . "' AND role_id='" . db_real_escape_string($roleID) . "'";
            $roleInfo = db_fetch_assoc($module->query($sql), 0);

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
            $insertFields = "NULL,'" . db_real_escape_string($projectID) . "'";
            $updateFields = "role_id = NULL";
        }
        foreach ($userIDs as $userID) {
            $insertsql = "INSERT INTO redcap_user_rights ($insertColumns,username)
			VALUES ($insertFields,'" . db_real_escape_string($userID) . "')
			ON DUPLICATE KEY UPDATE $updateFields";
            //echo "$insertsql<br/>";
            $returnArray[] = $module->query($insertsql);
        }
    }

	return $returnArray;
}

function updateUserDAG($userIDs,$dagID,$projectID) {
    global $module;
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
			$returnArray[] = $module->query($insertsql);
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
	sort($projectListing);
	return array_unique($projectListing);
}

function getProjectWithRoleName($roleID) {
    global $module;
	$projectList = array();

	$sql = "SELECT d.role_id, d.project_id,d2.app_title
			FROM redcap_user_roles d
			LEFT JOIN redcap_projects d2
				ON d.project_id=d2.project_id
			WHERE d.role_name=(SELECT role_name FROM redcap_user_roles WHERE role_id=$roleID)";
	$result = $module->query($sql);
	//echo "$sql<br/>";
	while ($row = db_fetch_assoc($result)) {
		$projectList[$row['project_id']] = $row['app_title'];
	}
	return $projectList;
}

function userAssignedProjects($userID) {
	global $projectListing,$module;
	$returnArray = array();
	$roleNamesFound = array();
	$sql = "SELECT project_id,role_id,group_id
			FROM redcap_user_rights
			WHERE username='$userID'
			AND project_id IN ('".implode("','",$projectListing)."')";
	//echo "$sql<br/>";
	$result = $module->query($sql);
	while ($row = db_fetch_assoc($result)) {
		$returnArray[$row['project_id']]['role'] = $row['role_id'];
		$returnArray[$row['project_id']]['dag'] = $row['group_id'];
	}
	return $returnArray;
}