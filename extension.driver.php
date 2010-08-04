<?php
	require_once 'lib/class.event.php';

	Class Extension_EmailTemplateFilter implements iExtension {

		public static $document = null;
		public static $events = array();

		public function about() {
			return (object)array(
				'name'			=> 'Email Template Filter',
				'version'		=> '2.0.0',
				'release-date'	=> '2010-08-04',
				'author'		=> (object)array(
					'name'			=> 'Rowan Lewis, Brendan Abbott',
					'website'		=> 'http://symphony-cms.com/',
					'email'			=> 'me@rowan-lewis.com'
				),
				'type'			=> array(
					'Email', 'Event'
				),
				'provides'		=> array(
					'datasource_template'
				),
			);
		}

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		public function uninstall() {
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_etf_logs`");
		}

		public function install() {
			Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_etf_logs` (
					`id` int(11) NOT NULL auto_increment,
					`template_id` int(11) NOT NULL,
					`entry_id` int(11) NOT NULL,
					`success` enum('yes','no') NOT NULL,
					`date` datetime NOT NULL,
					`subject` varchar(255),
					`sender` varchar(255),
					`senders` varchar(255),
					`recipients` varchar(255),
					`message` text,
					PRIMARY KEY (`id`)
				) TYPE=MyISAM
			");

			return true;
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendTemplatePreRender',
					'callback'	=> 'triggerEmail'
				)
			);
		}

		/*
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 250,
					'name'		=> 'Emails',
					'children'	=> array(
						array(
							'name'		=> 'Logs',
							'link'		=> '/logs/'
						)
					)
				)
			);
		}
		*/

		public function getEventTypes() {
			return array(
				(object)array(
					'class'		=> 'EmailEvent',
					'name'		=> __('Email Template')
				)
			);
		}

	/*-------------------------------------------------------------------------
		Utility functions:
	-------------------------------------------------------------------------*/

		public function countLogs() {
			return Symphony::Database()->query("
				SELECT
					COUNT(l.id) AS `total`
				FROM
					`tbl_etf_logs` AS l
			")->current()->total;
		}

		public function getLogs($page) {
			$rows = Symphony::Configuration()->core()->symphony->{'pagination-maximum-rows'};

			$results = Symphony::Database()->query(sprintf("
					SELECT
						l.*
					FROM
						`tbl_etf_logs` AS l
					ORDER BY
						l.date DESC
					LIMIT %d, %d
				", ($page - 1) * $rows, $rows
			));

			return ($results->valid()) ? $results : false;
		}

		public function getLog($log_id) {
			return Symphony::Database()->query("
				SELECT
					l.*
				FROM
					`tbl_etf_logs` AS l
				WHERE
					l.id = '{$log_id}'
				LIMIT 1
			")->current();
		}

	/*-------------------------------------------------------------------------
		Trigger functions:
	-------------------------------------------------------------------------*/

		public function triggerEmail($context) {
			$document = $context['document'];
			$xpath = new DOMXPath($document);

			if($xpath->evaluate('boolean(//parameters/document-render)')) return;

			foreach(self::$events as $event) {
				if($xpath->evaluate('boolean(' . $event->parameters()->trigger . ')')) {

					$entry_id = $xpath->evaluate('number(' . $event->parameters()->trigger . ')');

					self::$document = $document;

					$this->sendEmail($entry_id,	$event->parameters());
				}
			}
		}

		public function getTemplate($path, $entry_id) {
			try {
				$view = View::loadFromPath($path);

				Frontend::Parameters()->{'entry-id'} = $entry_id;
				Frontend::Parameters()->{'document-render'} = true;

				return $view->render(Frontend::Parameters());
			}
			catch (ViewException $ex) {
				// oh oh.
				throw $ex;
			}
		}

		public function sendEmail($entry_id, $template) {
			header('content-type: text/plain');
			$xpath = new DOMXPath(self::$document);
			$email = (array)$template;

			//	Remove junk
			unset($email['root-element']);
			unset($email['trigger']);
			unset($email['view']);
			unset($email['parameters']);
			unset($email['pathname']);

			// Replace {xpath} queries:
			foreach ($email as $key => $value) {
				$content = $email[$key];
				$replacements = array();

				// Find queries:
				preg_match_all('/\{[^\}]+\}/', $content, $matches);

				// Find replacements:
				foreach ($matches[0] as $match) {
					$results = @$xpath->query(trim($match, '{}'));

					if ($results->length) {
						$replacements[$match] = $results->item(0)->nodeValue;
					} else {
						$replacements[$match] = '';
					}
				}

				$content = str_replace(
					array_keys($replacements),
					array_values($replacements),
					$content
				);

				$email[$key] = $content;
			}

			// Add values:
			$email['message'] = (string)$this->getTemplate($template->view, $entry_id);
			$email['entry_id'] = $entry_id;

			// Determine if we are going to use the SMTP mailer, or the inbuilt Symphony mail function:
			try {
				$smtp = (Extension::status('smtp_email_library') == Extension::STATUS_ENABLED);
			}
			catch (Exception $ex) {
				$smtp = false;
			}

			// Send the email:
			if($smtp) {
				require_once EXTENSIONS . '/smtp_email_library/lib/class.email.php';

				$libEmail = new LibraryEmail;

				$libEmail->to = $email['recipient-addresses'];
				$libEmail->from = sprintf('%s <%s>', $email['sender-name'], $email['sender-addresses']);
				$libEmail->subject = $email['subject'];
				$libEmail->message = $email['message'];
				$libEmail->setHeader('Reply-To', sprintf('%s <%s>', $email['sender-name'], $email['sender-addresses']));

				try{
					$return = $libEmail->send();
				}
				catch(Exception $e){
					throw $e;
					$return = false;
				}
			}
			else {
				$return = General::sendEmail(
					$email['recipient-addresses'],  $email['sender-addresses'], $email['sender-name'], $email['subject'], $email['message'], array(
						'content-type'	=> 'text/html; charset="UTF-8"'
					)
				);
			}

			// Log the email:
			$email['method'] = ($smtp ? "smtp" : "symphony");
			$email['success'] = ($return ? 'yes' : 'no');
			$email['date'] = DateTimeObj::get('c');

			//	TODO: Logging
			return $return;
		}
	}

	return 'Extension_EmailTemplateFilter';

?>