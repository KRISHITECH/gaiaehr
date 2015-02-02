<?php
/**
 * GaiaEHR (Electronic Health Records)
 * Copyright (C) 2013 Certun, LLC.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

include_once(ROOT . '/dataProvider/Patient.php');
include_once(ROOT . '/dataProvider/User.php');
include_once(ROOT . '/dataProvider/Vitals.php');
include_once(ROOT . '/dataProvider/PoolArea.php');
include_once(ROOT . '/dataProvider/Medical.php');
include_once(ROOT . '/dataProvider/PreventiveCare.php');
include_once(ROOT . '/dataProvider/Services.php');
include_once(ROOT . '/dataProvider/DiagnosisCodes.php');
include_once(ROOT . '/dataProvider/FamilyHistory.php');

class Encounter {
	/**
	 * @var MatchaHelper
	 */
	private $db;
	/**
	 * @var User
	 */
	private $user;
	/**
	 * @var Patient
	 */
	private $patient;
	/**
	 * @var Services
	 */
	private $services;
	/**
	 * @var
	 */
	private $eid;
	/**
	 * @var PoolArea
	 */
	private $poolArea;
	/**
	 * @var Medical
	 */
	private $medical;
	/**
	 * @var PreventiveCare
	 */
	private $preventiveCare;
	/**
	 * @var DiagnosisCodes
	 */
	private $diagnosis;

	/**
	 * @var
	 */
	private $EncounterHistory;

	/**
	 * @var bool|MatchaCUP
	 */
	private $e;
	/**
	 * @var bool|MatchaCUP
	 */
	private $ros;
	/**
	 * @var bool|MatchaCUP
	 */
	private $soap;
	/**
	 * @var bool|MatchaCUP
	 */
	private $d;
	/**
	 * @var bool|MatchaCUP
	 */
	private $hcfa;
	/**
	 * @var Vitals
	 */
	private $Vitals;
	/**
	 * @var bool|MatchaCUP
	 */
	private $edx;

	function __construct(){
		$this->db = new MatchaHelper();
		$this->patient = new Patient();
		$this->services = new Services();
		$this->poolArea = new PoolArea();
		$this->medical = new Medical();
		$this->preventiveCare = new PreventiveCare();
		$this->diagnosis = new DiagnosisCodes();

		$this->FamilyHistory = new FamilyHistory();
		$this->Vitals = new Vitals();

		$this->e = MatchaModel::setSenchaModel('App.model.patient.Encounter');
		$this->ros = MatchaModel::setSenchaModel('App.model.patient.ReviewOfSystems');
		$this->soap = MatchaModel::setSenchaModel('App.model.patient.SOAP');
		$this->d = MatchaModel::setSenchaModel('App.model.patient.Dictation');
		$this->hcfa = MatchaModel::setSenchaModel('App.model.patient.HCFAOptions');
		$this->edx = MatchaModel::setSenchaModel('App.model.patient.EncounterDx');
	}

	private function setEid($eid){
		$this->eid = $eid;
		/**
		 * This is a temporary variable to comfort the certification needed by GaiaEHR
		 * GAIAEH-177 GAIAEH-173 170.302.r Audit Log (core)
		 * Added by: Gino Rivera Falu
		 * Web Jul 31 2013
		 */
		$_SESSION['encounter']['id'] = $eid; // Added by Gino Rivera
	}

	/**
	 * @param $pid
	 * @return array
	 */
	public function checkOpenEncountersByPid($pid){
		$params =  new stdClass();
		$params->filter[0] = new stdClass();
		$params->filter[0]->property = 'pid';
		$params->filter[0]->value = $pid;
		$params->filter[1] = new stdClass();
		$params->filter[1]->property = 'close_date';
		$params->filter[1]->value = null;
		$records = $this->e->load($params)->all();
		unset($params);
		if(count($records['encounter']) > 0){
			return array('encounter' => true);
		} else{
			return array('encounter' => false);
		}
	}

	/**
	 * @param stdClass $params
	 * @return array
	 *  Naming: "createPatientEncounters"
	 */
	public function createEncounter(stdClass $params){
		$record = $this->e->save($params);
		$encounter = (array) $record['encounter'];
		unset($record);
		$default = array(
			'pid' => $encounter['pid'],
			'eid' => $encounter['eid'],
			'uid' => $encounter['open_uid'],
			'date' => date('Y-m-d H:i:s')
		);

		if($_SESSION['globals']['enable_encounter_review_of_systems']){
			$this->addReviewOfSystems((object)$default);
		}

		if($_SESSION['globals']['enable_encounter_family_history']){
			$familyHistory = $this->FamilyHistory->getFamilyHistoryByPid($encounter['pid']);
		}


		// TODO: Matcha Model
		if($_SESSION['globals']['enable_encounter_review_of_systems_cks']){

		}

		if($_SESSION['globals']['enable_encounter_soap']){
			$this->addSoap((object)$default);
		}

		if($_SESSION['globals']['enable_encounter_dictation']){
			$this->addDictation((object)$default);
		}

		if($_SESSION['globals']['enable_encounter_hcfa']){
			$this->addHCFA((object)$default);
		}

		$this->poolArea->updateCurrentPatientPoolAreaByPid(array('eid' => $encounter['eid'], 'priority' => $encounter['priority']), $encounter['pid']);
		$this->setEid($encounter['eid']);

		return array('success' => true, 'encounter' => $encounter);
	}

	/**
	 * @param $params
	 * @param bool     $relations
	 *
	 * @return array
	 */
	public function getEncounters($params, $relations = true){
		$records = $this->e->load($params)->all();
		$encounters = (array) $records['encounter'];
		$relations = isset($params->relations) ? $params->relations : $relations;

		foreach($encounters as $i => $encounter){
			$encounters[$i]['status'] = ($encounter['close_date'] == null) ? 'open' : 'close';
			if($relations){
				$encounters[$i] = $this->getEncounterRelations($encounters[$i]);
			}
		}
		return $encounters;
	}

	/**
	 * @param stdClass $params
	 * @param bool     $relations
	 * @param bool     $allVitals include all patient vitals
	 *
	 * @return array|mixed
	 */
	public function getEncounter($params, $relations = true, $allVitals = true){

		if(is_string($params) || is_int($params)){
			$filters = new stdClass();
			$filters->filter[0] = new stdClass();
			$filters->filter[0]->property = 'eid';
			$filters->filter[0]->value = $params;
			$record = $this->e->load($filters)->one();
		}else{
			$record = $this->e->load($params)->one();
		}

		if($record === false) return array();
		$encounter = (array) $record['encounter'];
		$this->setEid($encounter['eid']);
		unset($record);

		$relations = isset($params->relations) ? $params->relations : $relations;
		if($relations == false) return array('encounter' => $encounter);
		$encounter = $this->getEncounterRelations($encounter, $allVitals);

		unset($filters);
		return array('encounter' => $encounter);
	}

	private function getEncounterRelations($encounter, $allVitals = true){
		$filters = new stdClass();
		$filters->filter[0] = new stdClass();
		$filters->filter[0]->property = 'eid';
		$filters->filter[0]->value = $encounter['eid'];

		if($_SESSION['globals']['enable_encounter_vitals']){
			$encounter['vitals'] = $allVitals ? $this->Vitals->getVitalsByPid($encounter['pid']) : $this->Vitals->getVitalsByEid($encounter['eid']);
		}

		if($_SESSION['globals']['enable_encounter_review_of_systems']){
			$encounter['reviewofsystems'][] = $this->getReviewOfSystems($filters);
		}

		if($_SESSION['globals']['enable_encounter_family_history']){
			$encounter['familyhistory'] = $this->FamilyHistory->getFamilyHistoryByPid($encounter['pid']);
		}

		// TODO: Matcha Model
		if($_SESSION['globals']['enable_encounter_review_of_systems_cks']){

		}

		if($_SESSION['globals']['enable_encounter_soap']){
			$encounter['soap'][] = $this->getSoapByEid($encounter['eid']);
		}

		if($_SESSION['globals']['enable_encounter_dictation']){
			$encounter['speechdictation'][] = $this->getDictation($filters);
		}

		if($_SESSION['globals']['enable_encounter_hcfa']){
			$encounter['hcfaoptions'][] = $this->getHCFA($filters);
		}
		unset($filters);
		return $encounter;
	}


	public function getEncounterSummary(stdClass $params){
		$this->setEid($params->eid);
		$record = $this->getEncounter($params);
		$encounter = (array) $record['encounter'];
		$encounter['patient'] = $this->patient->getPatientDemographicDataByPid($encounter['pid']);
		if(!empty($e)){
			return array('success' => true, 'encounter' => $e);
		} else{
			return array('success' => false, 'error' => "Encounter ID $params->eid not found");
		}
	}

	public function updateEncounterPriority($params){
		$this->updateEncounter($params);
		$this->poolArea->updateCurrentPatientPoolAreaByPid(array('eid' => $params->eid, 'priority' => $params->priority), $params->pid);
	}

	/**
	 * @param stdClass $params
	 * @return array|mixed
	 */
	public function updateEncounter($params){
		return $this->e->save($params);
	}

	/**
	 * @param stdClass $params
	 * @return array
	 */
	public function signEncounter(stdClass $params){
		$this->setEid($params->eid);

		/** verify permissions (sign encounter and supervisor) */
		if(!ACL::hasPermission('sign_enc') || ($params->isSupervisor && !ACL::hasPermission('sign_enc_supervisor'))){
			return array('success' => false, 'error' => 'access_denied');
		}

		$user = new User();
		if($params->isSupervisor){
			if($params->supervisor_uid != $_SESSION['user']['id']){
				unset($user);
				return array('success' => false, 'error' => 'supervisor_does_not_match_user');
			}
			if(!$user->verifyUserPass($params->signature, $params->supervisor_uid)){
				unset($user);
				return array('success' => false, 'error' => 'incorrect_password');
			}
		}else{
			if(!$user->verifyUserPass($params->signature)){
				unset($user);
				return array('success' => false, 'error' => 'incorrect_password');
			}
		}
		unset($user);

		if($params->isSupervisor){
			$params->close_date = date('Y-m-d H:i:s');
		}else{
			$params->provider_uid = $_SESSION['user']['id'];
			if(!ACL::hasPermission('require_enc_supervisor')) $params->close_date = date('Y-m-d H:i:s');
		}

		$data = $this->updateEncounter($params);
		return array('success' => true, 'data' => $data);


	}

	/**
	 * @param $eid
	 * @return array
	 */
	public function getSoapByEid($eid){
		$filters = new stdClass();
		$filters->filter[0] = new stdClass();
		$filters->filter[0]->property = 'eid';
		$filters->filter[0]->value = $eid;
		$soap = $this->getSoap($filters);
		$soap['dxCodes'] = $this->getEncounterDxs($filters);
		return $soap;
	}

	/**
	 * @param stdClass $params
	 * @return array
	 */
	public function getSoapHistory(stdClass $params){
		$filters = new stdClass();
		$filters->filter[0] = new stdClass();
		$filters->filter[0]->property = 'eid';
		$filters->filter[0]->operator = '!=';
		$filters->filter[0]->value = $params->eid;
		$filters->filter[1] = new stdClass();
		$filters->filter[1]->property = 'pid';
		$filters->filter[1]->operator = '=';
		$filters->filter[1]->value = $params->pid;
		$encounters = $this->getEncounters($filters);

		// switch the operator to =
		$filters->filter[0]->operator = '=';
		// remove the pid filter we don't need it
		unset($filters->filter[1]);

		foreach($encounters AS $i => $encounter){
			$filters->filter[0]->value = $encounter['eid'];
			$soap = $this->getSoap($filters);
			$encounter['service_date'] = date($_SESSION['globals']['date_time_display_format'], strtotime($encounter['service_date']));
			$icds = '';
			foreach($this->diagnosis->getICDByEid($encounter['eid'], true) as $code){
				$icds .= '<li><span style="font-weight:bold; text-decoration:none">' . $code['code'] . '</span> - ' . $code['long_desc'] . '</li>';
			}
			$encounter['subjective'] = $soap['subjective'];
			$encounter['objective'] = $soap['objective'] . $this->getObjectiveExtraDataByEid($encounter['eid']);
			$encounter['assessment'] = $soap['assessment'] . '<ul  class="ProgressNote-ul">' . $icds . '</ul>';
			$encounter['plan'] = $soap['plan'];
			$encounters[$i] = $encounter;
			unset($soap);
		}
		unset($filters);
		return $encounters;
	}


	/**
	 * TODO: get all codes CPT/CVX/HCPCS/ICD9/ICD10 encounter
	 * @param $params
	 * @return array
	 */
	public function getEncounterCodes($params){
		if(isset($params->eid))
			return $this->getEncounterServiceCodesByEid($params->eid);
		return array();
	}

	public function getEncounterServiceCodesByEid($eid){
		return $this->services->getCptByEid($eid);
	}


	//***********************************************************************************************
	//***********************************************************************************************
	public function getEncounterCptDxTree($params){
		if(isset($params->eid)){
			$services = $this->services->getCptByEid($params->eid);
			foreach($services['rows'] AS $index => $row){
				$dx_children = array();
				$foo = explode(',', $row['dx_pointers']);
				foreach($foo AS $fo){
					$dx = array();
					$f = $this->diagnosis->getICDDataByCode($fo);
					if(!empty($f)){
						$dx['code'] = $f['code'];
						$dx['code_text_medium'] = $f['short_desc'];
						$dx['leaf'] = true;
						$dx['iconCls'] = 'icoDotYellow';
						$dx_children[] = $dx;
					}

				}
				$services['rows'][$index]['iconCls'] = 'icoDotGrey';
				$services['rows'][$index]['expanded'] = true;
				$services['rows'][$index]['children'] = $dx_children;
			}
			return $services['rows'];
		} else{
			return array();
		}

	}

	public function addEncounterCptDxTree($params){
		$dx_pointers = array();
		$dx_children = array();
		foreach($this->diagnosis->getICDByEid($params->eid, true) AS $dx){
			$dx_children[] = $dx;
			$dx_pointers[] = $dx['code'];
		}
		$service = new stdClass();
		$service->pid = $params->pid;
		$service->eid = $params->eid;
		$service->code = $params->code;
		$service->dx_pointers = implode(',', $dx_pointers);
		$newService = $this->services->addCptCode($service);
		$params->id = $newService['rows']->id;
		$params->dx_children = $dx_children;
		return $params;
	}

	public function updateEncounterCptDxTree($params){
		return $params;
	}

	public function removeEncounterCptDxTree($params){
		$this->services->deleteCptCode($params);
		return $params;
	}

	//***********************************************************************************************
	//***********************************************************************************************

	/**
	 * @param $eid
	 * @return array
	 */
	public function getDictationByEid($eid){
		$this->db->setSQL("SELECT * FROM encounter_dictation WHERE eid = '$eid' ORDER BY date DESC");
		return $this->db->fetchRecord(PDO::FETCH_ASSOC);
	}

	/**
	 * @param $eid
	 * @return array
	 *  Naming: "closePatientEncounter"
	 */
	public function getProgressNoteByEid($eid){

		$record = $this->getEncounter($eid, true, false);
		unset($filters);
		$encounter = (array) $record['encounter'];
		$user = new User();
		$encounter['service_date'] = date('F j, Y, g:i a', strtotime($encounter['service_date']));
		$encounter['patient_name'] = $this->patient->getPatientFullNameByPid($encounter['pid']);
		$encounter['open_by'] = $user->getUserNameById($encounter['open_uid']);
		$encounter['signed_by'] = $user->getUserNameById($encounter['provider_uid']);
		unset($user);
		/**
		 * Add vitals to progress note
		 */
		if($_SESSION['globals']['enable_encounter_vitals']){
			if(count($encounter['vitals']) == 0) unset($encounter['vitals']);
		}

		/**
		 * Add Review of Systems to progress note
		 */
		if($_SESSION['globals']['enable_encounter_review_of_systems']){
			$foo = array();
			foreach($encounter['reviewofsystems'] as $key => $value){
				if($key != 'id' && $key != 'pid' && $key != 'eid' && $key != 'uid' && $key != 'date'){
					if($value != null && $value != 'null'){
						$value = ($value == 1 || $value == '1') ? 'Yes' : 'No';
						$foo[] = array('name' => $key, 'value' => $value);
					}
				}
			}
			if(!empty($foo)){
				$encounter['reviewofsystems'] = $foo;
			}
		}


		/**
		 * Add SOAP to progress note
		 */
		if($_SESSION['globals']['enable_encounter_soap']){

			$icdxs = '';
			foreach($this->diagnosis->getICDByEid($eid, true) as $code){
				$icdxs .= '<li><span style="font-weight:bold; text-decoration:none">' . $code['code'] . '</span> - ' . $code['long_desc'] . '</li>';
			}
			$soap = $this->getSoapByEid($eid);
			$soap['assessment'] = isset($soap['assessment']) ? $soap['assessment'] : '';
			$soap['objective'] = $this->getObjectiveExtraDataByEid($eid);
			$soap['assessment'] = $soap['assessment'] . '<ul  class="ProgressNote-ul">' . $icdxs . '</ul>';
			$encounter['soap'] = $soap;
		}

		/**
		 * Add Dictation to progress note
		 */
		if($_SESSION['globals']['enable_encounter_dictation']){
			$speech = $this->getDictationByEid($eid);
			if($speech['dictation']){
				$encounter['speechdictation'] = $speech;
			}
		}

		return $encounter;
	}

	private function getObjectiveExtraDataByEid($eid){
		$ExtraData = '';
		$medications = $this->medical->getPatientMedicationsByEncounterID($eid);
		if(!empty($medications)){
			$lis = '';
			foreach($medications as $foo){
				$lis .= '<li>' . $foo['STR'] . '</li>';
			}
			$ExtraData .= '<p>Medications:</p>';
			$ExtraData .= '<ul class="ProgressNote-ul">' . $lis . '</ul>';
		}
		$immunizations = $this->medical->getImmunizationsByEncounterID($eid);
		if(!empty($immunizations)){
			$lis = '';
			foreach($immunizations as $foo){
				$lis .= '<li>Vaccine name: ' . $foo['vaccine_name'] . '<br>';
				$lis .= 'Vaccine ID: (' . $foo['code_type'] . ')' . $foo['code'] . '<br>';
				$lis .= 'Manufacturer: ' . $foo['manufacturer'] . '<br>';
				$lis .= 'Lot Number: ' . $foo['lot_number'] . '<br>';
				$lis .= 'Dose: ' . $foo['administer_amount'] . ' ' . $foo['administer_units'] . '<br>';
				$lis .= 'Administered By: ' . $foo['administered_by'] . ' </li>';
			}
			$ExtraData .= '<p>Immunizations:</p>';
			$ExtraData .= '<ul class="ProgressNote-ul">' . $lis . '</ul>';
		}
		$allergies = $this->medical->getAllergiesByEncounterID($eid);
		if(!empty($allergies)){
			$lis = '';
			foreach($allergies as $foo){
				$lis .= '<li>Allergy: ' . $foo['allergy'] . ' (' . $foo['allergy_type'] . ')<br>';
				$lis .= 'Reaction: ' . $foo['reaction'] . '<br>';
				$lis .= 'Severity: ' . $foo['severity'] . '<br>';
				$lis .= 'Location: ' . $foo['location'] . '<br>';
				$lis .= 'Active?: ' . ($foo['end_date'] != null ? 'Yes' : 'No') . '</li>';
			}
			$ExtraData .= '<p>Allergies:</p>';
			$ExtraData .= '<ul class="ProgressNote-ul">' . $lis . '</ul>';
		}

		/**
		 * Active Problems found in this Encounter
		 */
		$activeProblems = $this->medical->getMedicalIssuesByEncounterID($eid);
		if(!empty($activeProblems)){
			$lis = '';
			foreach($activeProblems as $foo){
				$lis .= '<li>[' . $foo['code'] . '] - ' . $foo['code_text'] . ' </li>';
			}
			$ExtraData .= '<p>Active Problems:</p>';
			$ExtraData .= '<ul class="ProgressNote-ul">' . $lis . '</ul>';
		}

		$preventiveCare = $this->preventiveCare->getPreventiveCareDismissPatientByEncounterID($eid);
		if(!empty($preventiveCare)){
			$lis = '';
			foreach($preventiveCare as $foo){
				$lis .= '<li>Description: ' . $foo['description'] . '<br>';
				$lis .= 'Reason: ' . $foo['reason'] . '<br>';
				$lis .= 'Observation: ' . $foo['observation'] . ' </li>';
			}
			$ExtraData .= '<p>Preventive Care:</p>';
			$ExtraData .= '<ul class="ProgressNote-ul">' . $lis . '</ul>';
		}
		return $ExtraData;
	}

	public function getEncounterEventHistory($params){
		$this->EncounterHistory = MatchaModel::setSenchaModel('App.model.administration.AuditLog');
		return $this->EncounterHistory->load($params)->all();
	}

	public function checkoutAlerts(stdClass $params){
		$alerts = array();
		$records = $this->e->load(
			array('eid' => $params->eid),
			array('review_immunizations', 'review_allergies', 'review_active_problems', 'review_medications', 'review_alcohol', 'review_smoke', 'review_pregnant')
		)->one();
		foreach($records as $key => $rec){
			if($rec != 0 && $rec != null){
				unset($records[$key]);
			}
		}
		foreach($records as $key => $rec){
			$foo = array();
			$foo['alert'] = 'Need to ' . str_replace('_', ' ', $key) . ' area';
			$foo['alertType'] = 1;
			$alerts[] = $foo;
		}
		//TODO: vitals check
		return $alerts;
	}

	/**
	 * @param $date
	 * @return mixed
	 */
	public function parseDate($date){
		return str_replace('T', ' ', $date);
	}

	public function checkForAnOpenedEncounterByPid(stdClass $params){
		$date = strtotime('-1 day', strtotime($params->date));
		$date = date('Y-m-d H:i:s', $date);
		$this->db->setSQL("SELECT * FROM encounters
                           WHERE (pid='$params->pid'
                           AND   close_date is NULL)
                           AND service_date >= '$date'");
		$data = $this->db->fetchRecord(PDO::FETCH_ASSOC);
		if(isset($data['eid'])){
			return true;
		} else{
			return false;
		}

	}

	public function getEncounterFollowUpInfoByEid($eid){
		$this->db->setSQL("SELECT followup_time, followup_facility FROM encounters WHERE eid = '$eid'");
		$rec = $this->db->fetchRecord(PDO::FETCH_ASSOC);
		$rec['followup_facility'] = intval($rec['followup_facility']);
		return $rec;
	}

	public function getEncounterMessageByEid($eid){
		$this->db->setSQL("SELECT message FROM encounters WHERE eid = '$eid'");
		return $this->db->fetchRecord(PDO::FETCH_ASSOC);
	}

	public function getEncounterByDateFromToAndPatient($from, $to, $pid = null){
		$sql = " SELECT encounters.pid,
	                    encounters.eid,
	                    encounters.service_date,
	                    patient.*
	               FROM encounters
	          LEFT JOIN patient ON encounters.pid = patient.pid
	              WHERE encounters.service_date BETWEEN '$from 00:00:00' AND '$to 23:59:59'";
		if(isset($pid) && $pid != ''){
			$sql .= " AND encounters.pid = '$pid'";
		}
		$this->db->setSQL($sql);
		return $this->db->fetchRecords(PDO::FETCH_ASSOC);
	}

	public function onSaveItemsToReview(stdClass $params){
		$data = get_object_vars($params);
		unset($data['eid']);
		$this->db->setSQL($this->db->sqlBind($data, 'encounters', 'U', array('eid' => $params->eid)));
		$this->db->execLog();
		return array('success' => true);

	}

	public function addEncounterHCFAOptions(stdClass $params){
		$data = get_object_vars($params);
		unset($data['eid']);
		$this->db->setSQL($this->db->sqlBind($data, 'encounter_1500_options', 'U', array('eid' => $params->eid)));
		$this->db->execLog();
		return array('success' => true);
	}

	public function updateEncounterHCFAOptions(stdClass $params){
		$data = get_object_vars($params);
		unset($data['eid']);
		$this->db->setSQL($this->db->sqlBind($data, 'encounter_1500_options', 'U', array('eid' => $params->eid)));
		$this->db->execLog();
		return array('success' => true);
	}

	public function getEncounterHCFAOptionsByEid($eid){
		$this->db->setSQL("SELECT * FROM encounter_1500_options WHERE eid = '$eid'");
		return $this->db->fetchRecord(PDO::FETCH_ASSOC);
	}

	public function getEncountersByDate($date){
		$this->db->setSQL("SELECT * FROM encounters WHERE service_date >= '$date 00:00:00' AND service_date <= '$date 23:59:59'");
		return $this->db->fetchRecords(PDO::FETCH_ASSOC);
	}

	public function getTodayEncounters(){
		return $this->getEncountersByDate(date('Y-m-d'));
	}

	public function getReviewOfSystems($params){
		return $this->ros->load($params)->one();
	}

	public function addReviewOfSystems($params){
		return $this->ros->save($params);
	}

	public function updateReviewOfSystems($params){
		return $this->ros->save($params);
	}

	public function getSoap($params){
		return $this->soap->load($params)->one();
	}

	public function addSoap($params){
		return $this->soap->save($params);
	}

	public function updateSoap($params){
		return $this->soap->save($params);
	}

	public function getDictation($params){
		return $this->d->load($params)->one();
	}

	public function addDictation($params){
		return $this->d->save($params);
	}

	public function updateDictation($params){
		return $this->d->save($params);
	}

	public function getHCFA($params){
		return $this->hcfa->load($params)->one();
	}

	public function addHCFA($params){
		return $this->hcfa->save($params);
	}

	public function updateHCFA($params){
		return $this->hcfa->save($params);
	}

	public function getEncounterDxs($params){
		$records = $this->edx->load($params)->all();
		foreach($records as $i => $record){
			if($record !== false){
				$code = $this->diagnosis->getICDDataByCode($record['code'], $record['code_type']);
				if(is_array($code)) $records[$i] = array_merge($records[$i], $code);
			}
		}
		return $records;
	}

	public function getEncounterDx($params){
		$record = $this->edx->load($params)->one();
		if($record !== false){
			$code = $this->diagnosis->getICDDataByCode($record['code'], $record['code_type']);
			if(is_array($code)) $record = array_merge($record, $code);
		}
		return $record;
	}

	public function createEncounterDx($params){
		return $this->edx->save($params);
	}

	public function updateEncounterDx($params){
		return $this->edx->save($params);
	}

	public function destroyEncounterDx($params){
		return $this->edx->destroy($params);
	}

//	/**
//	 * @param stdClass $params
//	 * @return array
//	 */
//	public function getVitals(stdClass $params){
//		$records =  $this->v->load($params)->all();
//		foreach($records as $i => $record){
//			$records[$i]['height_in'] = isset($record['height_in']) ? intval($record['height_in']) : '';
//			$records[$i]['height_cm'] = isset($record['height_cm']) ? intval($record['height_cm']) : '';
//			$records[$i]['administer_by'] = $record['uid'] != null ? $this->user->getUserNameById($record['uid']) : '';
//			$records[$i]['authorized_by'] = $record['auth_uid'] != null ? $this->user->getUserNameById($record['auth_uid']) : '';
//		}
//		return $records;
//	}
//
//	/**
//	 * @param stdClass $params
//	 * @return stdClass
//	 */
//	public function addVitals($params){
//		$eid = is_object($params) ? $params->eid : $params[0]->eid;
//		$this->setEid($eid);
//		$record = $this->v->save($params);
//		$record['administer_by'] = $this->user->getUserNameById($record['uid']);
//		return $record;
//	}
//
//	/**
//	 * @param stdClass $params
//	 * @return stdClass
//	 */
//	public function updateVitals($params){
//		$eid = is_object($params) ? $params->eid : $params[0]->eid;
//		$this->setEid($eid);
//		$record = $this->v->save($params);
//		if(is_array($params)){
//			foreach($record as $i => $rec){
//				$record[$i] = $rec = (object) $rec;
//				$record[$i]->administer_by = $rec->uid != 0 ? $this->user->getUserNameById($rec->uid) : '';
//				$record[$i]->authorized_by = $rec->auth_uid != 0 ? $this->user->getUserNameById($rec->auth_uid) : '';
//			}
//		}else{
//			$record = (object) $record;
//			$record->administer_by = $record->uid != 0 ? $this->user->getUserNameById($record->uid) : '';
//			$record->authorized_by = $record->auth_uid != 0 ? $this->user->getUserNameById($record->auth_uid) : '';
//		}
//
//		return $record;
//	}
//
//	/**
//	 * @param $pid
//	 * @return array
//	 */
//	public function getVitalsByPid($pid){
//		$filters = new stdClass();
//		$filters->filter[0] = new stdClass();
//		$filters->filter[0]->property = 'pid';
//		$filters->filter[0]->value = $pid;
//
//		$filters->sort[0] = new stdClass();
//		$filters->sort[0]->property = 'date';
//		$filters->sort[0]->direction = 'DESC';
//		return $this->getVitals($filters);
//	}
//
//	/**
//	 * @param $eid
//	 * @return array
//	 */
//	public function getVitalsByEid($eid){
//		$filters = new stdClass();
//		$filters->filter[0] = new stdClass();
//		$filters->filter[0]->property = 'eid';
//		$filters->filter[0]->value = $eid;
//
//		$filters->sort[0] = new stdClass();
//		$filters->sort[0]->property = 'date';
//		$filters->sort[0]->direction = 'DESC';
//		return $this->getVitals($filters);
//	}
}