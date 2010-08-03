<?php

	Class FieldViews extends FieldSelect {
		public function __construct(){
			parent::__construct();
			$this->_name = __('Views Select Box');
		}

		/*-------------------------------------------------------------------------
			Utilities:
		-------------------------------------------------------------------------*/

			public function getToggleStates() {
				$types = null;
				$views = array();
				$options = array();

				if(!empty($this->{'view-types'})) {
					$types = explode(",", $this->{'view-types'});
					$types = array_map('trim', $types);
				}

				if(is_array($types)) foreach($types as $type) {
					$matched_views = View::findFromType($type);

					if(!empty($matched_views)) $views = array_merge($views, $matched_views);
				}
				else $views = new ViewIterator;

				foreach($views as $view) {
					$options[$view->handle] = $view->title;
				}

				return $options;
			}

			/*
			**	Stolen from contentBlueprints
			*/
			public static function __fetchAvailableViewTypes(){

				// TODO: Delegate here so extensions can add custom view types?
				$types = array('index', 'XML', 'admin', '404', '403');

				foreach(View::fetchUsedTypes() as $t){
					$types[] = $t;
				}

				return General::array_remove_duplicates($types);

			}

		/*-------------------------------------------------------------------------
			Settings:
		-------------------------------------------------------------------------*/

			public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $messages) {
				Field::displaySettingsPanel($wrapper, $messages);

				$document = $wrapper->ownerDocument;

				$label = Widget::Label(__('View Type'));
				$label->appendChild(
					Widget::Input('view-types', $this->{'view-types'})
				);

				$tags = $document->createElement('ul');
				$tags->setAttribute('class', 'tags');

				foreach(self::__fetchAvailableViewTypes() as $t){
					$tags->appendChild($document->createElement('li', $t));
				}

				$wrapper->appendChild($label);
				$wrapper->appendChild($tags);

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

	return 'FieldViews';