<?php
function xmldb_local_guestlogin_install() {
    global $DB;
    $dbman = $DB->get_manager();
    
    $table = new xmldb_table('guestlogins');
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }
    
    return true;
}