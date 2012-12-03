<?php
 /*
 * Project:		EQdkp-Plus
 * License:		Creative Commons - Attribution-Noncommercial-Share Alike 3.0 Unported
 * Link:		http://creativecommons.org/licenses/by-nc-sa/3.0/
 * -----------------------------------------------------------------------
 * Began:		2007
 * Date:		$Date$
 * -----------------------------------------------------------------------
 * @author		$Author$
 * @copyright	2006-2011 EQdkp-Plus Developer Team
 * @link		http://eqdkp-plus.com
 * @package		eqdkp-plus
 * @version		$Rev$
 * 
 * $Id$
 */

if( !defined( 'EQDKP_INC' ) ) {
	die( 'Do not access this file directly.' );
}

if( !interface_exists( "plus_datacache" ) ) {
	interface plus_datacache {
		public function put( $key, $data, $ttl, $global_prefix, $compress = false );
		public function get( $key, $global_prefix, $uncompress = false );
		public function del( $key, $global_prefix );
	}//end interface
}
//end if
?>