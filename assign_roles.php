<style type='text/css'>
    .picklist {
        width:90%;
        text-overflow: ellipsis;
        overflow: hidden;
        margin-top:5px;
        margin-bottom:5px;
    }
</style>

<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 1/16/2018
 * Time: 5:38 PM
 */
require_once("base.php");
$projectID = $_GET['pid'];

global $projectListing,$module;
$module = new \Vanderbilt\MassUserRightsExternalModule\MassUserRightsExternalModule($projectID);
$projectListing = getFullProjectList($module->getProjectSetting('role-projects'),$module->getProjectSetting('field-projects'));

if ($projectID != "") {
	//global $redcap_version;
    global $Proj;
	//require_once(dirname(dirname(dirname(__FILE__)))."/redcap_v$redcap_version/Config/init_project.php");
	require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	/*$HtmlPage = new HtmlPage();
	$HtmlPage->PrintHeaderExt();*/

	$userList = getUserList($projectListing);

	//$dagList = getDAGList($projectID);
	//$roleList = getRoleList($projectID);

	/*$userProjectID = $module->getRightsProjectID();
	$userProject = new \Project($userProjectID);
	$event_id = $module->getFirstEventId($userProjectID);

	$groupData = \REDCap::getData($userProjectID, 'array', "", array(), $event_id, array(), false, false, false, "([" . $module->getProjectSetting('project-field') . "] = '$projectID')");

	$groupList = array();
	$usersByGroup = array();
	foreach ($groupData as $group) {
	    if ($group[$event_id][$module->getProjectSetting('group-field')] != "" && !in_array($group[$event_id][$module->getProjectSetting('group-field')],$groupList)) {
	        $groupList[$group[$event_id][$module->getProjectSetting('group-field')]] = $group[$event_id][$module->getProjectSetting('group-field')];
        }
        $usersByGroup[$group[$event_id][$module->getProjectSetting('group-field')]][] = $group[$event_id][$module->getProjectSetting('user-field')];
    }*/

	//$rightsModule = new \Vanderbilt\UserRightsByRecordExternalModule\UserRightsByRecordExternalModule($projectID);

	echo "<table class='table table-bordered'><tr><th>Select a User to Apply Rights</th></tr>
    <form action='".$module->getUrl('assign_roles.php')."' method='POST'>
        <tr><td>
            <input type='radio' id='user_radio' name='assign_type' ".($_POST['assign_type'] == "individual" || $_POST['assign_type'] == "" ? "checked": "")." value='individual'>Individual User
        </td></tr><tr><td>
        <div id='user_div' ".($_POST['assign_type'] != "individual" && $_POST['assign_type'] != "" ? "style='display:none;'": "").">
        <select name='select_user'>";
	foreach ($userList as $userName => $realName) {
		echo "<option value='$userName' ".($_POST['select_user'] == $userName ? "selected" : "").">$realName ($userName)</option>";
	}
	echo "</select>
		    <input type='submit' value='Load User' name='load_user'/>
		</div>
	</td></tr></form></table>";

	if (isset($_POST['update_rights']) && $_POST['update_rights'] != "") {
	    $roleAssigns = array();

		if (is_array($_POST['select_user'])) {
			$userIDs = $_POST['select_user'];
		}
		else {
			$userIDs = array($_POST['select_user']);
		}

	    $projectIDs = $_POST['projectid'];
	    if (is_array($_POST['add_new_right_role'])) {
			$roleAssigns = $_POST['add_new_right_role'];
		}
		$resultsArray = array();
	    foreach ($roleAssigns as $index => $roleAssign) {
	        if ($roleAssign == "" || !is_numeric($roleAssign)) continue;
	        $roleList = getRolesWithName($roleAssign);
	        foreach ($roleList as $projectID => $roleID) {
	            if (isset($projectIDs[$index][$projectID]) && $projectIDs[$index][$projectID] == $projectID) {
	                $resultsArray = updateUserRole($userIDs,$roleID,$projectID);
	                $negativeResult = false;
	                foreach ($resultsArray as $result) {
	                    if ($result != "1") {
	                        $negativeResult = true;
                        }
                    }
                    if ($negativeResult) {
						echo "Had a negative result assigning user to <strong>" . getRoleList($projectID)[$roleID] . "</strong> on project <strong>" . getProjectName($projectID) . "</strong>.<br/>";
					}
					else {
						echo "Successfully assigned user to <strong>" . getRoleList($projectID)[$roleID] . "</strong> on project <strong>" . getProjectName($projectID) . "</strong>.<br/>";
                    }
                }
            }
        }
	    /*$postArray = array();

	    if (is_array($_POST['select_user'])) {
	        $userIDs = $_POST['select_user'];
			echo "<script>
            $(document).ready(function() {
            	$('#group_radio').trigger('onclick');
            });";
			echo "</script>";
        }
        else {
			$userIDs = array($_POST['select_user']);
		}

	    foreach ($_POST["add_new_right_role"] as $index => $value) {
	        $postArray[$index]['role'] = $value;
        }

        $dagAssigns = implode(",",$_POST['dagid']);

        foreach ($_POST['recordid'] as $index => $value) {
            foreach ($value as $key=>$subValue) {
				$postArray[$index]['record'][$subValue] = $subValue;
			}
        }

		$groupAssign = "";
		if ($_POST['select_group'] != "" && $_POST['select_group'] != "new") {
			$groupAssign = db_real_escape_string($_POST['select_group']);
		}
		elseif ($_POST['new_group'] != "") {
			$groupAssign = db_real_escape_string($_POST['new_group']);
		}

	    $json_encoder = json_encode($postArray);

        foreach ($userIDs as $userID) {
			$data = \REDCap::getData($userProjectID, 'array', "", array(), $event_id, array(), false, false, false, "([" . $module->getProjectSetting('project-field') . "] = '$projectID' and [" . $module->getProjectSetting('user-field') . "] = '" . $userID . "')");
			$recordID = "";
			if (empty($data)) {
				$recordID = $module->getAutoId($userProjectID);
			} else {
				foreach ($data as $record_id => $recordData) {
					$recordID = $record_id;
				}
			}

			$module->saveData($userProjectID, $recordID, $event_id, array($module->getProjectSetting("access-field") => $json_encoder, $module->getProjectSetting("project-field") => $projectID, $module->getProjectSetting("user-field") => $userID, $module->getProjectSetting("group-field") => $groupAssign, $module->getProjectSetting("dag-field") => $dagAssigns));
		}
		if ($groupAssign != "") {
			foreach ($usersByGroup[$groupAssign] as $user) {
			    if (!in_array($user,$userIDs)) {
					$removedata = \REDCap::getData($userProjectID, 'array', "", array(), $event_id, array(), false, false, false, "([" . $module->getProjectSetting('project-field') . "] = '$projectID' and [" . $module->getProjectSetting('user-field') . "] = '" . $user . "')");
                    foreach ($removedata as $record_id => $recordData) {
                        $recordID = $record_id;
                    }

					\Records::saveData($userProjectID, 'array', [$recordID => [$event_id => array($module->getProjectSetting("group-field") => "")]],'overwrite');
                }
			}
		}*/
	}
	elseif (isset($_POST['load_user']) && $_POST['select_user'] != "") {
		$userID = db_real_escape_string($_POST['select_user']);
		//$data = \REDCap::getData($userProjectID, 'array', $userID);
		/*$data = \REDCap::getData($userProjectID, 'array', "", array(), $event_id, array(), false, false, false, "([" . $module->getProjectSetting('project-field') . "] = '$projectID' and [" . $module->getProjectSetting('user-field') . "] = '".$userID."')");

		foreach ($data as $recordID => $recordData) {
		    $customRights = json_decode($recordData[$event_id][$module->getProjectSetting("access-field")],true);
			$dagAssigns = explode(",",$recordData[$event_id][$module->getProjectSetting("dag-field")]);
        }*/

		//$customRights = json_decode($data[1][$event_id][$module->getProjectSetting("access-field")], true);
        //$userCurrentRoles = getUserRoles($userID, );

        $hiddenFields = array('select_user'=>$userID);
		drawRightsTables($hiddenFields,$module->getUrl('assign_roles.php'));
        $userAssignedRoles = userAssignedProjects($userID);

		if (!empty($userAssignedRoles)) {
		    echo "<script>
            $(document).ready(function() {
                ".generatePrefill($userAssignedRoles)."
                });
             </script>";
        }
	}
}

