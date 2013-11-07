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
    $mymail->myMail_get_source();
    $mymail->myMail_get_dest();
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
    private $backup_path;

    private $limits;
    private $offset;

    //*_term_taxonomy data form live site stored here
    private $term_taxonomy = array();

    private $terms = array();

    private $term_relationships = array();

    private $posts = array();

    private $postmeta = array();

    private $live_site_wpdb = NULL;

    private $newsletter = array('source' => array(
	'term_taxonomy' => array(),
	'terms' => array(),
	'term_relationships' => array(),
	'posts' => array(),
	'postmeta' => array(),
    ));

    public $debug_on = FALSE;

    function __construct($dest, $source, $backup_path)
    {
	//Create array for destination data from source.
	$this->newsletter['dest'] = $this->newsletter['source'];
	//Convert into an object.
	$this->newsletter = (object) $this->newsletter;
	$this->newsletter->source = (object) $this->newsletter->source;
	$this->newsletter->dest = (object) $this->newsletter->dest;

	$this->db_dest = $dest;
	$this->db_source = $source;
	$this->backup_path = $backup_path;

	//TODO: implement limits/offsets
	$this->myMail_limits_get();
	$this->myMail_offset_get();
    }
    public function myMail_get_source()
    {
	echo 'Getting Source Data'."\n";
	global $wpdb;

	$this->myMail_term_taxonomy_get($wpdb);
	$this->myMail_terms_get($wpdb);
	$this->myMail_term_relationships_get($wpdb);
	$this->myMail_posts_get($wpdb);
	$this->myMail_postmeta_get($wpdb);

	//Put in object.
	$this->newsletter->source->term_taxonomy = $this->term_taxonomy;
	$this->newsletter->source->terms = $this->terms;
	$this->newsletter->source->term_relationships = $this->term_relationships;
	$this->newsletter->source->posts = $this->posts;
	$this->newsletter->source->postmeta = $this->postmeta;


	//Reset private data.
	$this->term_taxonomy = array();
	$this->terms = array();
	$this->term_relationships = array();
	$this->posts = array();
	$this->postmeta = array();

	//print_r($this->newsletter->source);
    }

    public function myMail_get_dest()
    {
	echo 'Getting Dest Data'."\n";

	//Connect to Dest database
	$wpdb_dest = new wpdb($this->db_dest['user'], $this->db_dest['pw'], $this->db_dest['name'], $this->db_dest['host']);

	//Get Data from database
	$this->myMail_term_taxonomy_get($wpdb_dest);
	$this->myMail_terms_get($wpdb_dest);
	$this->myMail_term_relationships_get($wpdb_dest);
	$this->myMail_posts_get($wpdb_dest);
	$this->myMail_postmeta_get($wpdb_dest);

	//Put in object.
	$this->newsletter->dest->term_taxonomy = $this->term_taxonomy;
	$this->newsletter->dest->terms = $this->terms;
	$this->newsletter->dest->term_relationships = $this->term_relationships;
	$this->newsletter->dest->posts = $this->posts;
	$this->newsletter->dest->postmeta = $this->postmeta;


	//Reset private data.
	$this->term_taxonomy = array();
	$this->terms = array();
	$this->term_relationships = array();
	$this->posts = array();
	$this->postmeta = array();

	//print_r($this->newsletter->dest);
    }



    public function import_subscribers_to_source()
    {
	global $wpdb;

	//List all posts ids
	$csv_header = array('email', 'firstname', 'lastname', 'ip', 'signupip',
	'signuptime', 'lang', 'lists', 'REMOVE THIS LINE BEFORE IMPORT');

	$new_imports = array($csv_header);

	foreach ($this->newsletter->dest->posts as $ID => $post)
	{
	    //Check if post_name is 32 char long.MD5SUM
	    if (strlen($post->post_name) != 32) continue;
	    //Check if post_name's title is an email [post_title]
	    if (!is_email($this->newsletter->dest->posts[$ID]->post_title)) continue;

	    $userdata = self::myMail_userdata_prepare(
		$this->newsletter->dest->posts[$ID],
		$this->newsletter->dest->postmeta[$ID]);
	    $userdata = array_merge($userdata, $userdata['_meta']);
	    unset($userdata['_meta']);

	    $lists = self::myMail_lists_prepare(
		$this->newsletter->dest->term_relationships[$ID],
		$this->newsletter->dest->term_taxonomy,
		$this->newsletter->dest->terms);
	    $userdata['lists'] = implode(',', $lists);

	    $csv_data = $userdata;
	    unset($userdata);
	    unset($lists);

	    $email = $this->newsletter->dest->posts[$ID]->post_title;
	    //Check if source database has this post_name.
	    if ($source_id = self::myMail_posts_has_POST_NAME($this->newsletter->source->posts, $post->post_name) === FALSE)
	    {
		echo "New Subscribers $email\n";
		$new_imports[] = $csv_data;
		$this->myMail_Insert_Subscriber($ID);
	    }
	    /*
	    else
	    {
		echo "Old Subscriber: $email";

		//Check if old subscriber post has changed.
		if ($this->newsletter->source->posts[$source_id] ==
		    $this->newsletter->dest->posts[$ID])
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
			if ($this->newsletter->dest->term_relationships[$ID] ==
			    $this->newsletter->source->term_relationships[$ID])
			{
			    echo ", relationships are still the same";
			}
			else
			{
			    echo ", relationships has changed";
			}

			//Check if postmeta has changed.
			if ($this->newsletter->dest->postmeta[$ID] ==
			    $this->newsletter->source->postmeta[$ID])
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
	    }*/
	}
	$timestamp = date('Ymd-His');

	$filename = "myMail-{$this->db_dest['name']}-{$timestamp}.csv";
	$this->myMail_Export_Subscriber($new_imports, $filename);
    }

    private function myMail_Export_Subscriber($data, $filename)
    {
	$fp = fopen($this->backup_path."/{$filename}", 'w');

	foreach ($data as $fields)
	{
	    fputcsv($fp, $fields, ';');
	}
	fclose($fp);
    }

    private function myMail_Insert_Subscriber($ID)
    {
	global $mymail_subscriber;
	//TODO: verify that this global variable exists!!

	$email = $this->newsletter->dest->posts[$ID]->post_title;
	$status = $this->newsletter->dest->posts[$ID]->post_status;

	$userdata = self::myMail_userdata_prepare(
	    $this->newsletter->dest->posts[$ID],
	    $this->newsletter->dest->postmeta[$ID]);

	$lists = self::myMail_lists_prepare(
	    $this->newsletter->dest->term_relationships[$ID],
	    $this->newsletter->dest->term_taxonomy,
	    $this->newsletter->dest->terms);

	$mymail_subscriber->insert($email, $status, $userdata, $lists, NULL, true);
    }


    private static function myMail_lists_prepare($term_relationships, $term_taxonomy, $terms)
    {
	$lists = array();

	foreach ($term_relationships as $term_taxonomy_id => $term_relationships_info)
	{
	    $lists[] = $terms[$term_taxonomy[$term_taxonomy_id]->term_id]->slug;
	}
	return $lists;
    }

    private static function myMail_userdata_prepare($posts, $postmeta)
    {
	$userdata = array(
	    'email' => '',
	    'firstname' => '',
	    'lastname' => '',
	    '_meta' => 
	    array(
		'ip' => '',
		'signupip' => '',
		'signuptime' => '',
		'lang' => '')
	    );

	$userdata = (object) $userdata;
	$userdata->_meta = (object) $userdata->_meta;

	$userdata->email = $posts->post_title;

	foreach ($postmeta as $_meta)
	{
	    if ($_meta->meta_key != 'mymail-userdata') continue;

	    $data = unserialize($_meta->meta_value);
	    $userdata->firstname = html_entity_decode($data['firstname'], ENT_QUOTES);
	    $userdata->lastname = $data['lastname'];
	    $userdata->_meta->ip = $data['ip'];
	    $userdata->_meta->signupip = $data['signupip'];
	    $userdata->_meta->signuptime = $data['signuptime'];
	    $userdata->_meta->lang = $data['lang'];
	    break;
	}

	$userdata->_meta = (array) $userdata->_meta;
	$userdata = (array) $userdata;
	return $userdata;
    }

    private static function myMail_wpdb_id_exists($my_wpdb, $table, $column, $id)
    {
	$result = $my_wpdb->get_results("SELECT * FROM $table WHERE $column = '$id'");
	if (count($result) == 0) return FALSE;
	if ($result) return $result;
	return FALSE;
    }

    private static function myMail_term_relationship_exists($my_wpdb, $object_id, $term_taxonomy_id)
    {

	$result = $my_wpdb->get_results("SELECT * FROM wp_term_relationships WHERE object_id = $object_id AND term_taxonomy_id = $term_taxonomy_id");
	if (count($result) == 0) return FALSE;
	if ($result) return $result;
	return FALSE;
    }

    private static function myMail_posts_has_POST_NAME($DATA, $post_name)
    {
	if (!is_array($DATA)) return; //Data must posts
	if (strlen($post_name) != 32) return; //post_name must be an MD5SUM 

	foreach ($DATA as $key => $post)
	{
	    if ($post->post_name == $post_name) return $key;
	}
	return FALSE;
    }

    private function myMail_term_relationships_insert($my_wpdb, &$posts, &$term_relationships_array)
    {
	foreach ($term_relationships_array as $term_relationships)
	{
	    $term_relationships->object_id = isset($posts->ID_NEW) ? $posts->ID_NEW : $posts->ID;
	    $match = FALSE;
	    foreach ($this->newsletter->source->term_relationships[$term_relationships->object_id] as $s_term_relationships)
	    {
		if ($term_relationships == $s_term_relationships)
		{
		    $match = TRUE;
		    break;
		}
	    }
	    if ($match == TRUE) continue;
	    if (self::myMail_term_relationship_exists($my_wpdb,
		$term_relationships->object_id,
		$term_relationships->term_taxonomy_id) !== FALSE)
		continue;

	    $my_wpdb->insert($my_wpdb->term_relationships, (array) $term_relationships);
	    echo "Inserting $my_wpdb->term_relationships.object_id:$term_relationships->object_id 
		-- $my_wpdb->term_relationships.term_taxonomy_id:$term_relationships->term_taxonomy_id\n";
	}
    }

    //FIXME Fix all _insert functions
    private function myMail_term_taxonomy_insert($old_term_id, $new_term_id)
    {
	if (!is_array($this->terms)) return -1;
	if (!is_array($this->term_taxonomy)) return -1;
	global $wpdb;

	$live_site_term_taxonomy = array();
	$table = $this->tables->term_taxonomy;

	foreach ($this->term_taxonomy as $term_taxonomy)
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
	    $live_site_term_taxonomy[] = $term_taxonomy;
	}
	$this->term_taxonomy = $live_site_term_taxonomy;
    }

    private function myMail_terms_insert()
    {
	if (!is_array($this->terms)) return -1;
	global $wpdb;

	$live_site_terms = array();
	$table = $this->tables->terms;

	foreach ($this->terms as $terms)
	{
	    $old_terms_id = $new_terms_id = $terms['term_id'];
	    $terms['term_id'] = '';

	    $wpdb->insert($wpdb->terms, $terms);
	    if ($old_terms_id != $wpdb->insert_id)
		$terms['term_id_NEW'] = $new_terms_id = $wpdb->insert_id;

	    echo "Inserting $table.term_id:$new_terms_id\n";
	    $this->myMail_term_taxonomy_insert($old_terms_id, $new_terms_id);
	    $live_site_terms[] = $terms;
	}
	$this->terms = $live_site_terms;
    }

    private function myMail_postmeta_insert($my_wpdb, &$posts, &$postmeta_array)
    {
	$old_meta_id = $new_meta_id = $postmeta->meta_id;

	foreach ($postmeta_array as $postmeta)
	{
	    if (self::myMail_wpdb_id_exists($my_wpdb, $my_wpdb->postmeta, 'meta_id', $postmeta->meta_id) !== FALSE)
	    {
		$postmeta->meta_id = '';
	    }

	    if ((isset($posts->ID_NEW)) &&
		($posts->ID != $posts->ID_NEW))
	    {
		$postmeta->post_id = $posts->ID_NEW;
	    }

	    $my_wpdb->insert($my_wpdb->postmeta, (array) $postmeta);
	    $postmeta->meta_id = $old_meta_id;

	    if ($postmeta->meta_id != $my_wpdb->insert_id)
		$postmeta->meta_id_NEW = $new_meta_id = $my_wpdb->insert_id;

	    echo "Inserting $my_wpdb->postmeta.meta_id: $new_meta_id \n";
	}
    }


    private function myMail_posts_insert($my_wpdb, &$posts)
    {
	$old_post_id = $new_post_id = $posts->ID;
	if (self::myMail_wpdb_id_exists($my_wpdb, $my_wpdb->posts, 'ID', $posts->ID) !== FALSE)
	{
	    $posts->ID = '';
	}

	$my_wpdb->insert($my_wpdb->posts, (array) $posts);
	$posts->ID = $old_post_id;

	if ($posts->ID != $my_wpdb->insert_id)
	    $posts->ID_NEW = $new_post_id = $my_wpdb->insert_id;

	echo "Inserting $my_wpdb->posts.ID:$new_post_id\n";
	$this->myMail_postmeta_insert($my_wpdb, $posts->ID);
    }

    private function myMail_term_taxonomy_set()
    {	
	if (!is_array($this->term_taxonomy)) return -1;

	foreach ($this->term_taxonomy as $taxonomy)
	{
	    print_r($taxonomy);
	}
    }

    private function myMail_postmeta_get($my_wpdb)
    {
	global $wpdb;
	if (count($this->posts) <= 0) return -1;
	$post_id = implode(',', array_keys($this->posts));

	$result = $my_wpdb->get_results("SELECT * FROM `$wpdb->postmeta` WHERE post_id IN ($post_id)");
	echo $my_wpdb->last_query."\n";
	if (!is_array($result)) return;

	foreach ($result as $data)
	{
	    if (strpos($data->meta_key, '_edit_') !== FALSE) continue;

	    $this->postmeta[$data->post_id][$data->meta_id] = $data;
	}
    }

    private function myMail_posts_get($my_wpdb)
    {
	global $wpdb;
	$post_id = implode(',', array_keys($this->term_relationships));

	$result = $my_wpdb->get_results("SELECT * FROM `$wpdb->posts` WHERE ID IN ($post_id)");
	echo $my_wpdb->last_query."\n";
	if (!is_array($result)) return;

	foreach ($result as $data)
	{
	    $this->posts[$data->ID] = $data;
	}
    }

    private function myMail_term_relationships_get($my_wpdb)
    {
	global $wpdb;
	$term_taxonomy = implode(',', array_keys($this->term_taxonomy));

	$result = $my_wpdb->get_results("SELECT * FROM `$wpdb->term_relationships` WHERE term_taxonomy_id IN ($term_taxonomy)");
	echo $my_wpdb->last_query."\n";
	if (!is_array($result)) return;

	foreach ($result as $data)
	{
	    $this->term_relationships[$data->object_id][$data->term_taxonomy_id] = $data;
	}
    }

    private function myMail_terms_get($my_wpdb)
    {
	global $wpdb;
	$terms_id = implode(',', array_keys($this->term_taxonomy));

	$result = $my_wpdb->get_results("SELECT * FROM `$wpdb->terms` WHERE term_id IN ($terms_id)");
	echo $my_wpdb->last_query."\n";
	if (!is_array($result)) return;

	foreach ($result as $data)
	{
	    $this->terms[$data->term_id] = $data;
	}
    }

    private function myMail_term_taxonomy_get($my_wpdb)
    {
	global $wpdb;
	$result = $my_wpdb->get_results("SELECT * FROM `$wpdb->term_taxonomy` WHERE taxonomy = 'newsletter_lists'");
	echo $my_wpdb->last_query."\n";
	if (!is_array($result)) return;

	foreach ($result as $data)
	{
	    $this->term_taxonomy[$data->term_taxonomy_id] = $data;
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
}

?>

