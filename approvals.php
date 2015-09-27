<?php
/*
Plugin Name: Gravity Forms Approvals
Plugin URI: http://www.stevenhenty.com
Description: Add simple approvals to Gravity Forms
Version: 1.0.0
Author: Steve Henty
Author URI: http://www.stevenhenty.com
License: GPL-2.0+
Text Domain: gravityformsapprovals
Domain Path: /languages/

------------------------------------------------------------------------
Copyright 2015 Steven Henty

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'GF_APPROVALS_VERSION', '1.0.0' );

add_action( 'gform_loaded', array( 'GF_Approvals_Bootstrap', 'load' ), 5 );

class GF_Approvals_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-approvals.php' );
		GFAddOn::register( 'GF_Approvals' );
	}

}

