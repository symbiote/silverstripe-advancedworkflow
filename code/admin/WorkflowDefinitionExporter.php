<?php
/**
 * Allows workflow definitions to be exported from one SilverStripe install, ready for import into another.
 * 
 * YAML is used for export as it's native to SilverStripe's config system and we're using {@link WorkflowTemplate}
 * for some of the import-specific heavy lifting, which is already heavily predicated on YAML.
 * 
 * @todo
 *	- If workflow-def is created badly, the "update template definition" logic, sometimes doesn't work
 * 
 * @author  russell@silverstripe.com
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowDefinitionExporter {
	
	/**
	 * The base filename of the file to the exported
	 * 
	 * @var string
	 */
	public static $export_filename_prefix = 'workflow-definition-export';
	/**
	 *
	 * @var \Member
	 */
	protected $member;
	/**
	 * 
	 * @var \WorkflowDefinition
	 */
	protected $workflowDefinition;
	
	/**
	 * 
	 * @param number $definitionID
	 * @return void
	 */
	public function __construct($definitionID) {
		$this->setMember(Member::currentUser());
		$this->workflowDefinition = DataObject::get_by_id('WorkflowDefinition', $definitionID);		
	}
	
	/**
	 * 
	 * @param \Member $member
	 */
	public function setMember($member) {
		$this->member = $member;
	}
	
	/**
	 * @return \WorkflowDefinition
	 */
	public function getDefinition() {
		return $this->workflowDefinition;
	}
	
	/**
	 * Runs the export 
	 * 
	 * @return string $template
	 */
	public function export() {
		// Disable any access to use of WorkflowExport if user has no SecurityAdmin access
		if(!Permission::check('CMS_ACCESS_SecurityAdmin')) {
			throw Exception(_t('ErrorPage.403'), 403);
		}		
		$def = $this->getDefinition();
		$templateData = new ArrayData(array(
			'ExportMetaData' => $this->ExportMetaData(),
			'ExportActions' => $def->Actions(),
			'ExportUsers' => $def->Users(),
			'ExportGroups' => $def->Groups() 
		));
		return $this->format($templateData);
	}
	
	/**
	 * Format the exported data as YAML.
	 * 
	 * @param \ArrayData $templateData
	 * @return void
	 */
	public function format($templateData) {
		$templateFile = BASE_PATH.'/'.ADVANCED_WORKFLOW_DIR.'/templates/Includes/WorkflowDefinitionExport.ss';
		$viewer = SSViewer::execute_template($templateFile, $templateData);
		// Temporary until we find the source of the replacement in SSViewer
		$processed = str_replace('&amp;', '&', $viewer);
		// Clean-up newline "gaps" that SSViewer leaves behind from the placement of template control structures
		return preg_replace("#^\R+|^[\t\s]*\R+#m", '', $processed);
	}
	
	/**
	 * Returns the size of the current export in bytes.
	 * Used for pushing data to the browser to prompt for download
	 * 
	 * @param string $str
	 * @return number $bytes
	 */
	public function getExportSize($str) {
		return mb_strlen($str, 'UTF-8');
	}

	/**
	 * Generate template vars for metadata
	 * 
	 * @return ArrayData
	 */
	public function ExportMetaData() {
		$def = $this->getDefinition();
		return new ArrayData(array(
			'ExportHost' => preg_replace("#http(s)?://#", '', Director::protocolAndHost()),
			'ExportDate' => date('d/m/Y H-i-s'),
			'ExportUser' => $this->member->FirstName.' '.$this->member->Surname,
			'ExportVersionFramework' => $this->ssVersion(),
			'ExportWorkflowDefName' => $this->processTitle($def->Title),
			'ExportRemindDays' => $def->RemindDays,
			'ExportSort' => $def->Sort
		));
	}
	
	/*
	 * Try different ways of obtaining the current SilverStripe version for YAML output.
	 * 
	 * @return string
	 */
	private function ssVersion() {
		// Remove colons so they don't screw with YAML parsing
		$versionSapphire = str_replace(':', '', singleton('SapphireInfo')->Version());
		$versionLeftMain = str_replace(':', '', singleton('LeftAndMain')->CMSVersion());
		if($versionSapphire != _t('LeftAndMain.VersionUnknown')) {
			return $versionSapphire;
		}
		return $versionLeftMain;
	}
	
	private function processTitle($title) {
		// If an import is exported and re-imported, the new export date is appended to Title, making for a very long title
		return preg_replace("#\s[\d]+\/[\d]+\/[\d]+\s[\d]+-[\d]+-[\d]+(\s[\d]+)?#", '', $title);	
	}
	
	/**
	 * Prompt the client for file download.
	 * We're "overriding" SS_HTTPRequest::send_file() for more robust cross-browser support
	 * 
	 * @param array $filedata
	 * @return \SS_HTTPResponse $response
	 */
	public function sendFile($filedata) {
		$response = new SS_HTTPResponse($filedata['body']);
		if(preg_match("#MSIE\s(6|7|8)?\.0#",$_SERVER['HTTP_USER_AGENT'])) {
			// IE headers
			$response->addHeader("Cache-Control","public");
			$response->addHeader("Content-Disposition","attachment; filename=\"".basename($filedata['name'])."\"");
			$response->addHeader("Content-Type","application/force-download");
			$response->addHeader("Content-Type","application/octet-stream");
			$response->addHeader("Content-Type","application/download");
			$response->addHeader("Content-Type",$filedata['mime']);
			$response->addHeader("Content-Description","File Transfer");
			$response->addHeader("Content-Length",$filedata['size']);	
		}
		else {
			// Everyone else
			$response->addHeader("Content-Type", $filedata['mime']."; name=\"".addslashes($filedata['name'])."\"");
			$response->addHeader("Content-disposition", "attachment; filename=".addslashes($filedata['name']));
			$response->addHeader("Content-Length",$filedata['size']);
		}
		return $response;
	}
	
}
