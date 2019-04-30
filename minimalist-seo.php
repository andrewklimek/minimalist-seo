<?php
/*
Plugin Name: Minimalist SEO
Plugin URI:  https://github.com/andrewklimek/minimalist-seo
Description: SEO essentials.  Notably, a <title> tag builder with "merge fields"
Version:     0.4.0
Author:      Andrew J Klimek
Author URI:  https://andrewklimek.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Minimalist SEO is free software: you can redistribute it and/or modify 
it under the terms of the GNU General Public License as published by the Free 
Software Foundation, either version 2 of the License, or any later version.

Minimalist SEO is distributed in the hope that it will be useful, but without 
any warranty; without even the implied warranty of merchantability or fitness for a 
particular purpose. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with 
Minimalist SEO. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

function mnmlseo_schema() {
	
	global $post;
	$data = $post;// so that we don't accidentally explode the global (or just use $post = get_post(); )
		
	$metadata = $description = false;
	
	$settings = get_option( 'mnmlseo' );
	
	if ( !empty( $settings['fb_app_id'] ) )
		echo "<meta property=fb:app_id content={$settings['fb_app_id']}>";
	
	if ( !empty( $settings['twitter_site'] ) )
		echo "<meta name=twitter:site content={$settings['twitter_site']}>";

	
	if ( is_front_page() ) {
		
		echo "<meta property=og:type content=website>";
		
		$title = get_option('blogname');
		
		echo "<meta property=og:title content='{$title}'>";// needed more for twitter than FB, but since twitter meta falls back to OG, why not use OG.
		
		/* DESCRIPTION */
		if ( !empty( $settings['site_desc'] ) ) $description = $settings['site_desc'];
		elseif ( !empty($data->ID) ) $description = get_post_meta( $data->ID, 'mnmlseo_description', true );
		if ( ! $description ) $description = get_option( 'blogdescription' );
		if ( $description ) {
			echo "<meta name=description content='{$description}'>";
			echo "<meta property=og:description content='{$description}'>";
		}
		
		$url = home_url();
		echo "<meta property=og:url content='{$url}'>";

		
		if ( !empty( $settings['site_image'] ) )
			echo "<meta property=og:image content={$settings['site_image']}>";
		
		$metadata = array(
			"@context" => "http://schema.org",
			"@type" => "WebSite",
			"name" => $title,
			"url" => $url,
			"potentialAction" => array(
			    "@type" => "SearchAction",
			    "target" => home_url() ."/?s={search_term_string}",
			    "query-input" => "required name=search_term_string"
			 )
		);
		
	} elseif ( is_singular() ) {
		
		echo "<meta property=og:type content=article>";
		// echo "<meta name=twitter:card content=summary_large_image>";// summary, summary_large_image, app, or player
		
		echo "<meta property=og:url content='". get_permalink( $data->ID ) ."'>";
		
		/** Author **/
		$post_author = get_userdata( $data->post_author );
		
		
		// TODO: add author twitter field to user profiles.
		// echo "<meta name=twitter:creator content='@authors_username'>";
		
		/** DESCRIPTION **/
		
		$description = get_post_meta( $data->ID, 'mnmlseo_description', true );
		
		if ( $description )
			$description = mnmlseo_process_meta( $description, $data->ID );
		else
			$description = $data->post_excerpt;
		
		if ( $description )
		{	
			$description = esc_attr( $description );
	
			echo "<meta name=description content='{$description}'>";
			echo "<meta property=og:description content='{$description}'>";
		}
		else
		{
			/**
			 * $data->post_excerpt will have been blank if an excerpt hasn't been explicitly added.
			 * Could use get_the_excerpt() but is the first 55 words in a post even a useful meta description?
			 * Not Usually... Better off letting Google decide a contextual snippet.
			 * However, it is better than what Facebook would show for the preview snippet...
			 * So, we'll only add it for the Open Graph meta.
			 * get_the_excerpt() doesn't work without setup_postdata() since we're outside the loop.
			 **/
			setup_postdata($post);
			if ( $description = get_the_excerpt() )
				echo "<meta property=og:description content='". esc_attr( $description ) ."'>";
		}
		
		$title = get_post_meta( $data->ID, 'mnmlseo_title', true );
		if ( ! $title ) $title = get_the_title( $data->ID );
		
		echo "<meta property=og:title content='{$title}'>";// needed more for twitter than FB, but since twitter meta falls back to OG, why not use OG.
	
		$metadata = array(
			"@context" => "http://schema.org",
			"@type" => "BlogPosting",
			"headline" => $title,
			// "author" => array(
			// 	"@type" => "Person",
			// 	"name" => $post_author->display_name
			// ),
			// "datePublished" => date( 'c', strtotime( $data->post_date_gmt ) ),
			// "dateModified" => date( 'c', strtotime( $data->post_modified_gmt ) ),
			// "mainEntityOfPage" => get_permalink( $data->ID ),
			// 'publisher' => array(
			// 				'@type' => 'Organization',
			// 				'name' => get_option('blogname'),
			// ),
		);
	
		if ( has_post_thumbnail( $data->ID ) )
		{	
			$post_image_src = wp_get_attachment_image_src( get_post_thumbnail_id( $data->ID ), 'full' );			
			if ( is_array( $post_image_src ) )
			{
				$metadata['image'] = array(
					'@type' => 'ImageObject',
					'url' => $post_image_src[0],
					'width' => $post_image_src[1],
					'height' => $post_image_src[2],
				);
				echo "<meta property=og:image content={$post_image_src[0]}><meta property=og:image:width content={$post_image_src[1]}><meta property=og:image:height content={$post_image_src[2]}>";
				echo "<meta name=twitter:card content=summary_large_image>";
			}
		}
		elseif ( !empty( $settings['site_image'] ) )
		{
			$metadata['image'] = array(
				'@type' => 'ImageObject',
				'url' => $settings['site_image'],
			);
			echo "<meta property=og:image content={$settings['site_image']}>";
		}
	}
	
	if ( $metadata ) {
		echo '<script type=application/ld+json>'. json_encode( $metadata ) .'</script>';
	}
}
add_action( 'wp_head', 'mnmlseo_schema' );


