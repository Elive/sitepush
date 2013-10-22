<?php
/*
 * SitePushMyMail class
 */

class SitePushMyMail
{
    public $subscribers = array(
	'#', 'Email', 'Firstname', 'Lastname', 'Lists', 'Status', 'IP Address', 'Signup Date', 'Signup IP',
	'Confirm Date', 'Confirm IP'
    );

    private $status = array(
	'subscribed', 'unsubscribed', 'hardbounced', 'error', 'trash'
    );

    //source and destination db data
    private $db_dest;
    private $db_soruce;

    private $limits;
    private $offset;

    private $tables = array();

    //*_term_taxonomy data form live site stored here
    private $term_taxonomy_DATA = array();
    private $term_taxonomy_TERM_ID = array();

    private $terms_DATA = array();

    private $term_relationships_DATA = array();
    private $term_relationships_OBJECT_ID = array();

    private $posts_DATA = array();
    private $posts_DATA_ID = array();

    private $postmeta_DATA = array();
    private $postmeta_META_ID = array();

    private $live_site_wpdb = NULL;

    function __construct($dest, $source)
    {
	$this->db_dest = $dest;
	$this->db_soruce = $source;

	$this->live_site_wpdb = new wpdb($this->db_dest['user'], $this->db_dest['pw'], $this->db_dest['name'], $this->db_dest['host']);


	$this->myMail_limits_get();
	$this->myMail_offset_get();
	$this->myMail_tables_get();
	$this->myMail_term_taxonomy_data_get();
	$this->myMail_terms_data_get();
	$this->myMail_term_relationships_data_get();
	$this->myMail_posts_data_get();
	$this->myMail_postmeta_data_get();
    }

    public function initialize()
    {
	//print_r($this->term_taxonomy_DATA);
	//print_r($this->term_taxonomy_TERM_ID);
	//print_r($this->terms_DATA);
	//print_r($this->term_relationships_DATA);
	//print_r($this->term_relationships_OBJECT_ID);
	//print_r($this->posts_DATA);
	//print_r($this->postmeta_DATA);

//	print_r($this->db_dest);

	global $wpdb;
	$wpdb->select($this->db_dest['name']);
	$wpdb->show_errors();

	$this->myMail_posts_data_insert();
	$this->myMail_terms_data_insert();
	$this->myMail_term_relationships_data_insert();

	$wpdb->select($this->db_soruce['name']);
    }

    private function myMail_term_relationships_data_insert()
    {
	if (!is_array($this->term_taxonomy_DATA)) return -1;
	if (!is_array($this->posts_DATA)) return -1;
	if (!is_array($this->term_relationships_DATA)) return -1;
	global $wpdb;

	$live_site_term_relationships_DATA = array();
	$table = $this->tables['term_relationships'];

	foreach ($this->term_relationships_DATA as $term_relationships)
	{
	    $object_id = $term_relationships['object_id'];
	    $term_taxonomy_id = $term_relationships['term_taxonomy_id'];

	    foreach ($this->term_taxonomy_DATA as $term_taxonomy)
	    {
		if ($term_relationships['term_taxonomy_id'] != $term_taxonomy['term_taxonomy_id']) continue;

		$term_taxonomy_id = $term_taxonomy['term_taxonomy_id'];
		if (isset($term_taxonomy['term_taxonomy_id_NEW']))
		    $term_taxonomy_id = $term_taxonomy['term_taxonomy_id_NEW'];
		break;
	    }

	    foreach ($this->posts_DATA as $posts)
	    {
		if ($term_relationships['object_id'] != $posts['ID']) continue;

		$object_id = $posts['ID'];
		if (isset($posts['ID_NEW']))
		    $object_id = $posts['ID_NEW'];
		break;
	    }

	    $term_relationships['object_id'] = $object_id;
	    $term_relationships['term_taxonomy_id'] = $term_taxonomy_id;

	    $wpdb->insert($wpdb->term_relationships, $term_relationships);
	    echo "Inserting $table.object_id:$object_id -- $table.term_taxonomy_id:$term_taxonomy_id\n";
	}
    }

