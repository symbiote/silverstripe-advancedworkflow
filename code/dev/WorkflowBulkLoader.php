<?php
/**
 * Utility class to facilitate a simple YML-import via the standard CMS ImportForm() logic.
 *
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowBulkLoader extends BulkLoader {
	
	/**
	 * @inheritDoc
	 */
	public function preview($filepath) {
		return $this->processAll($filepath, true);
	}
	
	/**
	 * @param string $filepath
	 * @param boolean $preview
	 */
	protected function processAll($filepath, $preview = false) {
		$results = new BulkLoader_Result();
		
		try {
			$yml = singleton('WorkflowDefinitionImporter')->parseYAMLImport($filepath);
			$this->processRecord($yml, $this->columnMap, $results, $preview);
			return $results;			
		} catch(ValidationException $e) {
			return new BulkLoader_Result();
		}
	}
	
	/**
	 * @param array $record
	 * @param array $columnMap
	 * @param BulkLoader_Result $results
	 * @param boolean $preview
	 * @return number
	 */
	protected function processRecord($record, $columnMap, &$results, $preview = false) {
		$posted = Controller::curr()->getRequest()->postVars();
		$default = WorkflowDefinitionExporter::$export_filename_prefix.'0.yml';
		$filename = (isset($posted['_CsvFile']['name']) ? $posted['_CsvFile']['name'] : $default);
		
		// @todo is this the best way to extract records (nested array keys)??
		$struct = $record['Injector']['ExportedWorkflow'];
		$name = $struct['constructor'][0];		
		$import = $this->createImport($name, $filename, $record);
		
		$template = Injector::inst()->createWithArgs('WorkflowTemplate', $struct['constructor']);
		$template->setStructure($struct['properties']['structure']);

		$def = WorkflowDefinition::create();
		$def->workflowService = singleton('WorkflowService');
		$def->Template = $template->getName();
		$obj = $def->workflowService->defineFromTemplate($def, $def->Template);
		
		$results->addCreated($obj, '');
		$objID = $obj->ID;
		
		// Update the import
		$import->DefinitionID = $objID;
		$import->write();
		
		$obj->destroy();
		unset($obj);
		
		return $objID;
	}
	
	/**
	 * Create the ImportedWorkflowTemplate record for the uploaded YML file.
	 * 
	 * @param string $name
	 * @param string $filename
	 * @param array $record
	 * @return ImportedWorkflowTemplate $import
	 */
	protected function createImport($name, $filename, $record) {
		// This is needed to feed WorkflowService#getNamedTemplate()
		$import = ImportedWorkflowTemplate::create();
		$import->Name = $name;
		$import->Filename = $filename;
		$import->Content = serialize($record);
		$import->write();
		
		return $import;
	}
}
