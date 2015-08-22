<?php
/**
 * Plugin Name: Google Spreadsheet extractor
 * Plugin URI: https://github.com/abassouk/gss-spreadsheet-extractor
 * Description: Retrieves data from a public Google Spreadsheet and makes the contents available in posts and pages. 
 * Version: 1.0.0
 * Author: Tassos Bassoukos
 * License: GPLv3
 */
class GoogleSpreadsheetExtractor {
	private $shortcode = 'gss';
	public function __construct() {
		add_shortcode ( $this->shortcode . '_load', array (
				$this,
				'loadDocument' 
		) );
		add_shortcode ( $this->shortcode . '_repeat', array (
				$this,
				'displayRepeatLoop' 
		) );
		add_shortcode ( $this->shortcode . '_rowid', array (
				$this,
				'displayRowId' 
		) );
		add_shortcode ( $this->shortcode . '_link', array (
				$this,
				'displayLink' 
		) );
		add_shortcode ( $this->shortcode . '_cell', array (
				$this,
				'displayCell' 
		) );
		add_shortcode ( $this->shortcode . '_if', array (
				$this,
				'displayIfExists' 
		) );
		add_filter ( 'query_vars', array (
				$this,
				'add_query_vars_filter' 
		) );
	}
	public function add_query_vars_filter($vars) {
		$vars [] = "rid";
		return $vars;
	}
	private function getDocUrl($key, $gid) {
		$url = '';
		if (preg_match ( '/\/(edit|pubhtml).*$/', $key, $m ) && 'http' === substr ( $key, 0, 4 )) {
			$m = array ();
			$parts = parse_url ( $key );
			$key = $parts ['scheme'] . '://' . $parts ['host'] . $parts ['path'];
			$action = 'export?format=csv';
			$url = str_replace ( $m [1], $action, $key );
			if ($gid) {
				$url .= '&gid=' . $gid;
			}
		} else {
			$url = "https://docs.google.com/spreadsheets/d/$key/pub?output=csv";
			if ($gid) {
				$url .= "&single=true&gid=$gid";
			}
		}
		return $url;
	}
	private function fetchData($url) {
		$resp = wp_remote_get ( $url );
		if (is_wp_error ( $resp )) {
			throw new Exception ( '[Error requesting Google Spreadsheet data: ' . $resp->get_error_message () . ' when fetching ' . $url . ']' );
		}
		return $resp;
	}
	private function results() {
		global $IGSV_RESULTS;
		return $IGSV_RESULTS;
	}
	private function getTransientName($key, $gid) {
		return substr ( $this->shortcode . hash ( 'sha1', $this->shortcode . $key . $gid ), 0, 40 );
	}
	private function getTransient($transient) {
		return unserialize ( base64_decode ( get_transient ( $transient ) ) );
	}
	private function setTransient($transient, $data, $expiry) {
		return set_transient ( $transient, base64_encode ( serialize ( $data ) ), $expiry );
	}
	public function str_getcsv($str, $delimiter = ',', $enclosure = '"', $escape = '\\') {
		$return = array ();
		$realreturn = array ();
		$fields = 0;
		$inside = false;
		$quoted = false;
		$char = '';
		
		// Let's go through the string
		$len = mb_strlen ( $str );
		for($i = 0; $i < $len; $i ++) {
			$char = mb_substr ( $str, $i, 1, 'UTF-8' );
			
			if (! $inside) { // Check if we are not inside a field
				if ($char === $delimiter) { // Check if the current char is the delimiter
				                            // Tells the function that we are not inside a field anymore
					$inside = false;
					$quoted = false;
					
					// Jumps to the next field
					$fields ++;
				} elseif ($char === $escape) { // Check if the current char is the escape
				                               // Error, because it isn't inside a field and there is a escape here
					return false;
				} elseif ($char === "\n" || $char === "\r") { // check for new row
					if ($fields != 0 || strlen ( $return [$fields] ) > 0) {
						$realreturn [] = $return;
						$return = array ();
						$fields = 0;
					}
				} elseif ($char != ' ') { // Check if the current char isn't a blank space
				                          // Tells the function that a field starts
					$inside = true;
					
					// Check if the current char is the enclosure, indicating that this field is quoted
					if ($char === $enclosure) {
						$quoted = true;
					} else {
						$return [$fields] .= $char;
					}
				}
			} else { // Here we are inside a field
			         // Check if the current char is the escape
				if ($char === $escape) {
					// Check if the string has one more char beyond the current one
					if ($len > $i + 1) {
						// Tells the function we will treat the next char
						$i ++;
						$char = mb_substr ( $str, $i, 1, 'UTF-8' );
						
						// Check if our new char is the enclosure
						if ($char === $enclosure) {
							// Check if the field is a quoted one
							if ($quoted) {
								$return [$fields] .= $enclosure;
							} else {
								// Error, because we have an escape and then we have an enclosure and we are not inside a quoted field
								return false;
							}
						} elseif ($char === $escape) {
							$return [$fields] .= $char;
						} else {
							eval ( "\$return[\$fields] .= \"\\" . $char . "\";" );
						}
					} else {
						// Error, because there is an escape and nothing more then
						return false;
					}
				} elseif ($char === $enclosure) { // Check if the current char is the enclosure
				                                  // Check if we are in a quoted field
					if ($quoted) {
						// Tells the function that we are not inside a field anymore
						$inside = false;
						$quoted = false;
					} else {
						// Error, because there is an enclosure inside a non quoted field
						return false;
					}
				} elseif (($char === "\n" || $char === "\r") && ! $quoted) { // check for new row
					if ($fields != 0 || strlen ( $return [$fields] ) > 0) {
						$realreturn [] = $return;
						$return = array ();
						$fields = 0;
					}
				} elseif ($char === $delimiter) { // Check if it is the delimiter
				                                  // Check if we are inside a quoted field
					if ($quoted) {
						$return [$fields] .= $char;
					} else {
						// Tells the function that we are not inside a field anymore
						$inside = false;
						$quoted = false;
						
						// Jumps to the next field
						$return [++ $fields] = '';
					}
				} else {
					$return [$fields] .= $char;
				}
			}
		}
		if (count ( $return )) {
			$realreturn [] = $return;
		}
		return $realreturn;
	}
	