    private function myMail_term_taxonomy_data_insert($old_term_id, $new_term_id)
    {
	if (!is_array($this->terms_DATA)) return -1;
	if (!is_array($this->term_taxonomy_DATA)) return -1;
	global $wpdb;

	$live_site_term_taxonomy_DATA = array();
	$table = $this->tables['term_taxonomy'];

	foreach ($this->term_taxonomy_DATA as $term_taxonomy)
	{
	    if ($term_taxonomy['term_id'] != $old_term_id) continue;

	    $old_term_taxonomy_id = $new_term_taxonomy_id = $term_taxonomy['term_taxonomy_id'];
	    $term_taxonomy['term_taxonomy_id'] = '';

	    if ($old_term_id != $new_term_id)
		$term_taxonomy['term_id'] = $new_term_id;

	    $wpdb->insert($wpdb->term_taxonomy, $term_taxonomy);
	    if ($old_term_taxonomy_id != $wpdb->insert_id)
		$term_taxonomy['term_taxonomy_id_NEW'] = $new_term_taxonomy_id = $wpdb->insert_id;

	    echo "Inserting $table.term_taxonomy_id:$new_term_taxonomy_id\n";
	    $term_taxonomy['term_taxonomy_id'] = $old_term_taxonomy_id;
	    $live_site_term_taxonomy_DATA[] = $term_taxonomy;
	}
	$this->term_taxonomy_DATA = $live_site_term_taxonomy_DATA;
    }

    private function myMail_terms_data_insert()
    {
	if (!is_array($this->terms_DATA)) return -1;
	global $wpdb;

	$live_site_terms_DATA = array();
	$table = $this->tables['terms'];

	foreach ($this->terms_DATA as $terms)
	{
	    $old_terms_id = $new_terms_id = $terms['term_id'];
	    $terms['term_id'] = '';

	    $wpdb->insert($wpdb->terms, $terms);
	    if ($old_terms_id != $wpdb->insert_id)
		$terms['term_id_NEW'] = $new_terms_id = $wpdb->insert_id;

	    echo "Inserting $table.term_id:$new_terms_id\n";
	    $this->myMail_term_taxonomy_data_insert($old_terms_id, $new_terms_id);
	    $live_site_terms_DATA[] = $terms;
	}
	$this->terms_DATA = $live_site_terms_DATA;
    }

    private function myMail_postmeta_data_insert($old_post_id, $new_post_id)
    {
	if (!is_array($this->postmeta_DATA)) return -1;
	if (!is_array($this->posts_DATA)) return -1;
	global $wpdb;

	$table = $this->tables['postmeta'];
	foreach ($this->postmeta_DATA as $postmeta)
	{
	    if ($postmeta['post_id'] != $old_post_id) continue;

	    $old_meta_id = $new_meta_id = $postmeta['meta_id'];
	    $postmeta['meta_id'] = '';

	    if ($old_post_id != $new_post_id)
		$postmeta['post_id'] = $new_post_id;

	    $wpdb->insert($wpdb->postmeta, $postmeta);

	    echo "Inserting $table.post_id: $postmeta[post_id] \n";

	    $postmeta['meta_id'] = $old_meta_id;
	    if ($old_meta_id != $wpdb->insert_id)
		$postmeta['meta_id_NEW'] = $new_meta_id = $wpdb->insert_id;
	}
    }


    private function myMail_posts_data_insert()
    {
	if (!is_array($this->posts_DATA)) return -1;
	global $wpdb;

	$live_site_posts_DATA = array();
	$table = $this->tables['posts'];

	foreach ($this->posts_DATA as $posts)
	{
	    $old_post_id = $new_post_id = $posts['ID'];
	    $posts['ID'] = '';

	    $wpdb->insert($wpdb->posts, $posts);

	    $posts['ID'] = $old_post_id;
	    if ($old_post_id != $wpdb->insert_id)
		$posts['ID_NEW'] = $new_post_id = $wpdb->insert_id;

	    echo "Inserting $table.ID:$new_post_id\n";
	    $this->myMail_postmeta_data_insert($old_post_id, $new_post_id);

	    
	    $live_site_posts_DATA[] = $posts;
	}
	$this->posts_DATA = $live_site_posts_DATA;
    }

    private function myMail_term_taxonomy_data_set()
    {	
	if (!is_array($this->term_taxonomy_DATA)) return -1;

	foreach ($this->term_taxonomy_DATA as $taxonomy)
	{
	    print_r($taxonomy);
	}
    }
    
    private function myMail_postmeta_data_get()
    {
	if (count($this->posts_DATA_ID) <= 0) return -1;
	$post_id = implode(',', $this->posts_DATA_ID);
	$table = $this->tables['postmeta'];
	$sql = "SELECT * FROM `$table` WHERE post_id IN ($post_id)";
	echo "$sql \n";
	$result = mysql_query($sql);

	if (mysql_num_rows($result) <= 0) return;

	while($row = mysql_fetch_assoc($result))
	{
	    $this->postmeta_DATA[] = $row;
	    $this->postmeta_META_ID[] = $row['meta_id'];
	}
    }