function drawRightsTables($hiddenFields,$destination)
{
    global $module,$projectListing;
	/*$projectListing = $module->getProjectSetting('role-projects');
	$fieldWithProjectIDs = $module->getProjectSetting('field-projects');*/
	$userRolesProjects = array();
	$projectNames = array();
    /*$projectID = $Proj->project_id;
	$recordList = getRecordList($projectID,$Proj->table_pk);*/
	$roleList = array();
	$roleAccess = array();
	/*foreach ($recordList as $recordID) {
		$recordData = \REDCap::getData($projectID, 'array',array($recordID),$fieldWithProjectIDs);
		foreach ($recordData[$recordID] as $projectIDFields) {
			foreach ($fieldWithProjectIDs as $fieldWithProjectID) {
				if ($projectIDFields[$fieldWithProjectID] != "" && is_numeric($projectIDFields[$fieldWithProjectID])) {
					$projectListing[] = $projectIDFields[$fieldWithProjectID];
				}
			}
		}
	}*/

	foreach ($projectListing as $listProjectID) {
		$listRole = getRoleList($listProjectID);
		$roleList = $listRole + $roleList;
		$projectNames[$listProjectID] = getProjectName($listProjectID);
		foreach ($listRole as $roleID => $roleName) {
		    $roleAccess = $roleAccess + getRoleAccess($roleID);
			if (!in_array($listProjectID,$userRolesProjects[$roleName])) {
				$userRolesProjects[$roleName][] = $listProjectID;
			}
		}
	}

	ksort($roleList);

	echo "<script type='text/javascript'>
		var roles = \"";
	foreach (array_unique($roleList) as $roleID => $roleName) {
		echo "<option value='" . str_replace("'", "\\'", $roleID) . "'>" . str_replace("'", "\\'", $roleName) . "</option>";
	}
	echo "\";";
	/*echo "var dags = \"";
	foreach ($dagList as $dagType => $dagData) {
		echo "<option value='" . str_replace("'", "\\'", $dagType) . "'>" . str_replace("'", "\\'", $dagData) . "</option>";
	}
	echo "\";";*/
	echo "</script>";

	echo "<form action='" . $destination . "' method='POST'>
	<table id='dags_table' class='table table-bordered' style='width:100%;font-weight:normal;'>";
	foreach ($hiddenFields as $fieldName => $fieldValue) {
		echo "<input type='hidden' value='$fieldValue' name='$fieldName' />";
	}
	$userCount = 0;
	/*if (is_array($userList)) {
		echo "<tr>";
		foreach ($userList as $userName => $realName) {
			if ($userCount % 4 == 0 && $userCount !== 0) {
				echo "</tr><tr>";
			}
			$userCount++;
			echo "<td><input type='checkbox' name='select_user[]' value='$userName' " . (in_array($userName, $usersGroup) ? "checked" : "") . "> $realName ($userName)</td>";
		}
		echo "</tr>";
	}*/
	echo "<table id='user_roles_table' class='table table-bordered' style='width:100%;font-weight:normal;'>
		    <tr>
                <th style='text-align:center;' class='col-xs-1'>
                    User Roles Assignments
                </th>
                <td class='col-xs-11'>
                    <table id='table-tr' class='table table-bordered' style='width:100%;font-weight:normal;'>";

	echo "<tr id='add_new_right'><td colspan='2' style='text-align:center;' class='col-md-6'><input id='roles_addbutton' type='button' value='Add New Role' style='margin:auto;' onclick='addRightsRow(this);'/></td>
                        <td colspan='2' style='text-align:center;' class='col-md-6'><input type='submit' name='update_rights' value='Submit Rights'/></td></tr>
                    </table>
                </td>
		    </tr>
		</table>
		</form>";
}

