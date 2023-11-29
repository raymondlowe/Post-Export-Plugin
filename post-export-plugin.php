<?php
/**
 * Plugin Name: Post Export Plugin
 * Description: A plugin to export posts to a text/json file
 * Version: 1.0.1
 * Author: Your Name
 */

// Create the admin menu item
function post_export_menu_item() {
    add_menu_page( 'Export Posts', 'Export Posts', 'manage_options', 'post-export', 'post_export_page' );
}
add_action( 'admin_menu', 'post_export_menu_item' );

// Create the page for the export tool
function post_export_page() {
    ?>
    <div class="wrap">
        <h1>Export Posts</h1>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <p>Select a category or choose all categories:</p>
            <select name="post_category">
                <option value="all">All Categories</option>
                <?php
                $categories = get_categories();
                foreach ( $categories as $category ) {
                    echo '<option value="' . $category->cat_ID . '">' . $category->name . '</option>';
                }
                ?>
            </select>
            <input type="hidden" name="action" value="export_posts">
            <?php wp_nonce_field( 'export_posts_nonce', 'export_posts_nonce_field' ); ?>
            <p><input type="submit" value="Export Posts"></p>
        </form>
    </div>
    <?php
}

// Handle the form submission
function post_export_handle_form() {
    check_admin_referer( 'export_posts_nonce', 'export_posts_nonce_field' );

    // Get the selected category ID
    $category_id = $_POST['post_category'];

    // Set up the post query
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    if ( $category_id !== 'all' ) {
        $args['cat'] = $category_id;
    }
    $posts = get_posts( $args );

    // Create the export file
    $export_data = array();
    foreach ( $posts as $post ) {
        // sanitize the content
        $content = apply_filters( 'the_content', $post->post_content );
        $content = str_replace( array( '<!-- wp:', '<!-- /wp:' ), array( '<!--', '-->' ), $content );

        // Convert HTML content to Markdown
        $markdown_content = convert_to_markdown( html_entity_decode( $content, ENT_QUOTES, 'UTF-8' ));
        
        $focus_keyword = get_post_meta( $post->ID, 'rank_math_focus_keyword', true );
        if ( empty( $focus_keyword ) ) {
            $focus_keyword = '';
        }
        
        $export_item = array(
            'title' => $post->post_title,
            'permalink' => get_permalink( $post->ID ),
            'author' => get_the_author_meta( 'display_name', $post->post_author ),
            'category' => get_the_category_list( ', ', '', $post->ID ),
            'tags' => get_the_tag_list( '', ', ', '', $post->ID ),
            'date' => get_the_date( '', $post->ID ),
            'excerpt' => get_the_excerpt( $post->ID ),
            'featured_image' => get_the_post_thumbnail_url( $post->ID ),
            'format' => get_post_format( $post->ID ),
            'status' => get_post_status( $post->ID ),
            'comments' => get_comments_number( $post->ID ),
            'focus_keyword' => $focus_keyword,
            'text' => $markdown_content,
        );
        $export_data[] = $export_item;
    }
    $export_file = 'posts-export-' . date( 'Y-m-d-H-i-s' ) . '.json';
    $export_file_path = WP_CONTENT_DIR . '/' . $export_file;
    file_put_contents( $export_file_path, json_encode( $export_data ) );

    // Download the export file
    header( 'Content-Type: application/json' );
    header( 'Content-Disposition: attachment; filename="' . $export_file . '"' );
    readfile( $export_file_path );
}

// Add the custom filter to convert HTML to Markdown
function convert_to_markdown($content) {
    // Remove all HTML tags except H1, H2, H3, H4, H5, H6, UL, LI
    $allowed_tags = array(
        'h1' => array(),
        'h2' => array(),
        'h3' => array(),
        'h4' => array(),
        'h5' => array(),
        'h6' => array(),
        'ul' => array(),
        'li' => array(),
    );
    $content = wp_kses( $content, $allowed_tags );

    $content = preg_replace_callback('/<h([1-6])>/i', function($matches) {
        $header_level = (int)$matches[1]; // The digit part is directly accessed from the match
        return str_repeat('#', $header_level) . ' ';
    }, $content);

    // Convert UL, LI to Markdown unordered list
    $ontent = preg_replace_callback( '/<\/?li>/i', function( $matches ) {
        $list_tag = str_replace( array( '<', '>' ), '', $matches[0] );
        if ( $list_tag === 'li' ) {
            return '- ';
        } else {
            return '';
        }
    }, $content );

    // Remove all other HTML tags and entities
    $content = wp_strip_all_tags( $content );

    return $content;
}

// Hook the form submission handler to admin_post_{action}
add_action( 'admin_post_export_posts', 'post_export_handle_form' );
