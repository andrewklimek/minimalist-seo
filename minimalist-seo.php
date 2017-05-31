<?php
/*
Plugin Name: Minimalist SEO
Plugin URI:  https://github.com/andrewklimek/minimalist-seo
Description: SEO essentials.  Notably, a <title> tag builder with "merge fields"
Version:     0.1.0
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
	
	$metadata = false;
	
	if ( is_front_page() ) {
		
		$metadata = array(
			"@context" => "http://schema.org",
		   "@type" => "WebSite",
			"name" => get_option('blogname'),
			"url" => home_url(),
			"potentialAction" => array(
			    "@type" => "SearchAction",
			    "target" => home_url() ."/?s={search_term_string}",
			    "query-input" => "required name=search_term_string"
			 )
		);
		
	} elseif ( is_singular() ) {
		global $post;
		$data = $post; // so that we don't accidentally explode the global
		$post_author = get_userdata( $data->post_author );
	
		$metadata = array(
			"@context" => "http://schema.org",
			"@type" => "BlogPosting",
			"headline" => get_the_title( $data->ID ),
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
	
		if ( has_post_thumbnail( $data->ID ) ) {
			$post_image_src = wp_get_attachment_image_src( get_post_thumbnail_id( $data->ID ), 'full' );
			if ( is_array( $post_image_src ) ) {
				$metadata['image'] = array(
					'@type' => 'ImageObject',
					'url' => $post_image_src[0],
					'width' => $post_image_src[1],
					'height' => $post_image_src[2],
				);
			}
		}
	}
	
	if ( $metadata ) {
		echo '<script type="application/ld+json">'. json_encode( $metadata ) .'</script>';
	}
}
add_action( 'wp_head', 'mnmlseo_schema' );

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
	$value = get_post_meta( $post->ID, 'mnmlseo_custom_title', true );
	if ( ! $value ) $value = '';

	echo '<label for="mnmlseo_custom_title">Custom Title: </label>';
	echo '<input type="text" id="mnmlseo_custom_title" name="mnmlseo_custom_title" class="large-text" value="' . esc_attr( $value ) . '">';
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
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	
	// Check if our nonce is set.
	if ( ! isset( $_POST['mnmlseo_meta_box_nonce'] ) ) {
		return;
	}

	// Verify that the nonce is valid.
	if ( ! wp_verify_nonce( $_POST['mnmlseo_meta_box_nonce'], 'mnmlseo_save_meta_box_data' ) ) {
		return;
	}

	// Check the user's permissions.
	if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

	} else {

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}

	/* OK, it's safe for us to save the data now. */

	// Make sure that it is set.
	if ( ! isset( $_POST['mnmlseo_custom_title'] ) ) {
		return;
	}

	// Sanitize user input... is it needed?
	// Update the meta field in the database.
	update_post_meta( $post_id, 'mnmlseo_custom_title', sanitize_text_field( $_POST['mnmlseo_custom_title'] ) );
}
add_action( 'save_post', 'mnmlseo_save_seo_meta_box_data' );

function mnmlseo_custom_title() {
	$id = get_the_ID();
	$title = get_post_meta( $id, 'mnmlseo_custom_title', true );
	
	if ( ! $title ) {
		return '';
	}

	$title = str_ireplace( '[title]', single_post_title( '', false ), $title );// why not get_the_title, or global post?
	
	if ( false !== strpos( $title, '[' ) ) {
		
		$title = str_ireplace(
						array( '[tag]', '[tags]', '[category]', '[categories]' ),
						array( '[tax:tag]', '[tax:tag]', '[tax:category]', '[tax:category]' ),
						$title
		);

		if ( false !== strpos( $title, '[tax:' ) ) {
		
			$title = preg_replace_callback(
							'/\[tax:(\w+?)\]/',
							function ( $match ) {
								return implode( ', ', array_map( function($v){ return $v->name; }, get_the_terms( $id, $match[1] ) ) );
							},
							$title
			);
		}
	}
	
	return $title;
}
add_filter( 'pre_get_document_title', 'mnmlseo_custom_title' );