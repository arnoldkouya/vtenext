<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

require_once 'modules/Import/models/Import_Map.php';
require_once 'modules/Import/resources/Utils.php';
require_once 'modules/Import/ui/Viewer.php';

class Import_Index_Controller {

	static $_cached_module_meta;

	public function  __construct() {
	}

	private function isEditableField($fieldInstance) {
		if(((int)$fieldInstance->getDisplayType()) === 2 ||
				in_array($fieldInstance->getPresence(), array(1,3)) ||
				strcasecmp($fieldInstance->getFieldDataType(),"autogenerated") ===0 ||
				strcasecmp($fieldInstance->getFieldDataType(),"id") ===0 ||
				$fieldInstance->isReadOnly() == true ||
				$fieldInstance->getUIType() ==  70 ||
				$fieldInstance->getUIType() ==  4) {

			return false;
		}
		return true;
	}

	public function getAccessibleFields($moduleName) {
		global $current_user;

		if(empty(self::$_cached_module_meta[$moduleName][$current_user->id])) {
			$moduleHandler = vtws_getModuleHandlerFromName($moduleName, $current_user);
			self::$_cached_module_meta[$moduleName][$current_user->id] = $moduleHandler->getMeta();
		}
		$meta = self::$_cached_module_meta[$moduleName][$current_user->id];
		$moduleFields = $meta->getModuleFields();
		$accessibleFields = array();
		foreach($moduleFields as $fieldName => $fieldInstance) {
			if($fieldInstance->getPresence() === 1) {
				continue;
			}
			$accessibleFields[$fieldName] = $fieldInstance;
		}
		return $accessibleFields;
	}

	public function getMergableFields($moduleName) {
		$accessibleFields = $this->getAccessibleFields($moduleName);
		$mergableFields = array();
		foreach($accessibleFields as $fieldName => $fieldInstance) {
			if($fieldInstance->getPresence() === 1) {
				continue;
			}
			// We need to avoid Last Modified by or any such User reference field
			// for now as Query Generator is not handling it well enough.
			// The case in which query generator is failing to generate right query is,
			// Assigned User field is not there either in the selected fields list or in the conditions
			// and condition is added on the User reference field
			// TODO - Cleanup this once Query Generator support is corrected
			if($fieldInstance->getFieldDataType() == 'reference') {
				$referencedModules = $fieldInstance->getReferenceList();
				if($referencedModules[0] == 'Users') {
					continue;
				}
			}
			$mergableFields[$fieldName] = $fieldInstance;
		}
		return $mergableFields;
	}

	public function getMandatoryFields($moduleName) {
		$focus = CRMEntity::getInstance($moduleName);
		if(method_exists($focus, 'getMandatoryImportableFields')) {
			$mandatoryFields = $focus->getMandatoryImportableFields();
		} else {
			$moduleFields = $this->getAccessibleFields($moduleName);
			$mandatoryFields = array();
			foreach($moduleFields as $fieldName => $fieldInstance) {
				if($fieldInstance->isMandatory($current_user)	//crmv@49510
						&& $fieldInstance->getFieldDataType() != 'owner'
						&& $this->isEditableField($fieldInstance)) {
					$mandatoryFields[$fieldName] = getTranslatedString($fieldInstance->getFieldLabelKey(), $moduleName);
				}
			}
		}
		return $mandatoryFields;
	}

	public function getImportableFields($moduleName) {
		global $table_prefix;
		$focus = CRMEntity::getInstance($moduleName);
		if(method_exists($focus, 'getImportableFields')) {
			$importableFields = $focus->getImportableFields();
		} else {
			$moduleFields = $this->getAccessibleFields($moduleName);
			$importableFields = array();
			foreach($moduleFields as $fieldName => $fieldInstance) {
				if(($this->isEditableField($fieldInstance)
							&& ($fieldInstance->getTableName() != $table_prefix.'_crmentity' || $fieldInstance->getColumnName() != 'modifiedby')
						) || ($fieldInstance->getUIType() == '70' && $fieldName != 'modifiedtime')) {
					$importableFields[$fieldName] = $fieldInstance;
				}
			}
		}
		return $importableFields;
	}

