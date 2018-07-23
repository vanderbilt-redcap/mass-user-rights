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
        <div id='user_div'>
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
	}
	//TODO If we go back to allowing assignment of multiple users at once, need to rewrite all 'userID' stuff below to behave as the 'userIDs' check above, and do escape strings in the functions instead
	elseif (isset($_POST['load_user']) && $_POST['select_user'] != "") {
		$userID = db_real_escape_string($_POST['select_user']);
		loadUser($userID);
	}
	elseif (isset($_POST['submit_single']) && $_POST['submit_single'] != "" && $_POST['select_user'] != "") {
	    $postProjectID = $_POST['submit_single'];
	    $userID = db_real_escape_string($_POST['select_user']);
	    updateUserRole(array($userID),$_POST['role_select_'.$postProjectID],$postProjectID);
	    updateUserDAG(array($userID),$_POST['dag_select_'.$postProjectID],$postProjectID);

		loadUser($userID);
    }
    elseif (isset($_POST['submit_all']) && $_POST['submit_all'] != "" && $_POST['select_user'] != "") {
		$userID = db_real_escape_string($_POST['select_user']);
		$projectID = "";
		foreach ($_POST as $postName => $postData) {
		    if (strpos($postName,"dag_select_") !== false) {
		        $projectID = explode("_",$postName)[2];
		        if ($projectID != "" && $userID != "") {
					updateUserDAG(array($userID), $postData, $projectID);
				}
            }
            elseif (strpos($postName,"role_select_") !== false) {
		        $projectID = explode("_",$postName)[2];
		        if ($projectID != "" && $userID != "") {
		            updateUserRole(array($userID),$postData,$projectID);
                }
            }
        }

		loadUser($userID);
    }
}

function loadUser($userID) {
    global $module;
	$hiddenFields = array('select_user'=>$userID);
	drawRightsTables($hiddenFields,$module->getUrl('assign_roles.php'));
	$userAssignments = userAssignedProjects($userID);

	$accessProjectID = $module->getProjectSetting('access-project');
	$roleProjectID = $module->getProjectSetting('role-project');
	$suggestedAssignments = getSuggestedAssignments($userID,$accessProjectID,$roleProjectID);

	if (!empty($userAssignments)) {
		echo "<script>
            $(document).ready(function() {
                ".generatePrefill($userAssignments,$suggestedAssignments)."
                });
             </script>";
	}
}

function drawRightsTables($hiddenFields,$destination)
{
    global $projectListing;
	$projectNames = array();
	$rolesByProject = array();
	$dagsByProject = array();

	foreach ($projectListing as $listProjectID) {
		$projectNames[$listProjectID] = getProjectName($listProjectID);
		$rolesByProject[$listProjectID] = getRoleList($listProjectID);
		$dagsByProject[$listProjectID] = getDAGList($listProjectID);
	}

	echo "<form action='$destination' method='POST'>";
	foreach ($hiddenFields as $fieldName => $fieldValue) {
		echo "<input type='hidden' value='$fieldValue' name='$fieldName' />";
	}
	echo "<div style='width:100%;font-weight:bold;text-align:center;font-size:120%;'>User Roles Assignments</div>
    <table id='submit_users_buttons1' class='table table-bordered' style='width:100%;font-weight:normal;margin-bottom:0;'>
        <tr>
            <td><input type='submit' name='submit_all' value='Submit All Projects'></td>
            <td><input type='submit' name='submit_suggest' value='Accept Suggested'></td>
        </tr>
    </table>
    <table id='user_roles_table' class='table table-bordered' style='width:100%;font-weight:normal;margin-bottom:0;'>
    <tr>
        <th>
            REDCap Project
        </th>
        <th>
            Data Access Group
        </th>
        <th>
            REDCap User Role
        </th>
        <th style='padding-left:0;padding-right:0;'>
        </th>
    </tr>";
	foreach ($projectListing as $projectID) {
	    echo "<tr>
            <td>".$projectNames[$projectID]."</td>
            <td id='dag_div_$projectID'>";
	        if (!empty($dagsByProject[$projectID])) {
	            echo "<select class='picklist' id='dag_select_$projectID' name='dag_select_$projectID'><option value=''>No DAG</option>";
	            foreach ($dagsByProject[$projectID] as $dagID => $dagName) {
	                echo "<option value='$dagID' >$dagName</option>";
                }
	            echo "</select>";
            }
	        echo "</td>
            <td id='role_div_$projectID'>";
	        if (!empty($rolesByProject[$projectID])) {
	            echo "<select class='picklist' id='role_select_$projectID' name='role_select_$projectID'><option value=''>No Role</option>";
	            foreach ($rolesByProject[$projectID] as $roleID => $roleName) {
	                echo "<option value='$roleID'>$roleName</option>";
                }
	            echo "</select>";
            }
            echo "</td>
            <td style='padding-left:0;padding-right:0;'><button type='submit' name='submit_single' value='$projectID'>Submit<br/>Project</button></td>";
	    echo "</tr>";
    }
    echo "</table>
    <table id='submit_users_buttons1' class='table table-bordered' style='width:100%;font-weight:normal;'>
        <tr>
            <td><input type='submit' name='submit_all' value='Submit All Projects'></td>
            <td><input type='submit' name='submit_suggest' value='Accept Suggested'></td>
        </tr>
    </table>
    </form>";
}

?>
<script>
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
</script>
