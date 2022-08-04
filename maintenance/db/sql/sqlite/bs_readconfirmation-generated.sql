-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/BlueSpiceReadConfirmation/maintenance/db/sql/bs_readconfirmation.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/bs_readconfirmation (
  rc_rev_id INTEGER UNSIGNED NOT NULL,
  rc_user_id INTEGER UNSIGNED NOT NULL,
  rc_timestamp BLOB NOT NULL
);

CREATE INDEX rc_rev_id ON /*_*/bs_readconfirmation (rc_rev_id);

CREATE INDEX rc_user_id ON /*_*/bs_readconfirmation (rc_user_id);