function mnmlseo_custom_title() {
	
	$post = get_post();
	
	if ( ! $post ) return '';
	
	$title = get_post_meta( $post->ID, 'mnmlseo_title', true );
	
	if ( ! $title ) return '';

	$title = str_ireplace( '[title]', $post->post_title, $title );
	
	$title = mnmlseo_process_meta( $title, $id );
	
	return $title;
}
add_filter( 'pre_get_document_title', 'mnmlseo_custom_title' );

function mnmlseo_process_meta( $content, $id ) {
	
	if ( false !== strpos( $content, '[' ) ) {
		
		$content = str_ireplace(
						array( '[tag]', '[tags]', '[category]', '[categories]' ),
						array( '[tax:tag]', '[tax:tag]', '[tax:category]', '[tax:category]' ),
						$content
		);

		if ( false !== strpos( $content, '[tax:' ) ) {
		
			$content = preg_replace_callback(
							'/\[tax:(\w+?)\]/',
							function ( $match ) {
								return implode( ', ', array_map( function($v){ return $v->name; }, get_the_terms( $id, $match[1] ) ) );
							},
							$content
			);
		}
	}
	return $content;
}


/**
 * Adds a box to the main column on the Post and Page edit screens.
 */
function mnmlseo_add_meta_box( $post_type ) {
	if ( !in_array ( $post_type, array('attachment','revision','nav_menu_item') ) ){
		add_meta_box(
			'mnmlseo_schema_article',
			__( 'SEO', 'mnmlseo' ),
			'mnmlseo_seo_meta_box_callback',
			null, 'advanced', 'default',
			array('type' => $post_type)
		);
	}
}
add_action( 'add_meta_boxes', 'mnmlseo_add_meta_box' );