?>
<script>
	function addRightsRow(fieldName) {
		var parentID = $(fieldName).closest('tr').prop("id");
		var field = parentID.replace("_addnew","");
		var numRows = $('.'+field+'_table').length + 1;

		var rowHTML = "<tr id='"+field+"_table_"+numRows+"'><td colspan='4' style='padding:0;';><table class='"+field+"_table col-md-12 table table-bordered'><tr style='text-align:center;padding-top:5px;padding-bottom:5px;' class='"+field+"_row'><th colspan='2'>User Roles: <select onchange='generateProjectTable(\""+field+"_projectrow_"+numRows+"\","+numRows+",this);' class='picklist' id='"+field+"_role_"+numRows+"' name='"+field+"_role["+numRows+"]'><option value=''></option>"+roles+"</select></th><th style='width:25px;'><a style='color:white;cursor:pointer;' onclick='removeTable(\""+field+"_table_"+numRows+"\")'>X</a></th></tr><tr><td colspan='2'><input class='"+field+"_"+numRows+"' type='checkbox' onclick='checkAll(this,\"recordid\");'>Check / Uncheck All<br/></td></tr><tr><td style='text-align:center;' colspan='2'><table id='"+field+"_projectrow_"+numRows+"'></table></td></tr>";

        rowHTML += "</tr></table></td></tr>";
		$('#'+parentID).before(rowHTML);

		return numRows;
	}

	function addDAGsRow(fieldName) {
		var parentIDDAG = $(fieldName).closest('tr').prop("id");
		var fieldDAG = parentIDDAG.replace("_addnew","");
		var numRowsDAG = $('.'+fieldDAG+'_table').length + 1;
		var rowHTMLDAG = "<tr id='"+fieldDAG+"_table_"+numRowsDAG+"'><td colspan='4' style='padding:0;';><table class='"+fieldDAG+"_table col-md-12 table table-bordered'><tr style='text-align:center;padding-top:5px;padding-bottom:5px;' class='"+fieldDAG+"_row'><th class='dagHeader' colspan='2'>DAGs: <select class='picklist' id='"+fieldDAG+"_dag_"+numRowsDAG+"' name='"+fieldDAG+"_dag["+numRowsDAG+"]'><option value=''></option>"+dags+"</select></th><th class='dagHeader' style='width:25px;'><a style='color:white;cursor:pointer;' onclick='removeTable(\""+fieldDAG+"_table_"+numRowsDAG+"\")'>X</a></th></tr><tr style='background-color:rgba(0,0,0,0.75);'><td style='text-align:center;' colspan='2'></td></tr>";
		rowHTMLDAG += "</tr></table></td></tr>";
		$('#'+parentIDDAG).before(rowHTMLDAG);

		return numRowsDAG
    }

	function checkAll(trigger,elementName) {
		var parentClass = trigger.className;

		$('input.'+parentClass+':checkbox').each(function() {
			$(this).prop('checked',trigger.checked);
		});
    }

    function removeTable(tableID) {
		$('#'+tableID).remove();
    }

    function showHide(showID,parent,showValue,hideID = "") {
		if (parent.value == showValue) {
			$('#' + showID).show();
			if (hideID != "") {
				$('#' + hideID).hide();
			}
		}
		else {
			$('#' + showID).hide();
			if (hideID != "") {
				$('#' + hideID).show();
			}
        }
    }

    function generateProjectTable(targetID,rowID,select) {
		var roleID = select.value;
		$.ajax({
			type: "POST",
            url: "<?php echo $module->getUrl('ajax_project_table.php'); ?>",
            data: {'roleid':roleID,'fieldrow':rowID},
            success: function(result) {
            	$('#'+targetID).html(result);
            }
        });
    }

</script>
