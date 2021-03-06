<?php
/**
 * This come from r75429 message
 * @author Platonides 
 */

require_once( dirname( __FILE__ ) . '/Benchmarker.php' );
class bench_wfIsWindows extends Benchmarker {

	public function __construct() {
		parent::__construct();	
	}

	public function execute() {
		$this->bench( array(
			array( 'function' => array( $this, 'wfIsWindows' ) ),
			array( 'function' => array( $this, 'wfIsWindowsCached' ) ),
		));
		print $this->getFormattedResults();
	}

	static function is_win() {
		return substr( php_uname(), 0, 7 ) == 'Windows' ;
	}

	// bench function 1
	function wfIsWindows() {
		return self::is_win();
	}

	// bench function 2
	function wfIsWindowsCached() {
		static $isWindows = null;
		if( $isWindows == null ) {
			$isWindows = self::is_win();
		}
		return $isWindows;
	}
}

$maintClass = 'bench_wfIsWindows';
require_once( RUN_MAINTENANCE_IF_MAIN );
