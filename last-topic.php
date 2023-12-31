<?php
/*
* sample.php 
* Description: example file for displaying latest posts and topics in selected forum id's
*/

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './'; 
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/bbcode.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('viewforum');

/* create_where_clauses( int[] gen_id, String type )
* This function outputs an SQL WHERE statement for use when grabbing 
* posts and topics */

function create_where_clauses($gen_id, $type)
{
    global $db, $auth;

    $size_gen_id = sizeof($gen_id);

    switch($type)
    {
        case 'forum':
        $type = 'forum_id';
        break;
        case 'topic':
        $type = 'topic_id';
        break;
        default:
        trigger_error('No type defined');
    }

    // Set $out_where to nothing, this will be used of the gen_id
    // size is empty, in other words "grab from anywhere" with
    // no restrictions
    $out_where = '';

    if( $size_gen_id > 0 )
    {
    // Get a list of all forums the user has permissions to read
        $auth_f_read = array_keys($auth->acl_getf('f_read', true));

        if( $type == 'topic_id' )
        {
            $sql     = 'SELECT topic_id FROM ' . TOPICS_TABLE . '
            WHERE ' .  $db->sql_in_set('topic_id', $gen_id) . '
            AND ' .  $db->sql_in_set('forum_id', $auth_f_read);

            $result     = $db->sql_query($sql);

            while( $row = $db->sql_fetchrow($result) )
            {
                        // Create an array with all acceptable topic ids
                $topic_id_list[] = $row['topic_id'];
            }

            unset($gen_id);

            $gen_id = $topic_id_list;
            
        }

        $j = 0;    

        for( $i = 0; $i < $size_gen_id; $i++ )
        {
            $id_check = (int) $gen_id[$i];

            // If the type is topic, all checks have been made and the query can start to be built
            if( $type == 'topic_id' )
            {
                $out_where .= ($j == 0) ? 'WHERE ' . $type . ' = ' . $id_check . ' ' : 'OR ' . $type . ' = ' . $id_check . ' ';
            }

            // If the type is forum, do the check to make sure the user has read permissions
            else if( $type == 'forum_id' && $auth->acl_get('f_read', $id_check) )
            {
                $out_where .= ($j == 0) ? 'WHERE ' . $type . ' = ' . $id_check . ' ' : 'OR ' . $type . ' = ' . $id_check . ' ';
            }    

            $j++;
        }
    }

    if( $out_where == '' && $size_gen_id > 0 )
    {
        trigger_error('A list of topics/forums has not been created');
    }

    return $out_where;
}



// -------------------------------------------------------------------




$search_limit = 5;

$forum_id = array(2, 10);
$forum_id_where = create_where_clauses($forum_id, 'forum');

$topic_id = array(20, 50);
$topic_id_where = create_where_clauses($topic_id, 'topic');

// -------------------------------------------------------------------------------

$posts_ary = array(
    'SELECT'    => 'p.*, t.*, u.username, u.user_colour',
    
    'FROM'      => array(
        POSTS_TABLE     => 'p',
    ),
    
    'LEFT_JOIN' => array(
        array(
            'FROM'  => array(USERS_TABLE => 'u'),
            'ON'    => 'u.user_id = p.poster_id'
        ),
        array(
            'FROM'  => array(TOPICS_TABLE => 't'),
            'ON'    => 'p.topic_id = t.topic_id'
        ),
    ),
    
    'WHERE'     => $db->sql_in_set('t.forum_id', array_keys($auth->acl_getf('f_read', true))) . '
    AND t.topic_status <> ' . ITEM_MOVED . '
    AND t.topic_visibility = 1',
    
    'ORDER_BY'  => 'p.post_id DESC',
);
$d=1;

$posts = $db->sql_build_query('SELECT', $posts_ary);

$posts_result = $db->sql_query_limit($posts, $search_limit);

while( $posts_row = $db->sql_fetchrow($posts_result) ):

   $topic_title       = $posts_row['topic_title'];
   $post_author       = get_username_string('full', $posts_row['poster_id'], $posts_row['username'], $posts_row['user_colour']);
   $post_date          = $user->format_date($posts_row['post_time']);
   $post_link       = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $posts_row['forum_id'] . '&amp;t=' . $posts_row['topic_id'] . '&amp;p=' . $posts_row['post_id']) . '#p' . $posts_row['post_id'];

   $post_text = nl2br($posts_row['post_text']);

   $bbcode = new bbcode(base64_encode($bbcode_bitfield));         
   $bbcode->bbcode_second_pass($post_text, $posts_row['bbcode_uid'], $posts_row['bbcode_bitfield']);

   $post_text = smiley_text($post_text);


echo "<tr>";
echo "<th scope='row'>";
echo $d++;
echo "</th>";
echo "<td  class='text-left' >";
echo "<a href='". $post_link ."'>";
echo $topic_title;
echo "</a>";
echo "</td>";
echo "<td  class='text-center'>";
echo $post_author;
echo "</td>";
echo "<td  class='text-right'>";
echo $post_date;
echo "</td>";
echo "</tr>";
echo "</br>";




endwhile;
?>
