<?php

	Class Extension_EmailTemplateFilter implements iExtension {
		public function about() {
			return (object)array(
				'name'			=> 'Email Template Filter',
				'version'		=> '2.0.0',
				'release-date'	=> '2010-08-02',
				'author'		=> (object)array(
					'name'			=> 'Rowan Lewis, Brendan Abbott',
					'website'		=> 'http://symphony-cms.com/',
					'email'			=> 'me@rowan-lewis.com'
				),
				'type'			=> array(
					'Email'
				),
			);
		}
		/*
		public function __construct() {
			Field::load(EXTENSIONS . '/field_selectbox/fields/field.select.php');
			Field::load(EXTENSIONS . '/field_textbox/fields/field.textbox.php');
		}
		*/

		public function sendEmail($entry_id, $template_id) {
			header('content-type: text/plain');

			$template = $this->getTemplate($template_id);
			$conditions = $this->getConditions($template_id);
			$data = $this->getData($template, $entry_id);
			$xpath = new DOMXPath($data);
			$email = null;

			// Find condition:
			foreach ($conditions as $condition) {
				if (empty($condition['expression'])) {
					$email = $condition; break;
				}

				$results = $xpath->query($condition['expression']);

				if ($results->length > 0) {
					/*
					foreach ($results as $node) {
						var_dump($data->saveXML($node));
					}
					*/

					$email = $condition; break;
				}
			}

			if (is_null($email)) return;

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

			// Find generator:
			$page = $this->getPage($email['page']);
			$generator = URL;

			if ($page->path) $generator .= '/' . $page->path;

			$generator .= '/' . $page->handle;
			$generator = rtrim($generator, '/');
			$params = trim($email['params'], '/');
			$email['generator'] = "{$generator}/{$params}/";

			// Add values:
			$email['message'] = (string)file_get_contents($email['generator']);
			$email['condition_id'] = $email['id'];
			$email['entry_id'] = $entry_id;

			// Remove junk:
			unset($email['id']);
			unset($email['expression']);
			unset($email['type']);
			unset($email['sortorder']);
			unset($email['page']);
			unset($email['params']);
			unset($email['generator']);

			//var_dump($data->saveXML());
			//var_dump(self::$params);
			//var_dump($email);
			//exit;

			// Send the email:
			try {
				$smtp = (Extension::status('smtp_email_library') == Extension::STATUS_ENABLED);
			}
			catch (Exception $ex) {
				$smtp = false;
			}

			if($smtp) {
				require_once EXTENSIONS . '/smtp_email_library/lib/class.email.php';

				$email = new LibraryEmail;

				$email->to = $email['recipients'];
				$email->from = sprintf('%s <%s>', $email['sender'], $email['senders']);
				$email->subject = $email['subject'];
				$email->message = $email['message'];
				$email->setHeader('Reply-To', sprintf('%s <%s>', $email['sender'], $email['senders']));

				try{
					$return = $email->send();
				}
				catch(Exception $e){
					$return = false;
				}
			}
			else {
				$return = General::sendEmail(
					$email['recipients'],  $email['senders'], $email['sender'], $email['subject'], $email['message'], array(
						'mime-version'	=> '1.0',
						'content-type'	=> 'text/html; charset="UTF-8"'
					)
				);
			}

			// Log the email:
			$email['success'] = ($return ? 'yes' : 'no');
			$email['date'] = DateTimeObj::get('c');

			Symphony::Database()->insert('tbl_etf_logs', $email);

			return $return;
		}
	}

	return 'Extension_EmailTemplateFilter';

?>