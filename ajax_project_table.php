<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 5/25/2018
 * Time: 11:19 AM
 */
global $module;
require_once('functions.php');
$projectListing = getFullProjectList($module->getProjectSetting('role-projects'),$module->getProjectSetting('field-projects'));
$roleID = $_POST['roleid'];
$fieldRow = $_POST['fieldrow'];
$projectsWithRole = getProjectWithRoleName($roleID);

$tableHTML = "";
if (!empty($projectsWithRole)) {
	$count = 0;

	foreach ($projectListing as $projectID) {
		if (!in_array($projectID,array_keys($projectsWithRole))) continue;
		if ($count % 3 == 0) {
			$tableHTML .= "<tr>";
		}
		$tableHTML .= "<td style='padding:10px;width:50%;text-align: center;'><input type='checkbox' id='projectid_".$fieldRow."_" . urlencode($projectID) . "' name='projectid[".$fieldRow."][" . urlencode($projectID) . "]' value='".$projectID."'>".$projectsWithRole[$projectID]."</td>";
		if ($count % 3 != 0) {
			$tableHTML .= "</tr>";
		}
		$count++;
	}
}
echo $tableHTML;