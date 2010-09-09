<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * Description
 *
 * @author marcus@silverstripe.com.au
 */
class MemberHierarchy extends DataObjectDecorator {
    public function  extraStatics() {
		return array(
			'has_one' => array(
				'Parent' => 'Member'
			)
		);
	}
}