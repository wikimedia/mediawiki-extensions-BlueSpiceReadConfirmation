-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/BlueSpiceReadConfirmation/maintenance/db/sql/bs_readconfirmation.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/bs_readconfirmation (
  rc_rev_id INT UNSIGNED NOT NULL,
  rc_user_id INT UNSIGNED NOT NULL,
  rc_timestamp BINARY(14) NOT NULL,
  INDEX rc_rev_id (rc_rev_id),
  INDEX rc_user_id (rc_user_id)
) /*$wgDBTableOptions*/;
