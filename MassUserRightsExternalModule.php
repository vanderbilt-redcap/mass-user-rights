<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 1/14/2018
 * Time: 7:25 PM
 */
namespace Vanderbilt\MassUserRightsExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class MassUserRightsExternalModule extends AbstractExternalModule
{
	private function getFormAccess($project_id,$role_id) {
		$formAccess = array();
		$sql = "SELECT data_entry
			FROM redcap_user_roles
			WHERE project_id=$project_id
			AND role_id=$role_id";
		//echo "$sql<br/>";
		$roleForms = db_result(db_query($sql),0);
		$roleForms = ltrim($roleForms,"[");
		$roleForms = rtrim($roleForms,"]");
		$formArray = array();
		$formArray = explode("][",$roleForms);
		foreach ($formArray as $formData) {
			$formElement = explode(",",$formData);
			$formAccess[$formElement[0]] = $formElement[1];
		}
		return $formAccess;
	}

	public function getRightsProjectID() {
		return $this->getProjectSetting('user-project');
	}

	public function processRightsJSON($project_id,$rights) {
		$rightsArray = array();
		if (!is_array($rights)) {
			$rights = json_decode($rights,true);
		}
		foreach ($rights as $rightData) {
			foreach ($rightData['record'] as $recordIndex => $recordID) {
				if (!isset($rightsArray[$recordID])) {
					$rightsArray[$recordID] = array("role"=>$rightData['role'],"dag"=>\Records::getRecordGroupId($project_id,$recordID));
				}
			}
		}
		return $rightsArray;
	}

	public function processDAGs($dagAssigns) {
		$dagArray = array();
		$dagArray = explode(",",$dagAssigns);
		return $dagArray;
	}

	public function setCustomRights($project_id, $customRights,$recordID,$userRights) {
		if (isset($customRights[$recordID])) {
			$formAccess = $this->getFormAccess($project_id, $customRights[$recordID]['role']);
			$userRights['group_id'] = $customRights[$recordID]['dag'];
			$userRights['role_id'] = $customRights[$recordID]['role'];
			$userRights['forms'] = $formAccess;
		}
		return $userRights;
	}

	public function getStatusCount($eventID, $eventData, &$completeStatuses) {
		//$completeStatuses = array();
		foreach ($eventData as $formName => $formData) {
			if (empty($formData)) {
				$completeStatuses[$eventID][$formName]['icon'] = "circle_gray.png";
				continue;
			}
			foreach ($formData as $formStatus) {
				if (isset($completeStatuses[$eventID][$formName])) {
					if ($completeStatuses[$eventID][$formName]['previous_status'] === $formStatus && $completeStatuses[$eventID][$formName]['icon'] != "circle_blue_stack.png") {
						if ($formStatus === "2") {
							$completeStatuses[$eventID][$formName]['icon'] = "circle_green_stack.png";
						}
						elseif ($formStatus === "1") {
							$completeStatuses[$eventID][$formName]['icon'] = "circle_yellow_stack.png";
						}
						elseif ($formStatus === "0") {
							$completeStatuses[$eventID][$formName]['icon'] = "circle_red_stack.png";
						}
						else {
							$completeStatuses[$eventID][$formName]['icon'] = "circle_gray.png";
						}
						//$completeStatuses[$eventID][$formName]['icon'] = "many";
					} else {
						$completeStatuses[$eventID][$formName]['icon'] = "circle_blue_stack.png";
					}
					$completeStatuses[$eventID][$formName]['previous_status'] = $formStatus;
				} else {
					$completeStatuses[$eventID][$formName]['previous_status'] = $formStatus;
					if ($formStatus === "2") {
						$completeStatuses[$eventID][$formName]['icon'] = "circle_green.png";
					}
					elseif ($formStatus === "1") {
						$completeStatuses[$eventID][$formName]['icon'] = "circle_yellow.png";
					}
					elseif ($formStatus === "0") {
						$completeStatuses[$eventID][$formName]['icon'] = "circle_red.png";
					}
					else {
						$completeStatuses[$eventID][$formName]['icon'] = "circle_gray.png";
					}
					//$completeStatuses[$eventID][$formName]['icon'] = $formStatus;
				}
			}
		}
		//return $completeStatuses;
	}

	public function getAutoId($project_id) {
		$sql = "SELECT MAX(CAST(record as UNSIGNED))
				FROM redcap_data
				WHERE project_id=$project_id";
		//echo "$sql<br/>";
		$highestRecord = db_result(db_query($sql));
		$highestRecord++;
		return $highestRecord;
	}

	public function getFormStatus($project_id,$record_id) {
		$returnArray = array();
		$sql = "SELECT d2.event_id,d2.field_name,d2.value,d.form_name
				FROM redcap_metadata d
				JOIN redcap_data d2
					ON d.field_name=d2.field_name AND d.project_id=d2.project_id
				WHERE d.project_id=$project_id
				AND d2.record='$record_id'
				AND d.field_name LIKE '%_complete'";
		//echo "$sql<br/>";
		$result = db_query($sql);
		while ($row = db_fetch_assoc($result)) {
			$returnArray[$row['event_id']][$row['form_name']][] = $row['value'];
		}
		return $returnArray;
	}

	/*function redcap_module_link_check_display($project_id, $link) {
		if(\REDCap::getUserRights(USERID)[USERID]['design'] == '1'){
			return $link;
		}
		return null;
	}*/

	/*function getUserList($project_id) {
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
	}*/
}