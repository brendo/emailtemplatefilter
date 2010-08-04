<?php

	require_once(LIB . '/class.administrationpage.php');

	class contentExtensionEmailTemplateFilterTemplates extends AdministrationPage {

		protected $driver = null;
		protected $uri = null;

		protected $_action = '';
		protected $_conditions = array();
		protected $_editing = false;

		protected $_fields = array();
		protected $_prepared = false;
		protected $_status = '';
		protected $_templates = array();

		protected $_valid = true;

		public function __construct(){
			parent::__construct();

			$this->uri = URL . '/symphony/extension/emailtemplatefilter';
			$this->driver = Extension::Load('emailtemplatefilter');
			$this->errors = new MessageStack;
		}

		public function build($context) {
			if (@$context[0] == 'edit' or @$context[0] == 'new') {
				if ($this->_editing = ($context[0] == 'edit')) {
					$this->_fields = $this->driver->getTemplate((integer)$context[1]);
					$this->_conditions = $this->driver->getConditions((integer)$context[1]);
				}

				$this->_fields = (isset($_POST['fields']) ? (object)$_POST['fields'] : $this->_fields);
				$this->_conditions = (isset($_POST['conditions']) ? (object)$_POST['conditions'] : $this->_conditions);
				$this->_status = $context[2];

			} else {
				$this->_templates = $this->driver->getTemplates();
			}

			parent::build($context);
		}

		public function __actionNew() {
			$this->__actionEdit();
		}

		public function __actionEdit() {
			if (@array_key_exists('delete', $_POST['action'])) {

				Symphony::Database()->delete('tbl_etf_templates', array($this->_fields->id), " `id` = %d");
				Symphony::Database()->delete('tbl_etf_conditions', array($this->_fields->id), " `template_id` = %d");

				redirect("{$this->uri}/templates/");

			} else {
				$this->__actionEditNormal();
			}
		}

		public function __actionEditNormal() {
			//header('content-type: text/plain');

		// Validate: ----------------------------------------------------------

			if (empty($this->_fields->name)) {
				$this->errors->name = __('Name must not be empty.');
			}

			foreach ($this->_conditions as $sortorder => $condition) {
				if (empty($condition['subject'])) {
					$this->errors->{$sortorder.':subject'} = __('Subject must not be empty.');
				}

				if (empty($condition['sender'])) {
					$this->errors->{$sortorder.':sender'} = __('Sender Name must not be empty.');
				}

				if (empty($condition['senders'])) {
					$this->errors->{$sortorder.':senders'} = __('Senders must not be empty.');
				}

				if (empty($condition['recipients'])) {
					$this->errors->{$sortorder.':recipients'} = __('Recipients must not be empty.');
				}

				if (empty($condition['page'])) {
					$this->errors->{$sortorder.':page'} = __('Page must not be empty.');
				}
			}

			if ($this->errors->length() !== 0) {
				$this->_valid = false;
				return;
			}

		// Save: --------------------------------------------------------------
			$this->_fields->conditions = 1;
			$this->_fields->datasources = implode(',', $this->_fields->datasources);

			Symphony::Database()->insert('tbl_etf_templates', (array)$this->_fields, Database::UPDATE_ON_DUPLICATE);

			if (!$this->_editing) {
				$redirect_mode = 'created';
				$template_id = Symphony::Database()->query("
					SELECT
						e.id
					FROM
						`tbl_etf_templates` AS e
					ORDER BY
						e.id DESC
					LIMIT 1
				")->current()->id;

			} else {
				$redirect_mode = 'saved';
				$template_id = $this->_fields->id;
			}

			// Remove all existing conditions before inserting the remaining ones
			Symphony::Database()->delete('tbl_etf_conditions', array($this->_fields->id), " `id` = %d");

			foreach ($this->_conditions as $condition) {
				$condition['template_id'] = $template_id;

				Symphony::Database()->insert('tbl_etf_conditions', $condition, Database::UPDATE_ON_DUPLICATE);
			}

			redirect("{$this->uri}/templates/edit/{$template_id}/{$redirect_mode}/");
		}

		public function __viewNew() {
			$this->__viewEdit();
		}

		public function __viewEdit() {
			$this->insertNodeIntoHead(
				$this->createStylesheetElement(URL . '/extensions/emailtemplatefilter/assets/templates.css')
			);

			if(!in_array($this->_context[0], array('new', 'edit'))) throw new AdministrationPageNotFoundException;

			$this->_editing = ($this->_context[0] == "edit");

		// Status: -----------------------------------------------------------

			if (!$this->_valid) $this->alerts()->append(
				__('An error occurred while processing this form. <a href="#error">See below for details.</a>'),
				AlertStack::ERROR
			);

			// Status message:
			if ($this->_status) {
				$action = null;

				switch($this->_status) {
					case 'saved': $action = '%1$s updated at %2$s. <a href="%3$s">Create another?</a> <a href="%4$s">View all %5$s</a>'; break;
					case 'created': $action = '%1$s created at %2$s. <a href="%3$s">Create another?</a> <a href="%4$s">View all %5$s</a>'; break;
				}

				if ($action) $this->alerts()->append(
					__(
						$action, array(
							__('Template'),
							DateTimeObj::get(__SYM_TIME_FORMAT__),
							URL . '/symphony/extension/emailtemplatefilter/templates/new/',
							URL . '/symphony/extension/emailtemplatefilter/templates/',
							__('Templates')
						)
					),
					AlertStack::SUCCESS
				);
			}

		// Header: ------------------------------------------------------------

			$this->setTitle(__(
				(!$this->_editing ? '%1$s &ndash; %2$s &ndash; Untitled' : '%1$s &ndash; %2$s &ndash; %3$s'),
				array(
					__('Symphony'),
					__('Email Templates'),
					$this->_fields->name
				)
			));

			$this->appendSubheading(
				!$this->_editing ? __('Untitled') : $this->_fields->name
			);

		// Form: --------------------------------------------------------------

			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$right = $layout->createColumn(Layout::LARGE);

			$fieldset = Widget::Fieldset(__('Essentials'));

			if (!empty($this->_fields->id)) {
				$fieldset->appendChild(Widget::Input("fields[id]", $this->_fields->id, 'hidden'));
			}

			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input(
				'fields[name]', General::sanitize($this->_fields->name)
			));

			$fieldset->appendChild(
				isset($this->errors->name) ? Widget::wrapFormElementWithError($label, $this->errors->name) : $label
			);

		// Datasources --------------------------------------------------------

			$datasources = new DatasourceIterator;

			if(!is_array($this->_fields->datasources)) {
				$handles = explode(',', $this->_fields->datasources);
			}
			else $handles = $this->_fields->datasources;

			$options = array();

			foreach ($datasources as $pathname) {
				$ds = DataSource::load($pathname);
				$handle = DataSource::getHandleFromFilename($pathname);

				$selected = in_array($handle, $handles);

				$options[] = array(
					$handle, $selected, $ds->about()->name
				);
			}

			$label = Widget::Label(__('Datasources'));
			$label->appendChild(Widget::Select(
				"fields[datasources][]", $options,
				array('multiple' => 'multiple')
			));

			$help = $this->createElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('The parameter <code>%s</code> can be used in the selected datasources to get related data.', array('$etf-entry-id')));

			$fieldset->appendChild($label);
			$fieldset->appendChild($help);
			$left->appendChild($fieldset);

		// Conditions -------------------------------------------------------------

			$fieldset = Widget::Fieldset(__('Condition'));

			$wrapper = $this->createElement('div');
			$wrapper->setAttribute('class', 'template');

			$this->displayCondition($wrapper, '-1', isset($this->_conditions) ? $this->_conditions : (object)array(
				'type'		=> __('XPath Condition')
			));

			$fieldset->appendChild($wrapper);

			$right->appendChild($fieldset);
			$layout->appendTo($this->Form);

		// Footer: ------------------------------------------------------------

			$div = $this->createElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Submit(
					'action[save]', ($this->_editing ? __('Save Changes') : __('Create Template')),
					array(
						'accesskey' => 's'
					)
				)
			);

			if ($this->_editing) {
				$div->appendChild(
					Widget::Submit(
						'action[delete]', __('Delete'),
						array(
							'class' => 'confirm delete',
							'title' => __('Delete this template')
						)
					)
				);
			}

			$this->Form->appendChild($div);
		}

		protected function displayCondition(&$wrapper, $sortorder, $condition) {
			$wrapper->appendChild($this->createElement('h4', ucwords($condition->type)));
			$wrapper->appendChild(Widget::Input("conditions[{$sortorder}][type]", $condition->type, 'hidden'));

			if (!empty($condition->id)) {
				$wrapper->appendChild(Widget::Input("conditions[{$sortorder}][id]", $condition->id, 'hidden'));
			}

		// Subject ------------------------------------------------------------

			$label = Widget::Label(__('Subject'));
			$label->appendChild(Widget::Input(
				"conditions[{$sortorder}][subject]",
				General::sanitize($condition->subject)
			));

			if (isset($this->errors->{$sortorder.':subject'})) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->{$sortorder.':subject'});
			}

			$wrapper->appendChild($label);

		// Sender Name --------------------------------------------------------

			$label = Widget::Label(__('Sender Name'));
			$label->appendChild(Widget::Input(
				"conditions[{$sortorder}][sender]",
				General::sanitize($condition->sender)
			));

			if (isset($this->errors->{$sortorder.':sender'})) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->{$sortorder.':sender'});
			}

			$wrapper->appendChild($label);

		// Senders ------------------------------------------------------------

			$label = Widget::Label(__('Senders'));
			$label->appendChild(Widget::Input(
				"conditions[{$sortorder}][senders]",
				General::sanitize($condition->senders)
			));

			if (isset($this->errors->{$sortorder.':senders'})) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->{$sortorder.':senders'});
			}

			$wrapper->appendChild($label);

		// Recipients ---------------------------------------------------------

			$label = Widget::Label(__('Recipients'));
			$label->appendChild(Widget::Input(
				"conditions[{$sortorder}][recipients]",
				General::sanitize($condition->recipients)
			));

			if (isset($this->errors->{$sortorder.':recipients'})) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->{$sortorder.':recipients'});
			}

			$help = $this->createElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__(
				'To access the XML, use XPath expressions: <code>%s static text %s</code>.',
				array('{datasource/entry/field-one}', '{datasource/entry/field-two}')
			));

			$wrapper->appendChild($label);
			$wrapper->appendChild($help);

		// Expression ---------------------------------------------------------

			$wrapper->appendChild($this->createElement('h4', __('Advanced')));

			$label = Widget::Label(__('Expression'));
			$label->appendChild(Widget::Input(
				"conditions[{$sortorder}][expression]",
				General::sanitize($condition->expression)
			));

			if (isset($this->errors->{$sortorder.':expression'})) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->{$sortorder.':expression'});
			}

			$wrapper->appendChild($label);

		// Page ---------------------------------------------------------------

			$div = $this->createElement('div');
			$div->setAttribute('class', 'group');

			$label = Widget::Label(__('Page'));
			$options = array();

			foreach (new ViewIterator as $page) {
				$options[] = array(
					$page->path, ($page->handle == $condition->page), $page->title
				);
			}

			$label->appendChild(Widget::Select(
				"conditions[{$sortorder}][page]", $options
			));

			if (isset($this->errors->{$sortorder.':page'})) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->{$sortorder.':page'});
			}

			$div->appendChild($label);

		// Params -------------------------------------------------------------

			$label = Widget::Label(__('URL Parameters'));
			$label->appendChild(Widget::Input(
				"conditions[{$sortorder}][params]",
				General::sanitize($condition->params)
			));

			if (isset($this->errors->{$sortorder.':params'})) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->{$sortorder.':params'});
			}

			$div->appendChild($label);
			$wrapper->appendChild($div);
		}

	/*-------------------------------------------------------------------------
		Index
	-------------------------------------------------------------------------*/

		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);

			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach ($checked as $template_id) {
							Symphony::Database()->delete('tbl_etf_templates`', " `id` = " . $template_id);
							Symphony::Database()->delete('tbl_etf_conditions`', " `template_id` = " . $template_id);
						}

						redirect("{$this->uri}/templates/");
						break;
				}
			}
		}

		public function __viewIndex() {

			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Email Templates'));

			$this->appendSubheading(__('Templates'), Widget::Anchor(
				__('Create New'), $this->uri . '/templates/new/', array(
					'title' => __('Create a new email template'),
					'class' => 'create button'
				)
			));

			$tableHead = array(
				array(__('Template Name'), 'col'),
				array(__('Conditions'), 'col')
			);

			$tableBody = array();
			$colspan = count($tableHead);

			if($this->_templates->length() <= 0){
				$tableBody = array(Widget::TableRow(
					array(
						Widget::TableData(__('None found.'), array(
								'class' => 'inactive',
								'colspan' => $colspan
							)
						)
					), array(
						'class' => 'odd'
					)
				));
			} else {
				foreach ($this->_templates as $template) {
					$template = (object)$template;

					$col_name = Widget::TableData(
						Widget::Anchor(
							$template->name, sprintf('%s/templates/edit/%d', $this->uri, $template->id)
						)
					);
					$col_name->appendChild(Widget::Input("items[{$template->id}]", null, 'checkbox'));

					if (!empty($template->conditions)) {
						$col_conditions = Widget::TableData($template->conditions);

					} else {
						$col_conditions = Widget::TableData('None', array('class' => 'inactive'));
					}

					$tableBody[] = Widget::TableRow(array($col_name, $col_conditions), null);
				}
			}

			$table = Widget::Table(
				Widget::TableHead($tableHead), null, Widget::TableBody($tableBody)
			);

			$this->Form->appendChild($table);

			$actions = $this->createElement('div');
			$actions->setAttribute('class', 'actions');

			$options = array(
				array(null, false, __('With Selected...')),
				array('delete', false, 'Delete')
			);

			$actions->appendChild(Widget::Select('with-selected', $options));
			$actions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));

			$this->Form->appendChild($actions);
		}
	}

?>