/**
 * Prints the box content.
 * 
 * @param WP_Post $post The object for the current post/page.
 */
function mnmlseo_seo_meta_box_callback( $post, $metabox ) {

	// Add a nonce field so we can check for it later.
	wp_nonce_field( 'mnmlseo_save_meta_box_data', 'mnmlseo_meta_box_nonce' );

	/**
	 * Use get_post_meta() to retrieve an existing value
	 * from the database and use the value for the form.
	 */
	$title = get_post_meta( $post->ID, 'mnmlseo_title', true );
	if ( ! $title ) $title = '';

	echo '';
	
	$description = get_post_meta( $post->ID, 'mnmlseo_description', true );
	if ( ! $description ) $description = '';

	echo '
		<p><span class="description">The size of these fields shows where Google would cut off the text.  You can type beyond but it probably won’t display in search results.</span></p>
		<p><label for="mnmlseo_title">Title Tag: </label></p>
		<textarea id="mnmlseo_title" name="mnmlseo_title" style="width:35.5em; max-width:100%; height: 1.9em; overflow-y: scroll;" placeholder="default is “Post Title - Site Name”">' . esc_attr( $title ) . '</textarea>
		<div class="description">You can use [title] to automatically insert the post title and [tax:your_taxonomy] for a string of terms, e.g., “[title], a post about [tax:tags]”</div>
		<p><label for="mnmlseo_description">Meta Description: </label></p>
		<textarea id="mnmlseo_description" name="mnmlseo_description" placeholder="Uses post excerpt by default. Type here to customize." style="word-break:break-all; width:40.5em; max-width:100%; height:3.3em; overflow-y: scroll;">' . esc_attr( $description ) . '</textarea>
		<div class="description">Google will sometimes use the meta description of a page in search results snippets, if they think it gives users a more accurate description than would be possible purely from the on-page content. <a href="https://support.google.com/webmasters/answer/35624#1" rel="nofollow">[read more]</a></div>
		<div id="mnmlseo-preview-title"></div><div id="mnmlseo-preview-desc"></div>';
		// <script>
		// 	var t = document.getElementById("mnmlseo-preview-title"), d = document.getElementById("mnmlseo-preview-desc"), pt = document.getElementById("title"),
		// 	mt = document.getElementById("mnmlseo_title");
		// 	t.innerHTML = pt.value;
		// 	pt.addEventListener("input", function(){ if(!mt.value) t.innerHTML=this.value});
		// 	mt.addEventListener("input", function(){t.innerHTML=this.value});
		// 	document.getElementById("mnmlseo_description").addEventListener("input", function(){d.innerHTML=this.value});
		// </script>
}

/**
 * When the post is saved, saves our custom data.
 *
 * @param int $post_id The ID of the post being saved.
 */
function mnmlseo_save_seo_meta_box_data( $post_id ) {

	/*
	 * We need to verify this came from our screen and with proper authorization,
	 * because the save_post action can be triggered at other times.
	 */
	
	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	
	// Check if our nonce is set.
	if ( ! isset( $_POST['mnmlseo_meta_box_nonce'] ) ) return;

	// Verify that the nonce is valid.
	if ( ! wp_verify_nonce( $_POST['mnmlseo_meta_box_nonce'], 'mnmlseo_save_meta_box_data' ) ) return;

	// Check the user's permissions.
	if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
		if ( ! current_user_can( 'edit_page', $post_id ) ) return;
	}
	elseif ( ! current_user_can( 'edit_post', $post_id ) ) return;

	/* OK, it's safe for us to save the data now. */

	// Make sure that it is set.
	if ( ! empty( $_POST['mnmlseo_title'] ) ) {
		// Sanitize user input... is it needed?
		// Update the meta field in the database.
		update_post_meta( $post_id, 'mnmlseo_title', sanitize_text_field( $_POST['mnmlseo_title'] ) );
	}
	if ( ! empty( $_POST['mnmlseo_description'] ) ) {
		
		update_post_meta( $post_id, 'mnmlseo_description', sanitize_text_field( $_POST['mnmlseo_description'] ) );
	}

	
}
add_action( 'save_post', 'mnmlseo_save_seo_meta_box_data' );




