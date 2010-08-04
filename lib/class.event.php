<?php

	require_once LIB . '/class.entry.php';
	require_once LIB . '/class.event.php';

	class EmailEvent extends Event {
		
		public function __construct(){
			// Set Default Values
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element' => null,
				'trigger' => null,
				'subject' => null,
				'sender-name' => null,
				'sender-addresses' => null,
				'recipient-addresses' => null,
				'view' => null,
				'parameters' => array(),
			);
		}

		public function getType() {
			return 'EmailEvent';
		}

		public function getTemplate(){
			return EXTENSIONS . '/emailtemplatefilter/templates/template.event.php';
		}

	/*-----------------------------------------------------------------------*/

		public function prepare(array $data = null) {
			if (!is_null($data)) {
				$this->about()->name = $data['name'];

				$this->about()->author->name = Administration::instance()->User->getFullName();
				$this->about()->author->email = Administration::instance()->User->email;

				$this->parameters()->trigger = $data['trigger'];
				$this->parameters()->subject = $data['subject'];
				$this->parameters()->{'sender-name'} = $data['sender-name'];
				$this->parameters()->{'sender-addresses'} = $data['sender-addresses'];
				$this->parameters()->{'recipient-addresses'} = $data['recipient-addresses'];
				$this->parameters()->view = $data['view'];

				if(isset($data['parameters']) && is_array($data['parameters']) || !empty($data['parameters'])){
					$parameters = array();
					foreach($data['parameters']['param'] as $index => $param){
						$parameters[$param] = $data['parameters']['value'][$index];
					}
					$this->parameters()->parameters = $parameters;
				}
			}
		}

		public function view(SymphonyDOMElement $wrapper, MessageStack $errors) {
			$page = Administration::instance()->Page;

			$layout = new Layout();
			$column_1 = $layout->createColumn(Layout::SMALL);
			$column_2 = $layout->createColumn(Layout::SMALL);
			$column_3 = $layout->createColumn(Layout::LARGE);

			$fieldset = Widget::Fieldset(__('Essentials'));

			// Name:
			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input('fields[name]', General::sanitize($this->about()->name)));

			if (isset($errors->{'about::name'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'about::name'});
			}

			$fieldset->appendChild($label);

			// Expression:
			$label = Widget::Label(__('Trigger'));
			$label->appendChild(Widget::Textarea(
				'fields[trigger]', General::sanitize($this->parameters()->{'trigger'}),
				array(
					'rows'	=> 3
				)
			));

			if (isset($errors->{'trigger'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'trigger'});
			}

			$fieldset->appendChild($label);

			$help = $page->createElement('p');
			$help->addClass('help');
			$help->setValue(__('Enter an XPath expression to trigger sending this email.'));
			$fieldset->appendChild($help);

			$column_1->appendChild($fieldset);

			$fieldset = Widget::Fieldset(__('Meta Data'));

			// Subject:
			$label = Widget::Label(__('Subject'));
			$label->appendChild(Widget::Input(
				'fields[subject]', General::sanitize($this->parameters()->{'subject'})
			));

			if (isset($errors->{'subject'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'subject'});
			}

			$fieldset->appendChild($label);

			// Sender Name:
			$label = Widget::Label(__('Sender Name'));
			$label->appendChild(Widget::Input(
				'fields[sender-name]', General::sanitize($this->parameters()->{'sender-name'})
			));

			if (isset($errors->{'sender-name'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'sender-name'});
			}

			$fieldset->appendChild($label);

			// Sender Address(es):
			$label = Widget::Label(__('Sender Address(es)'));
			$label->appendChild(Widget::Input(
				'fields[sender-addresses]', General::sanitize($this->parameters()->{'sender-addresses'})
			));

			if (isset($errors->{'sender-addresses'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'sender-addresses'});
			}

			$fieldset->appendChild($label);

			// Recipient Address(es):
			$label = Widget::Label(__('Recipient Address(es)'));
			$label->appendChild(Widget::Input(
				'fields[recipient-addresses]', General::sanitize($this->parameters()->{'recipient-addresses'})
			));

			if (isset($errors->{'recipient-addresses'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'recipient-addresses'});
			}

			$fieldset->appendChild($label);

			$help = $page->createElement('p');
			$help->addClass('help');
			$text = $page->createDocumentFragment();
			$text->appendXML(__('To access the current document, use XPath expressions: <code>{datasource/entry/...}</code>'));
			$help->appendChild($text);
			$fieldset->appendChild($help);

			$column_2->appendChild($fieldset);

			$fieldset = Widget::Fieldset(__('Template'));

			// View:
			$label = Widget::Label(__('View'));
			$options = array();

			foreach (new ViewIterator() as $view) {
				$options[] = array(
					$view->path,
					($view->path == $this->parameters()->{'view'}),
					$view->path
				);
			}

			$select = Widget::Select('fields[view]', $options);

			$label->appendChild($select);
			$fieldset->appendChild($label);

			// URL Parameters:
			$label = $page->createElement('h4', __('Parameters'));
			$fieldset->appendChild($label);

			$this->appendDuplicator($fieldset, $this->parameters()->parameters);

			$column_3->appendChild($fieldset);

			$layout->appendTo($wrapper);
		}

		protected function appendDuplicator(SymphonyDOMElement $wrapper, array $items = null) {
			$document = $wrapper->ownerDocument;

			$duplicator = new Duplicator(__('Add Item'));
			$item = $duplicator->createTemplate(__('Parameter'));
			$label = Widget::Label(__('Name'));
			$options = array(
				array(
					'entry-id', true, 'entry-id'
				)
			);

			$label->appendChild(Widget::Select('fields[parameters][param][]', $options));
			$item->appendChild($label);

			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Textarea(
				'fields[parameters][value][]', null,
				array(
					'rows'	=> 2
				)
			));
			$item->appendChild($label);

			$help = $document->createElement('p');
			$help->addClass('help');
			$help->setValue(__('Enter an XPath expression to set the value of this parameter.'));
			$item->appendChild($help);

			if(is_array($items)){
				foreach($items as $param => $xpath) {
					$item = $duplicator->createInstance(__('Parameter'));
					$label = Widget::Label(__('Parameter'));

					$label->appendChild(Widget::Select('fields[parameters][param][]', $options));
					$item->appendChild($label);

					$label = Widget::Label(__('Value'));
					$label->appendChild(Widget::Textarea(
						'fields[parameters][value][]', General::sanitize($xpath),
						array(
							'rows'	=> 2
						)
					));
					$item->appendChild($label);
				}
			}

			$duplicator->appendTo($wrapper);
		}

	/*-----------------------------------------------------------------------*/

		/*
		**	Email Event always triggers, it's up to the Delegate in the
		**	extension driver to determine whether it runs though
		*/
		public function canTrigger(array $data) {
			return true;
		}

		public function trigger(Register $ParameterOutput, array $postdata){
			Extension_EmailTemplateFilter::$events[] = $this;
		}

	}

?>