	public function getEntityFields($moduleName) {
		$moduleFields = $this->getAccessibleFields($moduleName);
		$entityColumnNames = vtws_getEntityNameFields($moduleName);
		$entityNameFields = array();
		foreach($moduleFields as $fieldName => $fieldInstance) {
			$fieldColumnName = $fieldInstance->getColumnName();
			if(in_array($fieldColumnName, $entityColumnNames)) {
				$entityNameFields[$fieldName] = $fieldInstance;
			}
		}
		return $entityNameFields;
	}
	
	// crmv@83878
	public function getFieldFormats($availFields) {
		$formats = array();
		foreach ($availFields as $field) {
			$uitype = $field->getUIType();
			$datatype = $field->getFieldDataType();
			$fname = $field->getFieldName();
			if ($uitype == 7 || $uitype == 71 || $uitype == 72) {
				// numeric or currency
				$formats[$fname] = array(
					'EMPTY:PERIOD' => '1234.56',
					'PERIOD:COMMA' => '1.234,56',
					'COMMA:PERIOD' => '1,234.56',
					'SPACE:PERIOD' => '1 234.56',
					'SPACE:COMMA' => '1 234,56',
					'QUOTE:PERIOD' => '1\'234.56',
				);
			} elseif ($datatype == 'date') {
				// date
				$formats[$fname] = array(
					'Y-m-d' => 'YYYY-MM-DD',
					'd/m/Y' => 'DD/MM/YYYY',
					'm/d/Y' => 'MM/DD/YYYY',
					'Ymd' => 'YYYYMMDD',
				);
			}
		}
		return $formats;
	}
	// crmv@83878e
	
	public static function loadBasicSettings($userInputObject, $user) {
		$moduleName = $userInputObject->get('module');
		$indexController = new Import_Index_Controller();

		$viewer = new Import_UI_Viewer();
		$viewer->assign('FOR_MODULE', $moduleName);
		$viewer->assign('SUPPORTED_FILE_TYPES', Import_Utils::getSupportedFileExtensions());
		$viewer->assign('SUPPORTED_FILE_ENCODING', Import_Utils::getSupportedFileEncoding());
		$viewer->assign('SUPPORTED_DELIMITERS', Import_Utils::getSupportedDelimiters());
		$viewer->assign('AUTO_MERGE_TYPES', Import_Utils::getAutoMergeTypes());
		$viewer->assign('AVAILABLE_FIELDS', $indexController->getMergableFields($moduleName));
		$viewer->assign('ENTITY_FIELDS', $indexController->getEntityFields($moduleName));
		$viewer->assign('ERROR_MESSAGE', $userInputObject->get('error_message'));
		$viewer->display('ImportBasic.tpl');
	}
	
	public static function loadAdvancedSettings($userInputObject, $user) {
		global $current_user;

		$moduleName = $userInputObject->get('module');
		$indexController = new Import_Index_Controller();

		$fileReader = Import_Utils::getFileReader($userInputObject, $current_user);
		if($fileReader == null) {
			$userInputObject->set('error_message', getTranslatedString('LBL_INVALID_FILE', 'Import'));
			Import_Index_Controller::loadBasicSettings($userInputObject, $user);
			exit;
		}

		$hasHeader = $fileReader->hasHeader();
		$rowData = $fileReader->getFirstRowData($hasHeader);

		$autoMerge = $userInputObject->get('auto_merge');
		if(!$autoMerge) {
			$userInputObject->set('merge_type', 0);
			$userInputObject->set('merge_fields', '');
		}

		// crmv@83878
		$availFields = $indexController->getImportableFields($moduleName);
		
		$viewer = new Import_UI_Viewer();
		$viewer->assign('CURRENT_USER', $current_user);	//crmv@49510
		$viewer->assign('FOR_MODULE', $moduleName);
		$viewer->assign('AVAILABLE_FIELDS', $availFields);
		$viewer->assign('FIELDS_FORMATS', $indexController->getFieldFormats($availFields));
		$viewer->assign('HAS_HEADER', $hasHeader);
		$viewer->assign('ROW_1_DATA', $rowData);
		$viewer->assign('USER_INPUT', $userInputObject);
		// crmv@92218
		$viewer->assign('SUPPORTED_FILE_ENCODING', Import_Utils::getSupportedFileEncoding());
		$viewer->assign('DETECTED_ENCODING', $fileReader->getDetectedEncoding());
		$viewer->assign('CHOOSEN_ENCODING', $userInputObject->get('file_encoding'));
		// crmv@92218e
		$viewer->assign('ENCODED_MANDATORY_FIELDS',
						Zend_Json::encode($indexController->getMandatoryFields($moduleName)));
		$viewer->assign('SAVED_MAPS', Import_Map::getAllByModule($moduleName));
		$viewer->assign('USERS_LIST', Import_Utils::getAssignedToUserList($moduleName));
		$viewer->assign('GROUPS_LIST', Import_Utils::getAssignedToGroupList($moduleName));
		$viewer->display('ImportAdvanced.tpl');
		// crmv@83878e
	}

