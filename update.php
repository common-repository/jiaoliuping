<?php
include_once 'functions.php';

global $post, $since_id, $max_id, $count;
$session 	= check_access_token($_GET['access_token']);
$since_id 	= @$_GET['since_id'];
$max_id 	= @$_GET['max_id'];
$count 		= @$_GET['count'];

$since_id 	= $since_id ? $since_id : 0;
$max_id 	= $max_id ? $max_id : 0;
$count 		= $count ? $count : 20;


function filter_where($w = '') {
	global $since_id, $max_id;
	if($since_id){
		$w .= " AND ID >{$since_id}";
	}elseif($max_id){
		$w .= " AND ID < {$max_id}";
	}
	return $w;
}
add_filter('posts_where', 'filter_where');

query_posts( "posts_per_page={$count}" );

$data = array();

while ( have_posts() ) {
	the_post();
	$user = get_userdata($post->post_author);
	$data[] = array(
		'created_at'=>$post->post_date,
		'rid'			=>$post->ID,
		'message'	=>$post->post_content,
		'comments-count'=>$post->comment_count,
		'image'=>array(
				'normal'=>'',
				'big'=>''
		),
		'message_url'=>$post->guid,
		'user'=>array(
				'uid'		=>$user->ID,
				'name'	=>$user->display_name,
				'url'		=>$user->user_url,
				'avatar'	=>'http://gravatar.com/avatar/' . md5( strtolower( trim ( $user->user_email ) ) ),
				'description'=>$user->user_description
		)
	);
}

remove_filter('posts_where', 'filter_where');
wp_reset_query();

output_json_data($data);