<?php

	Class FieldXPath extends FieldTextbox {
		public function __construct(){
			parent::__construct();
			$this->_name = __('XPath Box');
		}
		
		/*-------------------------------------------------------------------------
			Settings:
		-------------------------------------------------------------------------*/

			public function findDefaultSettings(&$fields) {
				$fields['column-length'] = 75;
				$fields['text-size'] = 'single';
				$fields['text-length'] = 'none';
				$fields['text-handle'] = 'no';
				$fields['text-cdata'] = 'no';
			}			
			
	}
	

	return 'FieldXPath';