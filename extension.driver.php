<?php

	class Extension_Log_Notifications extends Extension {

		public static $active = false;

		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendInitialised',
					'callback'	=> 'frontendInitialised'
				)
			);
		}

		public function appendPreferences($context){
			$group = new XMLElement('fieldset',null,array('class'=>'settings'));
			$group->appendChild(new XMLElement('legend', 'Log Notifications'));
									
			$div = new XMLElement('div',null,array('class'=>'group'));
			$label = Widget::Label(
					__('Frequency'),
					Widget::Input("settings[log_notifications][frequency]", (string)Symphony::Configuration()->get('frequency', "log_notifications"), 'text'),
					'column'
				);
			$div->appendChild($label);
			$group->appendChild($div);
			
			$div = new XMLElement('div',null,array('class'=>'group two columns'));
			$label = Widget::Label(
					__('Subject'),
					Widget::Input("settings[log_notifications][subject]", (string)Symphony::Configuration()->get('subject', "log_notifications"), 'text'),
					'column'
				);
			$div->appendChild($label);
			
			$label = Widget::Label(
					__('Recipients'),
					Widget::Input("settings[log_notifications][recipients]", (string)Symphony::Configuration()->get('recipients', "log_notifications"), 'text'),
					'column'
				);
			$div->appendChild($label);
			$group->appendChild($div);
			
			$div = new XMLElement('div',null,array('class'=>'group two columns'));
			$label = Widget::Label(
					__('Reply to name'),
					Widget::Input("settings[log_notifications][reply_to_name]", (string)Symphony::Configuration()->get('reply_to_name', "log_notifications"), 'text'),
					'column'
				);
			$div->appendChild($label);
			
			$label = Widget::Label(
					__('Reply to email'),
					Widget::Input("settings[log_notifications][reply_to_email]", (string)Symphony::Configuration()->get('reply_to_email', "log_notifications"), 'text'),
					'column'
				);
			$div->appendChild($label);
			$group->appendChild($div);


			// Add poor man's cron checkbox
			$label = Widget::Label();
			$input = Widget::Input('settings[log_notifications][poor_mans_cron]', 'yes', 'checkbox');

			if (Symphony::Configuration()->get('poor_mans_cron', 'log_notifications') === 'yes') {
			    $input->setAttribute('checked', 'checked');
			}

			$label->setValue($input->generate() . ' ' . __('Poor man\'s cron'));
			$group->appendChild($label);

			// Append help
			$group->appendChild(new XMLElement('p', __('Use poor man\'s cron if you are not able to set up a dedicated cron job as described in the readme.'), array('class' => 'help')));

			// Add poor man's cron checkbox
			$label = Widget::Label();
			$input = Widget::Input('settings[log_notifications][include_notice]', 'yes', 'checkbox');

			if (Symphony::Configuration()->get('include_notice', 'log_notifications') === 'yes') {
			    $input->setAttribute('checked', 'checked');
			}

			$label->setValue($input->generate() . ' ' . __('Include notices'));
			$group->appendChild($label);
			
			// Append help
			$group->appendChild(new XMLElement('p', __('Check if you want to recieve emails when there are notices in the logs, do not check if you are only interested in errors.'), array('class' => 'help')));

			// Append preferences
			$context['wrapper']->appendChild($group);
		}
	

		public function frontendInitialised($context) {
			if ( Symphony::Configuration()->get('poor_mans_cron', 'log_notifications') == 'yes'){
				$this->sendLogEmail();
			}
		}

		public function sendLogEmail() {
			$this->_path = MANIFEST . '/logs/';
			$this->tmpFileLocation = TMP . '/log_notifications';

			if (is_writable($this->tmpFileLocation)) {
				$lastUpdate = file_get_contents($this->tmpFileLocation);
			} else {
				$lastUpdate = strtotime('-1 day');
			}

			$showNotice = Symphony::Configuration()->get('include_notice', 'log_notifications') == 'yes';

			$newTimestamp = time();

			//ensures that it's not run too frequently
			$frequency = Symphony::Configuration()->get('frequency', 'log_notifications');
			if (isset($frequency) && $lastUpdate > strtotime( '-' . $frequency)) 
				// requests are too frequent
				return;

			// Find log files:
			if (is_readable($this->_path) and is_dir($this->_path)) {
				$files = scandir($this->_path);

				foreach ($files as $file) if ($file == 'main' || preg_match('/^main.[0-9]{10}.gz$/', $file)) {
					$this->_files[$file] = filemtime($this->_path . $file);
				}

				asort($this->_files);
				$this->_files = array_reverse($this->_files, true);
			}

			$this->_view = 'main';

			$emailContent = "";

			if (array_key_exists($this->_view, $this->_files)) {
				$table = new XMLElement('table');

				foreach ($this->__parseLog($this->_path . $this->_view) as $item) {

					if (strtotime($item->timestamp) <= $lastUpdate) break;

					$isNotice = preg_match('%^NOTICE: .*%', $item->message);				

					if ($isNotice && !$showNotice){
						continue;
					}

					$emailContent .=  '<strong>' . $item->timestamp . '</strong> > ' . $item->message . '<br/>';
				}

			}

			if (empty($emailContent)){
				return;
				echo "no errors to report since " . date("D M Y h:m:i",$lastUpdate);
			}

			// echo $emailContent;die;
			$email = Email::create();

            $email->subject = Symphony::Configuration()->get('subject', 'log_notifications');
            $email->reply_to_name = Symphony::Configuration()->get('reply_to_name', 'log_notifications');
            $email->reply_to_email_address = Symphony::Configuration()->get('reply_to_email', 'log_notifications');
            $email->text_html = $emailContent;

            $email->recipients = explode(',', Symphony::Configuration()->get('recipients', 'log_notifications'));
            $email->send();

            //on every run update the timestamp to the one when the processing started
			$myfile = fopen($this->tmpFileLocation, "w") or die("Unable to open file!");
			fwrite($myfile, $newTimestamp );
			fclose($myfile);

		}

		protected function __parseLog($file) {
			$items = array(); $last = null;

			if (is_readable($file)) {

				if (preg_match('/.gz$/', $file)) {
					$handle = gzopen($file, "r");
					$data = gzread($handle, Symphony::Configuration()->get('maxsize', 'log'));
					gzclose($handle);
				}
				else {
					$data = file_get_contents($file);
				}

				$lines = explode(PHP_EOL, $data);

				// Skip log info:
				while (count($lines)) {
					$line = trim(array_shift($lines));

					if ($line == '--------------------------------------------') break;
				}

				// Create items:
				foreach ($lines as $line) {
					preg_match('/^(.*?) > (.*)/', trim($line), $matches);

					// New entry:
					if (count($matches) == 3) {
						$message = htmlentities($matches[2]);

						$items[] = (object)array(
							'timestamp'	=> DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($matches[1])),
							'message'	=> $message
						);
					}
				}

				// Reverse order:
				$items = array_reverse($items);
			}

			return $items;
		}

	}
