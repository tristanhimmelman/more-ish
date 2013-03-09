<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
///** The name of the database for WordPress */
//define('DB_NAME', 'database_name_here');
//
///** MySQL database username */
//define('DB_USER', 'username_here');
//
///** MySQL database password */
//define('DB_PASSWORD', 'password_here');
//
///** MySQL hostname */
//define('DB_HOST', 'localhost');
//
///** Database Charset to use in creating database tables. */
//define('DB_CHARSET', 'utf8');
//
///** The Database Collate type. Don't change this if in doubt. */
//define('DB_COLLATE', '');

if (isset($_SERVER["DATABASE_URL"])) {
 $db = parse_url($_SERVER["DATABASE_URL"]);
 define("DB_NAME", trim($db["path"],"/"));
 define("DB_USER", $db["user"]);
 define("DB_PASSWORD", $db["pass"]);
 define("DB_HOST", $db["host"]);
}
else {
 die("Your heroku DATABASE_URL does not appear to be correctly specified.");
}

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'aM`t@7{Fge/Op.gVM:+-![s |xOrm?pam|5#R5Dh<0MnN;Sz,^QD_cs*PZn+0rVD');
define('SECURE_AUTH_KEY',  '8E>/ljv[`)4B!Z-qqT*JM-!691dI!J^qT)/q>/46(BO{*Q{Ra/F.!CXfr~vUp|+x');
define('LOGGED_IN_KEY',    '+No[^<K:Jk,]*}mc!0b%g0M`B] wqdmpW)R]Us[Y6^ %YAX@@_v,v;%-t4Rj6!Zu');
define('NONCE_KEY',        'Q;-)::++d-e|egwv:A~;dJT%uF}xJyh^@{;qO~8j`&nS)fCEx)0w[JF?W%|[^AN)');
define('AUTH_SALT',        '-S--Ac+>aQX|HEk,w=84q>9z7,B~C;`F,!O.8S4jJ5GB=@[)o@}0d<_pqC)+_x=g');
define('SECURE_AUTH_SALT', '/+}OOni9eOro4|n|DQ+j86`=62lvGmw`za3VFzr0WZ{ n2#|-dkP)1~$a)n6h<^[');
define('LOGGED_IN_SALT',   '5+C-f5m6.@VqI,xW:h|kE+^.b KRw+S1x3r|@TDF-+SC;]-/6j4er+h0/CWlv(]r');
define('NONCE_SALT',       '*Rk8KH m-D-Nr5ReSlZqSm}YMxCln@~2WoCR&an-`^Q:g,23RFYmE}x+!Y{gseTv');

/**#@-*/

define('WP_SITEURL', 'http://' . $_SERVER['SERVER_NAME'] );

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', '');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
