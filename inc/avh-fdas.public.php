<?php
class AVH_FDAS_Public extends AVH_FDAS_Core
{

	/**
	 * PHP5 Constructor
	 *
	 */
	function __construct ()
	{
		// Initialize the plugin
		parent::__construct();
		
		// Public actions and filters
		add_action( 'get_header', array (&$this, 'handleMainAction' ) );
	}

	/**
	 * PHP4 Constructor
	 *
	 * @return AVH_FDAS_Public
	 */
	function AVH_FDAS_Public ()
	{
		$this->__construct();
	
	}

	/**
	 * Handle the main action.
	 * Get the visitors IP and call the stopforumspam API to check if it's a known spammer
	 *
	 * @since 1.0
	 */
	function handleMainAction ()
	{
		
		$time_start = microtime( true );
		$ip = $_SERVER['REMOTE_ADDR'];
		$spaminfo = $this->handleRESTcall( $this->getRestIPLookup( $ip ) );
		$time_end = microtime( true );
		$time = $time_end - $time_start;
		
		if ( isset( $spaminfo['Error'] ) ) {
			// Let's give it one more try.
			$time_start = microtime( true );
			$ip = $_SERVER['REMOTE_ADDR'];
			$spaminfo = $this->handleRESTcall( $this->getRestIPLookup( $ip ) );
			$time_end = microtime( true );
			$time = $time_end - $time_start;
			if ( isset( $spaminfo['Error'] ) ) {
				$error = $this->getHttpError( $spaminfo['Error'] );
				$site_name = str_replace( '"', "'", get_option( 'blogname' ) );
				
				$to = get_option( 'admin_email' );
				
				$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Error detected', 'avhfdas' ), $site_name );
				
				$message = __( 'An error has been detected', 'avhfdas' ) . "\r\n";
				$message .= sprintf( __( 'Error:	%s', 'avhfdas' ), $error ) . "\r\n\r\n";
				$message .= sprintf( __( 'IP:			%s', 'avhfdas' ), $ip ) . "\r\n";
				$message .= sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] ) . "\r\n";
				$message .= sprintf( __( 'Call took:	%s', 'avhafdas' ), $time ) . "\r\n";
				
				wp_mail( $to, $subject, $message );
			}
		}
		/*$site_name = str_replace( '"', "'", get_option( 'blogname' ) );
		$to = get_option( 'admin_email' );
		$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Debug', 'avhfdas' ), $site_name );
		$message = sprintf( __( 'IP:	%s', 'avhfdas' ), $ip ) . "\r\n";
		$message .= var_export( $spaminfo, TRUE );
		wp_mail( $to, $subject, $message );*/
		
		if ( 'yes' == $spaminfo['appears'] ) {
			$this->handleSpammer( $ip, $spaminfo, $time );
		}
	}

	/**
	 * Handle a known spam IP
	 *
	 * @param string $ip - The spammers IP
	 * @param array $info - Information from stopforumspam
	 * @param string $time - Time it took the call to stopforumspam
	 * 
	 */
	function handleSpammer ( $ip, $info, $time )
	{
		
		// Update the counter
		$this->data['spam']['counter']++;
		update_option( $this->db_data, $this->data );
		
		// Email
		if ( $info['frequency'] >= $this->options['spam']['whentoemail'] ) {
			$site_name = str_replace( '"', "'", get_option( 'blogname' ) );
			
			$to = get_option( 'admin_email' );
			
			$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Spammer detected [%s]', 'avhfdas' ), $site_name, $ip );
			
			$message = __( 'Stop Forum Spam has the following statistics:', 'avhfdas' ) . "\r\n";
			$message .= sprintf( __( 'Spam IP:	%s', 'avhfdas' ), $ip ) . "\r\n";
			$message .= sprintf( __( 'Last Seen:	%s', 'avhfdas' ), $info['lastseen'] ) . "\r\n";
			$message .= sprintf( __( 'Frequency:	%s', 'avhfdas' ), $info['frequency'] ) . "\r\n\r\n";
			
			if ( $info['frequency'] >= $this->options['spam']['whentodie'] ) {
				$message .= sprintf( __( 'Treshhold (%s) reached. Connection terminated', 'avhfdas' ), $this->options['spam']['whentodie'] ) . "\r\n";
			}
			
			$message .= sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] ) . "\r\n";
			
			$message .= sprintf( __( 'Call took:	%s', 'avhafdas' ), $time ) . "\r\n";
			wp_mail( $to, $subject, $message );
		
		}
			
		// This should be the very last option.
		if ( $info['frequency'] >= $this->options['spam']['whentodie'] ) {
			if ( '1' == $this->options['spam']['diewithmessage'] ) {
				$m = sprintf( __( '<h1>Access has been blocked.</h1><p>Your IP [%s] is registered in the Stop Forum Spam database.<BR />If you feel this is incorrect please contact <a href="http://www.stopforumspam.com">Stop Forum Spam</a></p>', 'avhfdas' ), $ip );
				$m .= __('<p>Protected by: AVH First Defense Against Spam</p>','avhfdas');
				wp_die( $m );
			} else {
				die();
			}
		}
	}
}
?>