	public static function validateFileUpload($userInputObject) {
		global $current_user;

		$uploadMaxSize = Import_Utils::getMaxUploadSize();
		$importDirectory = Import_Utils::getImportDirectory();
		$temporaryFileName = Import_Utils::getImportFilePath($current_user);

		if(!is_uploaded_file($_FILES['import_file']['tmp_name'])) {
			$userInputObject->set('error_message', getTranslatedString('LBL_FILE_UPLOAD_FAILED', 'Import'));
			return false;
		}
		if ($_FILES['userfile']['size'] > $uploadMaxSize) {
			$userInputObject->set('error_message', getTranslatedString('LBL_IMPORT_ERROR_LARGE_FILE', 'Import').
												' $uploadMaxSize.'.getTranslatedString('LBL_IMPORT_CHANGE_UPLOAD_SIZE', 'Import'));
			return false;
		}
		if(!is_writable($importDirectory)) {
			$userInputObject->set('error_message', getTranslatedString('LBL_IMPORT_DIRECTORY_NOT_WRITABLE', 'Import'));
			return false;
		}

		$fileCopied = move_uploaded_file($_FILES['import_file']['tmp_name'], $temporaryFileName);
		if(!$fileCopied) {
			$userInputObject->set('error_message', getTranslatedString('LBL_IMPORT_FILE_COPY_FAILED', 'Import'));
			return false;
		}
		$fileReader = Import_Utils::getFileReader($userInputObject, $current_user);

		if($fileReader == null) {
			$userInputObject->set('error_message', getTranslatedString('LBL_INVALID_FILE', 'Import'));
			return false;
		}

		$hasHeader = $fileReader->hasHeader();
		$firstRow = $fileReader->getFirstRowData($hasHeader);
		if($firstRow === false) {
			//crmv@38558
			if($fileReader->status == 'failed' && $fileReader->errorMessage != ''){
				$userInputObject->set('error_message', getTranslatedString($fileReader->errorMessage, 'Import'));
			}
			else{
			//crmv@38558e
				$userInputObject->set('error_message', getTranslatedString('LBL_NO_ROWS_FOUND', 'Import'));
			} //crmv@38558
			return false;
		}
		return true;
	}

	public static function undoLastImport($userInputObject, $user) {
		$adb = PearDatabase::getInstance();
		$moduleName = $userInputObject->get('module');
		$ownerId = $userInputObject->get('foruser');
		$owner = CRMEntity::getInstance('Users');
		$owner->id = $ownerId;
		$owner->retrieve_entity_info($ownerId, 'Users');
		$dbTableName = Import_Utils::getDbTableName($owner);
		if(!is_admin($user) && $user->id != $owner->id) {
			$viewer = new Import_UI_Viewer();
			$viewer->display('OperationNotPermitted.tpl', 'Vtiger');
			exit;
		}
		$result = $adb->query("SELECT recordid FROM $dbTableName WHERE status = ". Import_Data_Controller::$IMPORT_RECORD_CREATED
									." AND recordid IS NOT NULL");
		$noOfRecords = $adb->num_rows($result);
		$noOfRecordsDeleted = 0;
		for($i=0; $i<$noOfRecords; ++$i) {
			$recordId = $adb->query_result($result, $i, 'recordid');
			if(isRecordExists($recordId) && isPermitted($moduleName, 'Delete', $recordId) == 'yes') {
				$focus = CRMEntity::getInstance($moduleName);
				$focus->id = $recordId;
				$focus->trash($moduleName, $recordId);
				$noOfRecordsDeleted++;
			}
		}

		$viewer = new Import_UI_Viewer();
		$viewer->assign('FOR_MODULE', $moduleName);
		$viewer->assign('TOTAL_RECORDS', $noOfRecords);
		$viewer->assign('DELETED_RECORDS_COUNT', $noOfRecordsDeleted);
		$viewer->display('ImportUndoResult.tpl');
	}

