<?php
if(DB::get_alternative_database_name()) {
	Session::start();
  require_once BASE_PATH . '/vendor/autoload.php';
	
	// Register mailer
	$this->mailer = new SilverStripe\BehatExtension\Utility\TestMailer();
  Email::set_mailer($this->mailer);
  Email::send_all_emails_to(null);

  // Set mock date and time
  $mockDate = Session::get('behat.mockDate');
  if($mockDate) {
  	SS_Datetime::set_mock_now($mockDate);
  }
}