    private function myMail_posts_data_get()
    {
	if (count($this->term_relationships_OBJECT_ID) <= 0) return -1;
	$post_id = implode(',', $this->term_relationships_OBJECT_ID);
	$table = $this->tables['posts'];
	$sql = "SELECT * FROM `$table` WHERE ID IN ($post_id)";
	echo "$sql \n";
	$result = mysql_query($sql);
	if (mysql_num_rows($result) <= 0) return;

	while($row = mysql_fetch_assoc($result))
	{
	    $this->posts_DATA[] = $row;
	    $this->posts_DATA_ID[] = $row['ID'];
	}
    }


    private function myMail_term_relationships_data_get()
    {
	$term_taxonomy = implode(',', $this->term_taxonomy_TERM_ID);
	$table = $this->tables['term_relationships'];
	$sql = "SELECT * FROM `$table` WHERE term_taxonomy_id IN ($term_taxonomy)";
	echo "$sql \n";
	$result = mysql_query($sql);

	if (mysql_num_rows($result) <= 0) return;

	while($row = mysql_fetch_assoc($result))
	{
	    $this->term_relationships_DATA[] = $row;
	    $this->term_relationships_OBJECT_ID[] = $row['object_id'];
	}
    }


    private function myMail_terms_data_get()
    {
	$terms_id = implode(',', $this->term_taxonomy_TERM_ID);
	$table = $this->tables['terms'];
	$sql = "SELECT * FROM `$table` WHERE term_id IN ($terms_id)";
	echo "$sql \n";
	$result = mysql_query($sql);

	if (mysql_num_rows($result) <= 0) return;

	while($row = mysql_fetch_assoc($result))
	{
	    $this->terms_DATA[] = $row;
	}
    }


    private function myMail_term_taxonomy_data_get()
    {
	$table = $this->tables['term_taxonomy'];
	$sql = "SELECT * FROM `$table` WHERE taxonomy = 'newsletter_lists'";
	echo "$sql \n";
	$result = mysql_query($sql);

	if (mysql_num_rows($result) <= 0) return;

	while($row = mysql_fetch_assoc($result))
	{
	    $this->term_taxonomy_DATA[] = $row;
	    $this->term_taxonomy_TERM_ID[] = $row['term_id'];
	}
    }

    private function myMail_limits_get()
    {
	$this->limits = 500;

    }

    private function myMail_offset_get()
    {
	$this->offset = 0; //FIXME: calc the offsets;
    }


    private function dest_sql_connect($username, $password, $host)
    {
	$conn = mysql_connect($host, $username, $password) or
	    $this->add_result("SQL_Debug: Line:".__LINE__." :: MySQL Error:".mysql_error());
	return $conn;
    }

    private function dest_sql_connection_get()
    {
	$db_dest = $this->db_dest;

	$conn = $this->dest_sql_connect($db_dest['user'], $db_dest['pw'], $db_dest['host']);
	mysql_select_db($db_dest['name'], $conn);
	if (!$conn)
	{
	    $this->add_result("SQL_Debug: Line:".__LINE__." :: MySQL Error:".mysql_error());
	    return FALSE;
	}
	return $conn;
    }

    private function dest_sql_tables_get()
    {
	$db_dest = $this->db_dest;
	$sql = "SHOW TABLES FROM $db_dest[name]";

	$result = mysql_query($sql);

	$retval = array();
	while ($row = mysql_fetch_row($result)) {
	    $retval[] = $row[0];
	}
	return $retval;
    }

    private function myMail_tables_get()
    {
	$this->dest_sql_connection_get();
	foreach ($this->dest_sql_tables_get() as $tables)
	{
	    if (strpos($tables, '_term_taxonomy') !== FALSE)
		$this->tables['term_taxonomy'] = $tables;
	    if (strpos($tables, '_term_relationships') !== FALSE)
		$this->tables['term_relationships'] = $tables;
	    if (strpos($tables, '_terms') !== FALSE)
		$this->tables['terms'] = $tables;
	    if (strpos($tables, '_posts') !== FALSE)
		$this->tables['posts'] = $tables;
	    if (strpos($tables, '_postmeta') !== FALSE)
		$this->tables['postmeta'] = $tables;
	}
    }
}

?>