	/**
	 * WordPress Shortcode handlers.
	 */
	public function loadDocument($atts, $content = null) {
		global $IGSV_RESULTS;
		$x = shortcode_atts ( array (
				'key' => false, // Google Doc URL or ID
				'gid' => false, // Sheet ID for a Google Spreadsheet, if only one
				'strip' => 1, // Number of rows to omit from top
				'sortcolumn' => false, // If defined, sort first by this column.
				'expires_in' => 3600,
				'stale_in' => 60,
				'use_cache' => true,
				'collate' => 'en_US' 
		), $atts, $this->shortcode . '_load' );
		$gid = ($x ['gid']) ? $x ['gid'] : 0;
		$transient = $this->getTransientName ( $x ['key'], $x ['gid'] );
		
		$lastFetch = $this->getTransient ( $transient . "s" );
		if ($lastFetch && $lastFetch < time ()) {
			if ($IGSV_RESULTS = $this->getTransient ( $transient )) {
				return "";
			}
		}
		
		$resp = $this->fetchData ( $this->getDocUrl ( $x ['key'], $x ['gid'] ) );
		if (false === $x ['use_cache'] || 'no' === strtolower ( $x ['use_cache'] )) {
			delete_transient ( $transient );
			delete_transient ( $transient . "h" );
			delete_transient ( $transient . "s" );
		} else {
			if (false !== ($IGSV_RESULTS = $this->getTransient ( $transient ))) {
				if (hash ( 'sha1', $resp ['body'] ) == ($thash = $this->getTransient ( $transient . "h" ))) {
					$this->setTransient ( $transient . "s", time () + $x ['stale_in'], ( int ) $x ['expire_in'] );
					return "";
				}
			}
		}
		$type = explode ( ';', $resp ['headers'] ['content-type'] );
		switch ($type [0]) {
			case 'text/html' :
				$r = $this->parseHtml ( $resp ['body'], $gid );
				break;
			case 'text/csv' :
			default :
				$r = $this->str_getcsv ( $resp ['body'] );
				break;
		}
		if ($x ['strip'] > 0) {
			$r = array_slice ( $r, $x ['strip'] ); // discard
		}
		if ($x ['sortcolumn']) {
			$r = $this->sortArray ( $r, $x ['sortcolumn'], $x ['collate'] );
		}
		$this->setTransient ( $transient, $r, ( int ) $x ['expire_in'] );
		$this->setTransient ( $transient . "s", time () + $x ['stale_in'], ( int ) $x ['expire_in'] );
		$this->setTransient ( $transient . "h", hash ( 'sha1', $resp ['body'] ), ( int ) $x ['expire_in'] );
		$IGSV_RESULTS = $r;
		return "";
	}
	public function displayRepeatLoop($atts, $content = null) {
		$x = shortcode_atts ( array (
				'sortcolumn' => 0, // If defined, sort first by this column.
				'validcolumn' => 0, // If defined, this column will be tested for data, if false, row will be skipped.; validcondition is the value to test for
				'validcondition' => false,
				'rowid' => '__ROWID__',
				'collate' => 'en_US' 
		), $atts, $this->shortcode . '_repeat' );
		$col = $x ['validcolumn'] - 1;
		$cond = $x ['validcondition'];
		$sc = $x ['sortcolumn'] - 1;
		$html = '';
		$results = $this->results ();
		global $IGSV_ROW;
		foreach ( $this->sortArray ( $results, $sc, $x ['collate'] ) as $r ) {
			$IGSV_ROW = $r;
			$row = $results [$r];
			if ($col >= 0) {
				$v = $row [$col];
				if ($cond == false ? ! $v : $v != $cond) {
					continue;
				}
			}
			$txt = do_shortcode ( $content );
			$html .= str_replace ( $x ['rowid'], $r, $txt );
		}
		$html = preg_replace ( '/<\/?p>/', '', $html );
		return $html;
	}
	public function displayRowId($atts) {
		$x = shortcode_atts ( array (
				'row' => 0,
				'column' => 0 
		), $atts, $this->shortcode . '_rowid' );
		$rid = $this->findRow ( $x );
		return $rid;
	}
	private function sortArray($results, $sortColumn, $collator = "en_US") {
		$rv = array ();
		$i = 0;
		foreach ( $results as $row ) {
			$rv [] = $i ++;
		}
		if ($sortColumn >= 0) {
			$c = new Collator ( $collator );
			$func = function ($a, $b) use($c, $results, $sortColumn) {
				return $c->compare ( $results [$a] [$sortColumn], $results [$b] [$sortColumn] );
			};
			usort ( $rv, $func );
		}
		return $rv;
	}
	private function findRow($x) {
		$rid = $x ['row'] - 1;
		if ($rid < 0) {
			$rid = get_query_var ( 'rid' );
		}
		if ($rid < 0 || $rid === '') {
			global $IGSV_ROW;
			$rid = $IGSV_ROW;
		}
		
		return $rid;
	}
	public function displayCell($atts) {
		$x = shortcode_atts ( array (
				'row' => 0,
				'column' => 0 
		), $atts, $this->shortcode . '_cell' );
		$rid = $this->findRow ( $x );
		$cid = $x ['column'] - 1;
		if ($cid < 0) {
			return $rid;
		}
		$results = $this->results ();
		return $results [$rid] [$cid];
	}
	public function displayLink($atts, $content = null) {
		$x = shortcode_atts ( array (
				'row' => 0,
				'column' => 0,
				'tag' => 'a',
				'attr' => 'href' 
		), $atts, $this->shortcode . '_link' );
		$rid = $this->findRow ( $x );
		$cid = $x ['column'] - 1;
		$results = $this->results ();
		return "<" . $x ['tag'] . " " . $x ['attr'] . "=\"" . htmlspecialchars ( $results [$rid] [$cid] ) . "\">" . do_shortcode ( $content ) . "</" . $x ['tag'] . ">";
	}
	public function displayIfExists($atts, $content = null) {
		$x = shortcode_atts ( array (
				'column' => 0,
				'row' => 0,
				'columns' => '0' 
		), $atts, $this->shortcode . '_if' );
		$rid = $this->findRow ( $x );
		$results = $this->results ();
		$col = $x ['column'];
		$cols = explode ( ',', $x ['columns'] . "," . $col );
		$row = $results [$rid];
		foreach ( $cols as $col ) {
			if ($col == 0) {
				continue;
			}
			$v = $row [$col - 1];
			if (strlen ( $v ) == 0) {
				return "";
			}
		}
		$html .= do_shortcode ( $content );
		$html = preg_replace ( '/<\/?p>/', '', $html );
		return $html;
	}
}

$google_spreasheet_extractor = new GoogleSpreadsheetExtractor ();
