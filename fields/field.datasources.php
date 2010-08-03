<?php

	Class FieldDatasources extends FieldSelect {
		public function __construct(){
			parent::__construct();
			$this->_name = __('Datasources Select Box');
		}

		/*-------------------------------------------------------------------------
			Utilities:
		-------------------------------------------------------------------------*/

			public function getToggleStates() {
				$datasources = new DatasourceIterator;
				$options = array();
				
				foreach($datasources as $pathname) {
					$ds = DataSource::load($pathname);
					$handle = DataSource::getHandleFromFilename($pathname);
					
					$options[$handle] = $ds->about()->name;
				}

				return $options;
			}

		/*-------------------------------------------------------------------------
			Settings:
		-------------------------------------------------------------------------*/

			public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $messages) {
				Field::displaySettingsPanel($wrapper, $messages);

				$document = $wrapper->ownerDocument;

				$options_list = $document->createElement('ul');
				$options_list->setAttribute('class', 'options-list');

				$this->appendShowColumnCheckbox($options_list);
				$this->appendRequiredCheckbox($options_list);

				## Allow selection of multiple items
				$label = Widget::Label(__('Allow selection of multiple options'));

				$input = Widget::Input('allow-multiple-selection', 'yes', 'checkbox');
				if($this->{'allow-multiple-selection'} == 'yes') $input->setAttribute('checked', 'checked');

				$label->prependChild($input);
				$options_list->appendChild($label);

				$wrapper->appendChild($options_list);
			}


		/*-------------------------------------------------------------------------
			Publish:
		-------------------------------------------------------------------------*/

			public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry = null, $data = null) {
				parent::displayPublishPanel($wrapper, $errors, $entry, $data);
			}

		/*-------------------------------------------------------------------------
			Filtering:
		-------------------------------------------------------------------------*/

			public function displayDatasourceFilterPanel(SymphonyDOMElement $wrapper, $data=NULL, MessageStack $errors=NULL){
				Field::displayDatasourceFilterPanel($wrapper, $data, $errors);
			}
	}

	return 'FieldDatasources';