	public static function deleteMap($userInputObject, $user) {
		$adb = PearDatabase::getInstance();
		$moduleName = $userInputObject->get('module');
		$mapId = $userInputObject->get('mapid');
		Import_Map::markAsDeleted($mapId);

		$viewer = new Import_UI_Viewer();
		$viewer->assign('FOR_MODULE', $moduleName);
		$viewer->assign('SAVED_MAPS', Import_Map::getAllByModule($moduleName));
		echo $viewer->fetch('Import_Saved_Maps.tpl');
	}

	public static function process($requestObject, $user) {

		$moduleName = $requestObject->get('module');
		$mode = $requestObject->get('mode');

		if($mode == 'undo_import') {
			Import_Index_Controller::undoLastImport($requestObject, $user);
			exit;
		} elseif($mode == 'listview') {
			Import_ListView_Controller::render($requestObject, $user);
			exit;
		} elseif($mode == 'delete_map') {
			Import_Index_Controller::deleteMap($requestObject, $user);
			exit;
		} elseif($mode == 'clear_corrupted_data') {
			Import_Utils::clearUserImportInfo($user);
		} elseif($mode == 'cancel_import') {
			$importId = $requestObject->get('import_id');
			$importInfo = Import_Queue_Controller::getImportInfoById($importId);
			if($importInfo != null) {
				if($importInfo['user_id'] == $user->id || is_admin($user)) {
					$importuser = CRMEntity::getInstance('Users');
					$importuser->id = $importInfo['user_id'];
					$importuser->retrieve_entity_info($importInfo['user_id'], 'Users');
					$importDataController = new Import_Data_Controller($importInfo, $importuser);
					$importStatusCount = $importDataController->getImportStatusCount();
					$importDataController->finishImport();
					Import_Controller::showResult($importInfo, $importStatusCount);
				}
				exit;
			}
		}

		// Check if import on the module is locked
		$lockInfo = Import_Lock_Controller::isLockedForModule($moduleName);
		if($lockInfo != null) {
			$lockedBy = $lockInfo['userid'];
			if($user->id != $lockedBy && !is_admin($user)) {
				Import_Utils::showImportLockedError($lockInfo);
				exit;
			} else {
				if($mode == 'continue_import' && $user->id == $lockedBy) {
					$importController = new Import_Controller($requestObject, $user);
					$importController->triggerImport(true);
				} else {
					$importInfo = Import_Queue_Controller::getImportInfoById($lockInfo['importid']);
					$lockOwner = $user;
					if($user->id != $lockedBy) {
						$lockOwner = CRMEntity::getInstance('Users');
						$lockOwner->id = $lockInfo['userid'];
						$lockOwner->retrieve_entity_info( $lockInfo['userid'], 'Users');
					}
					Import_Controller::showImportStatus($importInfo, $lockOwner);
				}
				exit;

			}
		}

		if(Import_Utils::isUserImportBlocked($user)) {
			$importInfo = Import_Queue_Controller::getUserCurrentImportInfo($user);
			if($importInfo != null) {
				Import_Controller::showImportStatus($importInfo, $user);
				exit;
			} else {
				Import_Utils::showImportTableBlockedError($moduleName, $user);
				exit;
			}
		}
		Import_Utils::clearUserImportInfo($user);

		if($mode == 'upload_and_parse') {
			if(Import_Index_Controller::validateFileUpload($requestObject)) {
				Import_Index_Controller::loadAdvancedSettings($requestObject, $user);
				exit;
			}
		// crmv@92218
		} elseif ($mode == 'reload_mapping') {
			Import_Index_Controller::loadAdvancedSettings($requestObject, $user);
			exit;
		// crmv@92218e
		} elseif($mode == 'import') {
			Import_Controller::import($requestObject, $user);
			exit;
		}

		Import_Index_Controller::loadBasicSettings($requestObject, $user);
	}
}

?>