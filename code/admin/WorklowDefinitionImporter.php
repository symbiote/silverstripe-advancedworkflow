<?php
/**
 * Workflow definition import-specific logic. @see {@link WorkflowDefinitionExporter}.
 * 
 * @author  russell@silverstripe.com
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class WorkflowDefinitionImporter {
	
	/**
	 * Generates an array of WorkflowTemplate Objects of all uploaded workflows.
	 * 
	 * @param string $name. If set, a single-value array comprising a WorkflowTemplate object who's first constructor param matches $name
	 *						is returned.
	 * @return WorkflowTemplate $template | array $importedDefs
	 */
	public function getImportedWorkflows($name = null) {
		$imports = DataObject::get('ImportedWorkflowTemplate');
		$importedDefs = array();
		foreach($imports as $import) {
			if(!$import->Content) {
				continue;
			}
			$structure = unserialize($import->Content);
			$struct = $structure['Injector']['ExportedWorkflow'];
			$template = Injector::inst()->createWithArgs('WorkflowTemplate', $struct['constructor']);
			$template->setStructure($struct['properties']['structure']);
			if($name) {
				if($struct['constructor'][0] == trim($name)) {
					return $template;
				}
				continue;
			}
			$importedDefs[] = $template;
		}
		return $importedDefs;
	}
	
	/**
	 * Handles finding and parsing YAML input as a string or from the contents of a file.
	 * 
	 * @see addYAMLConfigFile() on {@link SS_ConfigManifest} from where this logic was taken and adapted.
	 * @param string $source YAML as a string or a filename
	 * @return array
	 */
	public function parseYAMLImport($source) {
		if(is_file($source)) {
			$source = file_get_contents($source);
		}

		require_once('thirdparty/zend_translate_railsyaml/library/Translate/Adapter/thirdparty/sfYaml/lib/sfYamlParser.php');
		$parser = new sfYamlParser();
		
		// Make sure the linefeeds are all converted to \n, PCRE '$' will not match anything else.
		$convertLF = str_replace(array("\r\n", "\r"), "\n", $source);
		/*
		 * Remove illegal colons from Transition/Action titles, otherwise sfYamlParser will barf on them
		 * Note: The regex relies on there being single quotes wrapped around these in the export .ss template
		 */
		$converted = preg_replace("#('[^:\n][^']+)(:)([^']+')#", "$1;$3", $convertLF);
		$parts = preg_split('#^---$#m', $converted, -1, PREG_SPLIT_NO_EMPTY);

		// If we got an odd number of parts the config, file doesn't have a header.
		// We know in advance the number of blocks imported content will have so we settle for a count()==2 check.
		if(count($parts) != 2) {
			$msg = _t('WorkflowDefinitionImporter.INVALID_YML_FORMAT_NO_HEADER', 'Invalid YAML format.');
			throw new ValidationException($msg);
		}
		
		try {
			$parsed = $parser->parse($parts[1]);
			return $parsed;
		} catch (Exception $e) {
			$msg = _t('WorkflowDefinitionImporter.INVALID_YML_FORMAT_NO_PARSE', 'Invalid YAML format. Unable to parse.');
			throw new ValidationException($msg);
		}
	}		
}