/****
*
* Settings Page
*
****/

add_action( 'admin_menu', 'mnmlseo_admin_menu' );
add_action( 'admin_init', 'mnmlseo_settings_ids' );
/****
*
* Register admin pages
*
****/
function mnmlseo_admin_menu() {
	add_submenu_page( 'options-general.php', 'SEO', 'SEO', 'edit_users', 'mnmlseo', 'mnmlseo_settings_page' );
	// add_submenu_page( 'tools.php', 'Test Email', 'Test Email', 'edit_users', 'mnmlseo-test', 'mnmlseo_test' );
}
/****
*
* Settings > Email
*
****/
function mnmlseo_settings_page() {
?>
<div class="wrap">
	<h2>Minimalist SEO</h2>
	<form action="options.php" method="post">
		<?php settings_fields( 'mnmlseo' ); ?>
		<?php do_settings_sections( 'mnmlseo' ); ?>
		<?php submit_button(); ?>
	</form>
</div>
<?php
}
/****
*
* Settings > SEO
*
****/
function mnmlseo_settings_ids() {

	$section = 'mnmlseo';
	$settings = get_option( $section );
	
	add_settings_section(
		$section,
		'',// heading
		$section .'_callback',
		'mnmlseo'
	);
	
	$field = 'site_image';
	add_settings_field(
		"{$section}_{$field}",
		'Default Preview Image',// label
		'mnmlseo_setting_callback_text',
		'mnmlseo',
		$section,
		array( 'label_for' => "{$section}_{$field}", 'name' => "{$section}[{$field}]", 'value' => isset($settings[$field]) ? $settings[$field] : '', 'placeholder' => "enter a full url" )
	);
	
	$field = 'site_desc';
	add_settings_field(
		"{$section}_{$field}",
		'Homepage Description',// label
		'mnmlseo_setting_callback_textarea',
		'mnmlseo',
		$section,
		array( 'label_for' => "{$section}_{$field}", 'name' => "{$section}[{$field}]", 'value' => isset($settings[$field]) ? $settings[$field] : '' )
	);
	
	$field = 'twitter_site';
	add_settings_field(
		"{$section}_{$field}",
		'Site-wide Twitter Handle',// label
		'mnmlseo_setting_callback_text',
		'mnmlseo',
		$section,
		array( 'label_for' => "{$section}_{$field}", 'name' => "{$section}[{$field}]", 'value' => isset($settings[$field]) ? $settings[$field] : '', 'placeholder' => "@username" )
	);
	
	$field = 'fb_app_id';// https://developers.facebook.com/apps/redirect/dashboard
	add_settings_field(
		"{$section}_{$field}",
		'Facebook App ID',// label
		'mnmlseo_setting_callback_text',
		'mnmlseo',
		$section,
		array( 'label_for' => "{$section}_{$field}", 'name' => "{$section}[{$field}]", 'value' => isset($settings[$field]) ? $settings[$field] : '', 'placeholder' => "123456789012345" )
	);

	
	register_setting( 'mnmlseo', $section );
}

/****
*
* Section & Field Callbacks
*
****/
function mnmlseo_callback() {
	// echo "<p>Some help text</p>";
}
function mnmlseo_setting_callback_text( $args ) {
	printf(
		'<input type="text" name="%s" id="%s" value="%s" class="regular-text" placeholder="%s">',
		$args['name'],
		$args['label_for'],
		$args['value'],
		$args['placeholder']
	);
}
function mnmlseo_setting_callback_textarea( $args ) {
	printf(
		'<textarea name="%s" id="%s" rows="10" class="large-text code">%s</textarea>',
		$args['name'],
		$args['label_for'],
		$args['value']
	);
}
