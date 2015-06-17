<?php
/**
 * Process on Create, copy, import, and move events.
 */
global $ds_runtime;
if ( $ds_runtime->last_ui_event === false ) return; // Interested in events
$events = ['site_created', 'site_copied', 'site_imported', 'site_moved'];
if ( !in_array( $ds_runtime->last_ui_event->action, $events ) ) return;
$site_name = '';
if ( in_array( $ds_runtime->last_ui_event->action, ['site_copied', 'site_moved'] ) ) {
	$site_name = $ds_runtime->last_ui_event->info[1];
}else{
	$site_name = $ds_runtime->last_ui_event->info[0];
}

/**
 * Get the database credentials for the given site.
 */
$db_name = $ds_runtime->preferences->sites->{$site_name}->dbName;
$db_user = $ds_runtime->preferences->sites->{$site_name}->dbUser;
$db_pass = $ds_runtime->preferences->sites->{$site_name}->dbPass;

/**
 * Get list of all tables that are currently MyISAM
 */
$db = new PDO( "mysql:host=localhost;dbname=$db_name;charset=utf8", $db_user, $db_pass);
$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = '$db_name'
        AND ENGINE = 'MyISAM'";
$tables = [];
foreach($db->query( $sql ) as $row) {
	array_push( $tables, $row['TABLE_NAME'] );
}

/**
 * Create queries to drop fulltext indexes from affected MyISAM tables
 */
$drops = [];
foreach( $tables as $table ) {
	$sql = "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
  			WHERE table_name = '$table'	AND table_schema = '$db_name'
  			AND INDEX_TYPE = 'FULLTEXT';";
	foreach($db->query( $sql ) as $row) {
		$index = $row['INDEX_NAME'];
		array_push( $drops, "ALTER TABLE $table DROP INDEX $index;");
	}
}

/**
 * Drop the indexes
 */
foreach( $drops as $drop ) {
	$db->exec( $drop );
}

/**
 * Convert the tables to InnoDB
 */
foreach( $tables as $table ) {
	$db->exec( "ALTER TABLE $table ENGINE=InnoDB;" );
}




