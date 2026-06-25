<?php
namespace Lihi\ShortUrl;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Base exception for all lihi API errors. */
class Lihi_Exception extends \RuntimeException {}

/** Authorization rejected — e.g. lihi API returns 403 "email not verified". */
class Lihi_Auth_Exception extends Lihi_Exception {}

/** lihi account password verification failed. */
class Lihi_Email_Or_Password_Invalid_Exception extends Lihi_Auth_Exception {}

/** lihi user is invalid server-side. */
class Lihi_User_Invalid_Exception extends Lihi_Auth_Exception {}

/** HTTP 400 — required fields missing or invalid. */
class Lihi_Validation_Exception extends Lihi_Exception {}

/** HTTP 404 HTML — resource not found. */
class Lihi_Not_Found_Exception extends Lihi_Exception {}

/** HTTP 429 — per-host rate limit exceeded on the auth endpoint. */
class Lihi_Rate_Limit_Exception extends Lihi_Exception {}

/** HTTP 5xx HTML or other unrecoverable server error. */
class Lihi_Server_Exception extends Lihi_Exception {}

/**
 * Token is missing, expired, or has been revoked server-side.
 *
 * Detected by the fixed HTML title "網站升級中..." or the lihi JWT middleware
 * JSON messages "Token invalid ,please login again",
 * "Token expired ,please login again", and
 * "Something wrong ,please login again".
 * Extends Lihi_Server_Exception so general server-error catch blocks still apply.
 */
class Lihi_Token_Invalid_Exception extends Lihi_Server_Exception {}
