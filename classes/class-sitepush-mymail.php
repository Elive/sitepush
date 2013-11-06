<?php
/*
 * SitePushMyMail class
 */

if (isset($_GET['mymail_test']))
{
    $db_dest = array(
	'prefix' => 'wp_',
	'name' => 'elive',
	'user' => 'root',
	'host' => 'localhost',
	'pw' => 'msiamd',
	'label' => 'elive');

    $db_source = array(
	'prefix' => 'wp_',
	'name' => 'elivesandbox',
	'user' => 'root',
	'host' => 'localhost',
	'pw' => 'msiamd',
	'label' => 'elivesandbox');

    $mymail = new SitePushMyMail($db_dest, $db_source);
    $mymail->myMail_get_source_DATA();
    $mymail->myMail_get_dest_DATA();
    $mymail->initialize();
    exit(0);
}

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
    private $db_source;

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

    private $newsletter = array('source' => array(
	'term_taxonomy_DATA' => array(),
	'term_taxonomy_TERM_ID' => array(),
	'terms_DATA' => array(),
	'term_relationships_DATA' => array(),
	'term_relationships_OBJECT_ID' => array(),
	'posts_DATA' => array(),
	'posts_DATA_ID' => array(),
	'postmeta_DATA' => array(),
	'postmeta_META_ID' => array(),
    ));

    function __construct($dest, $source)
    {
	//Create array for destination data from source.
	$this->newsletter['dest'] = $this->newsletter['source'];
	//Convert into an object.
	$this->newsletter = (object) $this->newsletter;
	$this->newsletter->source = (object) $this->newsletter->source;
	$this->newsletter->dest = (object) $this->newsletter->dest;

	$this->db_dest = $dest;
	$this->db_source = $source;

	//TODO: implement limits/offsets
	$this->myMail_limits_get();
	$this->myMail_offset_get();
	$this->myMail_tables_get();
    }
    public function myMail_get_source_DATA()
    {
	echo 'Getting Source Data'."\n";
	global $wpdb;
	$wpdb = new wpdb($this->db_source['user'], $this->db_source['pw'], $this->db_source['name'], $this->db_source['host']);
	//$wpdb->select($this->db_source['name']);

	$this->myMail_term_taxonomy_data_get();
	$this->myMail_terms_data_get();
	$this->myMail_term_relationships_data_get();
	$this->myMail_posts_data_get();
	$this->myMail_postmeta_data_get();

	//Put in object.
	$this->newsletter->source->term_taxonomy_DATA = $this->term_taxonomy_DATA;
	$this->newsletter->source->term_taxonomy_TERM_ID = $this->term_taxonomy_TERM_ID;
	$this->newsletter->source->terms_DATA = $this->terms_DATA;
	$this->newsletter->source->term_relationships_DATA = $this->term_relationships_DATA;
	$this->newsletter->source->term_relationships_OBJECT_ID = $this->term_relationships_OBJECT_ID;
	$this->newsletter->source->posts_DATA = $this->posts_DATA;
	$this->newsletter->source->posts_DATA_ID = $this->posts_DATA_ID;
	$this->newsletter->source->postmeta_DATA = $this->postmeta_DATA;
	$this->newsletter->source->postmeta_META_ID = $this->postmeta_META_ID;


	//Reset private data.
	$this->term_taxonomy_DATA = array();
	$this->term_taxonomy_TERM_ID = array();
	$this->terms_DATA = array();
	$this->term_relationships_DATA = array();
	$this->term_relationships_OBJECT_ID = array();
	$this->posts_DATA = array();
	$this->posts_DATA_ID = array();
	$this->postmeta_DATA = array();
	$this->postmeta_META_ID = array();
	echo "\n";
    }

    public function myMail_get_dest_DATA()
    {
	echo 'Getting Dest Data'."\n";
	global $wpdb;

	$wpdb = new wpdb($this->db_dest['user'], $this->db_dest['pw'], $this->db_dest['name'], $this->db_dest['host']);
	//$wpdb->select($this->db_dest['name']);

	$this->myMail_term_taxonomy_data_get();
	$this->myMail_terms_data_get();
	$this->myMail_term_relationships_data_get();
	$this->myMail_posts_data_get();
	$this->myMail_postmeta_data_get();

	//Put in object.
	$this->newsletter->dest->term_taxonomy_DATA = $this->term_taxonomy_DATA;
	$this->newsletter->dest->term_taxonomy_TERM_ID = $this->term_taxonomy_TERM_ID;
	$this->newsletter->dest->terms_DATA = $this->terms_DATA;
	$this->newsletter->dest->term_relationships_DATA = $this->term_relationships_DATA;
	$this->newsletter->dest->term_relationships_OBJECT_ID = $this->term_relationships_OBJECT_ID;
	$this->newsletter->dest->posts_DATA = $this->posts_DATA;
	$this->newsletter->dest->posts_DATA_ID = $this->posts_DATA_ID;
	$this->newsletter->dest->postmeta_DATA = $this->postmeta_DATA;
	$this->newsletter->dest->postmeta_META_ID = $this->postmeta_META_ID;


	//Reset private data.
	$this->term_taxonomy_DATA = array();
	$this->term_taxonomy_TERM_ID = array();
	$this->terms_DATA = array();
	$this->term_relationships_DATA = array();
	$this->term_relationships_OBJECT_ID = array();
	$this->posts_DATA = array();
	$this->posts_DATA_ID = array();
	$this->postmeta_DATA = array();
	$this->postmeta_META_ID = array();

	$wpdb = new wpdb($this->db_source['user'], $this->db_source['pw'], $this->db_source['name'], $this->db_source['host']);
	echo "\n";
    }



    public function initialize()
    {
	//List all posts ids
	foreach ($this->newsletter->dest->posts_DATA_ID as $ID => $post_name)
	{
	    //Check if post_name is 32 char long.MD5SUM
	    if (strlen($post_name) != 32) continue;
	    //Check if post_name's title is an email [post_title]
	    if (!is_email($this->newsletter->dest->posts_DATA[$ID]->post_title)) continue;

	    //Check if source database has this post_name.
	    if ($source_id = self::myMail_posts_DATA_ID_has_POST_NAME($this->newsletter->source->posts_DATA_ID, $post_name))
	    {
		$email = $this->newsletter->dest->posts_DATA[$ID]->post_title;
		echo "Old Subscriber: $email";

		//Check if old subscriber post_DATA has changed.
		if ($this->newsletter->source->posts_DATA[$source_id] ==
		    $this->newsletter->dest->posts_DATA[$ID])
		{
		    echo " has not been modified\n";
		    //TODO: Check if lists changed.
		}
		else
		{
		    echo " has been modified";
		    //Check is the ID has changed.
		    if ($ID == $source_id)
		    {
			echo ", still using the same ID";

			//Check if lists changed.
			if ($this->newsletter->dest->term_relationships_DATA[$ID] ==
			    $this->newsletter->source->term_relationships_DATA[$ID])
			{
			    echo ", relationships are still the same";
			}
			else
			{
			    echo ", relationships has changed";
			}

			//Check if postmeta has changed.
			if ($this->newsletter->dest->postmeta_DATA[$ID] ==
			    $this->newsletter->source->postmeta_DATA[$ID])
			{
			    echo ", postmeta are still the same \n";
			}
			else
			{
			    echo ", postmeta has changed\n";
			}
		    }
		    else
		    {
			echo ", it seems the posts ID has changed, (this is not normal)\n";
		    }
		}
	    }
	    else
	    {
		$email = $this->newsletter->dest->posts_DATA[$ID]->post_title;
		echo "New Subscriber: $email\n";
	    }

	}
	print_r($this->newsletter);
    }

    private static function myMail_posts_DATA_ID_has_POST_NAME($DATA, $post_name)
    {
	if (!is_array($DATA)) return; //Data must posts_DATA_ID
	if (strlen($post_name) != 32) return; //post_name must be an MD5SUM 

	foreach ($DATA as $key => $_post_name)
	{
	    if ($_post_name == $post_name) return $key;
	}
	return FALSE;
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
	    if ($term_taxonomy['term_id'] == $old_term_id)
	    {
		$old_term_taxonomy_id = $new_term_taxonomy_id = $term_taxonomy['term_taxonomy_id'];
		$term_taxonomy['term_taxonomy_id'] = '';

		if ($old_term_id != $new_term_id)
		    $term_taxonomy['term_id'] = $new_term_id;

		$wpdb->insert($wpdb->term_taxonomy, $term_taxonomy);
		if ($old_term_taxonomy_id != $wpdb->insert_id)
		    $term_taxonomy['term_taxonomy_id_NEW'] = $new_term_taxonomy_id = $wpdb->insert_id;

		echo "Inserting $table.term_taxonomy_id:$new_term_taxonomy_id\n";
		$term_taxonomy['term_taxonomy_id'] = $old_term_taxonomy_id;
	    }
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
	    if ($postmeta['post_id'] != $old_post_id) continue; //FIXME: if we want to pass back data to postmeta_DATA change this statement

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
	$post_id = implode(',', array_keys($this->posts_DATA_ID));
	$table = $this->tables['postmeta'];
	$sql = "SELECT * FROM `$table` WHERE post_id IN ($post_id)";
	echo "$sql \n";
	$result = mysql_query($sql);

	if (mysql_num_rows($result) <= 0) return;

	while($row = mysql_fetch_assoc($result))
	{
	    if (strpos($row['meta_key'], '_edit_') !== FALSE) continue;

	    $this->postmeta_DATA[$row['post_id']][$row['meta_id']] = (object) $row;
	    $this->postmeta_META_ID[$row['post_id']][] = $row['meta_id'];
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
	    $this->posts_DATA[$row['ID']] = (object) $row;
	    $this->posts_DATA_ID[$row['ID']] = $row['post_name'];
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
	    $this->term_relationships_DATA[$row['object_id']][$row['term_taxonomy_id']] = (object) $row;
	    $this->term_relationships_OBJECT_ID[$row['object_id']] = $row['object_id'];
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
	    $this->terms_DATA[$row['term_id']] = (object) $row;
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
	    $this->term_taxonomy_DATA[$row['term_taxonomy_id']] = (object) $row;
	    $this->term_taxonomy_TERM_ID[$row['term_taxonomy_id']] = $row['term_id'];
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

