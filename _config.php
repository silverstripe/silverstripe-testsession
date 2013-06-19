<?php
if(
  Config::inst()->get('Security', 'token')
  && DB::get_alternative_database_name()
) {
  require_once BASE_PATH . '/vendor/autoload.php';
	
	// Register mailer
  if($mailer = Session::get('testsession.mailer')) {
    Email::set_mailer(new $mailer());
    \Config::inst()->update("Email","send_all_emails_to", null);
  }
  
  // Set mock date and time
  $date = Session::get('testsession.date');
  if($date) {
  	SS_Datetime::set_mock_now($date);
  }
}