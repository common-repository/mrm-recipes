<?php

/**
 * Plugin Name: MRM Recipes
 *
 * Description: This plugin will add receipes to MyRecipeMagic.com with one click.
 * Version: 1.4.6
 * Author: MyRecipeMagic
 * Author URI: http://myrecipemagic.com/
 * License: GPL2
 */

class MRM_Recipes_Plugin {
    
    const HOST = 'https://www.myrecipemagic.com';

    public function __construct() {
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( &$this, 'mrm_recipes_my_plugin_action_links' ) );

        add_action('admin_menu', array( &$this, 'mrm_recipes_create_menu' ));

        add_action('wp_insert_post', array( &$this, 'mrm_recipes_create_xml' ), 10, 3);

        add_filter('the_content', array( &$this, 'mrm_recipes_insert_mrm_button' ));

        add_action( 'init', array( &$this, 'mrm_recipes_init_process_post' ));

        add_action('wp_enqueue_scripts', array( &$this, 'mrm_recipes_setting_style' ));
    }

    public function mrm_recipes_my_plugin_action_links( $links ) {
       $links[] = '<a href="'. esc_url( get_admin_url(null, 'admin.php?page=mrm-recipes%2Fmrm_recipes.php') ) .'">Settings</a>';
       return $links;
    }

    public function mrm_recipes_setting_style() {
        wp_register_style( 'mrm-magic-style', plugins_url( 'mrm_recipes_1_4_6.css', __FILE__ ));
        wp_enqueue_style( 'mrm-magic-style' );
    }

    public function mrm_recipes_create_menu() {

        // create new top-level menu
        add_menu_page('MRM Settings', 'MRM Settings', 'administrator', __FILE__, array( &$this, 'mrm_recipes_plugin_settings_page' ) );
    }

    public function mrm_recipes_plugin_settings_page() {
        global $current_user;

        if ( isset($_REQUEST) && isset($_REQUEST['savedata']) && $_REQUEST['savedata'] == 'savedata' ) {
            $mrm_username = $_REQUEST['mrm_username'];
            $mrm_button_location = $_REQUEST['mrm_button_location'];
            update_user_meta( $current_user->ID, 'mrm_username', $mrm_username); 
            update_user_meta( $current_user->ID, 'mrm_button_location', $mrm_button_location);
        }

        $get_mrm_username = get_user_meta( $current_user->ID, 'mrm_username', true);
        $get_mrm_button_location = get_user_meta( $current_user->ID, 'mrm_button_location', true); 
        ?>

        <div class="wrap">
        <h2>MRM Settings</h2>

        <form method="post" action="">
            
            <table class="form-table">
                <tr valign="top">
                <th scope="row">MRM UserName</th>
                <td><input type="text" name="mrm_username" value="<?php echo $get_mrm_username; ?>" /></td>
                </tr>
                 
                <tr valign="top">
                <th scope="row">Magic Button location</th>
                <td>
                <select name="mrm_button_location">
                <option value="above_post_left" <?php if ( $get_mrm_button_location == 'above_post_left' ) { echo "selected"; } ?>>Above the Post&ndash;Left</option>
                <option value="above_post_right" <?php if ( $get_mrm_button_location == 'above_post_right' ) { echo "selected"; } ?>>Above the Post&ndash;Right</option>
                <option value="below_post_left" <?php if ( $get_mrm_button_location == 'below_post_left' ) { echo "selected"; } ?>>Below the Post&ndash;Left</option>
                <option value="below_post_right" <?php if ( $get_mrm_button_location == 'below_post_right' ) { echo "selected"; } ?>>Below the Post&ndash;Right</option>
                </select>
                <input type="hidden" value="savedata" name="savedata"/>
            </td>
                </tr>
                      
            </table>
            
            <?php submit_button(); ?>

        </form>
        </div>
        <?php 
    }

    /**
    * create a slug for the recipe.
    */
    public function mrm_recipes_get_slug( $post_name, $post_author) {
        $slug = $post_author.'-'.$post_name;
        $slug = str_replace('@', '-', $slug);
        $slug = str_replace('.', '-', $slug);
        return $slug;
    }

    /**
    * Split hours and minutes.
    */
    public function mrm_recipes_hoursToMinutes( $hours ) {
        $totalMinutes = 0;
        if ( strstr( $hours, 'hour' ) ) {

            $separatedData = explode( 'hour', $hours);

            $separatedData[1] = str_replace( 'mins', '', $separatedData[1] );
            $minutesInHours = $separatedData[0] * 60;
            $minutesInDecimals = $separatedData[1];

            $totalMinutes = $minutesInHours + $minutesInDecimals;
        }
        
        return $totalMinutes;
    }

    /**
    * Generate XML
    */
    public function mrm_recipes_create_xml( $post_id ) {
        global $wpdb;
       
        if ( wp_is_post_revision( $post_id ) )
            return;

        $status = get_post_status ( $post_id ); 
        if ( $status == "publish" ) {
            $magic_button = get_post_meta( $post_id, '_magic_button', true );
            
            if ( $magic_button == '1' ) {
                
                // jacob Meal Planner Pro Recipes
                $table = 'mpprecipe_recipes';
                if ( $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->prefix$table'" ) ) {
                    $result_meal_planner_pro = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->prefix$table WHERE post_id = %d", $post_id ) );
                }

                $content_post = get_post( $post_id );
                $post_content = $content_post->post_content;

                // call dom element
                $domelement = new DOMDocument();
                $domelement->loadHTML( $post_content );
                $xpath = new DOMXPath( $domelement );
                
                // get  ingredients data from post content
                $ingredients = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " ingredients ")]' );

                $ingredients_item = $ingredients->item( 0 );
                if ( $ingredients_item != '' ) {
                    $ingredient = $domelement->saveXML( $ingredients_item );
                    $ingredient = preg_replace( "/&#13;/", '', $ingredient );
                    $ingredient = htmlentities( strip_tags( $ingredient ) );
                    $ingredient = str_replace( '"', "&#34;", $ingredient );
                    $ingredient = str_replace( "'", "&#039;", $ingredient );
                } else {
                    $ingredient = '';
                }
                
                // jacob Meal Planner Pro Recipes 33333333333333333333333
                if (isset($result_meal_planner_pro) && $result_meal_planner_pro->ingredients ) {
                    $ingredient = $result_meal_planner_pro->ingredients;
                }
                
                // get  Instruction  data from post content
                $Instruction = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " instructions ")]' );

                $instruction_item = $Instruction->item( 0 );
                if ( $instruction_item != '' ) {
                    $instruction = $domelement->saveXML( $instruction_item );
                    $instruction = preg_replace("/&#13;/", '', $instruction );
                    $instruction = htmlentities( strip_tags( $instruction ) );
                } else {
                    $instruction = '';
                }
                
                // jacob Meal Planner Pro Recipes 2222222222222
                if ( isset($result_meal_planner_pro) && $result_meal_planner_pro->instructions ) {
                    $instruction = $result_meal_planner_pro->instructions;
                }
                
                // get summary  data from post content
                $content = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), "summary ")]');

                $content_item = $content->item(0);
                if ( $content_item != '' ) {
                    $summary =  $domelement->saveXML($content_item);
                    $summary = preg_replace("/&#13;/",'',$summary);
                    $summary = htmlentities(strip_tags($summary));
                    $summary = str_replace('"',"&#34;",$summary);
                    $summary = str_replace("'","&#039;",$summary);
                } else {
                    $summary = '';
                }
                
                // jacob Meal Planner Pro Recipes 6666666666666666
                if ( isset($result_meal_planner_pro) && $result_meal_planner_pro->summary ) {
                    $summary = $result_meal_planner_pro->summary;
                }
                
                $Prep = $xpath->query( '//*[@itemprop="prepTime"]' );
                $Total = $xpath->query( '//*[@itemprop="totalTime"]' );
                $Serve = $xpath->query( '//*[@class="yield"]' );

                // prep time  data from post content
                $prep_item = $Prep->item( 0 );
                if ( $prep_item != '' ) {
                    $prep =  $domelement->saveXML( $prep_item );
                    $prep = strip_tags( $prep );
                    $prep = str_replace( '"', "&#34;", $prep );
                    $prep = str_replace( "'", "&#039;", $prep );
                    if ( strstr($prep, 'hour') ) {
                        $prep = $this->mrm_recipes_hoursToMinutes( $prep );
                    } else {
                        $prep = str_replace( ' mins', '', $prep );
                        $prep = str_replace( ' min', '', $prep );
                    }
                } else {
                    $prep = '';
                }

                // total time  data from post content
                $total_item = $Total->item( 0 );
                if ( $total_item != '' ) {
                    $totaltime =  $domelement->saveXML( $total_item );
                    $totaltime = strip_tags( $totaltime );
                    $totaltime = str_replace( '"',"&#34;",$totaltime );
                    $totaltime = str_replace( "'","&#039;",$totaltime );
                    if ( strstr( $totaltime, 'hour' ) ) {
                        $totaltime = $this->mrm_recipes_hoursToMinutes( $totaltime );
                    } else {
                        $totaltime = str_replace( ' mins', '', $totaltime );
                        $totaltime = str_replace( ' min', '', $totaltime );
                    }
                } else {
                    $totaltime = '';
                }

                // jacob Meal Planner Pro Recipes 444444444444444444444
                if ( isset($result_meal_planner_pro) && $result_meal_planner_pro->total_time ) {
                    $totaltime = $result_meal_planner_pro->total_time;
                }
                
                // serve time data from post content
                $serve_item = $Serve->item( 0 );
                if ( $serve_item != '' ) {
                    $servetime = $domelement->saveXML( $serve_item );
                    $servetime = str_replace( "serves", '', $servetime );
                    $servetime = str_replace( "servings", '', $servetime );
                    $servetime = strip_tags( $servetime );
                    $servetime = str_replace( '"', "&#34;", $servetime );
                    $servetime = str_replace( "'", "&#039;", $servetime );
                } else {
                    $servetime = '';
                }
                
                // jacob Meal Planner Pro Recipes 5555555555555555555555
                if ( isset($result_meal_planner_pro) && $result_meal_planner_pro->serving_size ) {
                    $servetime = $result_meal_planner_pro->serving_size;
                }
                
                $urlname = preg_replace('#^http?://#', '', get_permalink($post_id));
                
                $slug =  $content_post->post_name;
                
                if ( has_post_thumbnail( $post_id) ) {
                    $image_url_full = wp_get_attachment_image_src( get_post_thumbnail_id($post_id),'full' );
                    $image_url_thumb = wp_get_attachment_image_src( get_post_thumbnail_id($post_id),'thumbnail' ); 
                    $image_url_medium = wp_get_attachment_image_src( get_post_thumbnail_id($post_id),'medium' );
                }
                $author_id = $content_post->post_author;
                $created_date = date('Y-m-d H:i:s');
                
                // jacob Meal Planner Pro Recipes 1111111111111111111111111
                /*
                if ( $result_meal_planner_pro->author ) {
                    $authorname = $result_->author;
                } else {
                */
                    $authorname = get_the_author_meta( 'mrm_username', $author_id );
                //}
                
                $current_user = wp_get_current_user();
                
                $slug = $this->mrm_recipes_get_slug( $content_post->post_name,
                                                     $authorname);
                
                // create xml
                 $xml_data = '<?xml version="1.0"?><rss version="2.0">'.
                '<item>'.
                    '<title>' . get_the_title( $post_id ) . '</title>'.
                    '<link>' . get_permalink( $post_id ) . '</link>'.
                    '<owner>' . $authorname . '</owner>'.
                    '<urlname>' . $urlname . '</urlname>'.
                    '<image>' . (isset( $image_url_full) ? $image_url_full[0] : '' ). '</image>'.
                    '<imagethumb>' . (isset( $image_url_thumb ) ? $image_url_thumb[0] : '' ) . '</imagethumb>'.
                    '<imagemedium>' . (isset( $image_url_medium ) ? $image_url_medium[0] : '' ) . '</imagemedium>'.
                    '<Instruction>' . $instruction . '</Instruction>'.
                    '<Ingredient>' . $ingredient . '</Ingredient>'.
                    /*
                    '<PrepTime>' . $prep.'</PrepTime>'.
                    '<TotalTime>' . $totaltime.'</TotalTime>'.
                    */
                    '<PrepTime>1</PrepTime>'.
                    '<TotalTime>1</TotalTime>'.
                    '<ServeTime>' . $servetime . '</ServeTime>'.
                    
                    '<slug>' . $slug . '</slug>'.
                    // post from wp should be not be display on the front end of mcm site.
                    '<cleared>0</cleared>'.
                    '<is_child>0</is_child>'.
                    '<type>0</type>'.
                    '<description>' . $summary . '</description>'.
                    '<date>' . $created_date . '</date>'.
                '</item></rss>';
                add_post_meta( $post_id, '_magic_button_slug', $slug, true ) OR update_post_meta( $post_id, '_magic_button_slug', $slug);

                $URL = self::HOST. '/recipiexml/get_data.php';
                // $URL = "http://ds09.projectstatus.co.uk/recipiexml/get_data.php";

                $response = wp_remote_post($URL,  array(
                    'method' => 'POST',
                    'timeout' => 60,
                    'redirection' => 2,
                    'httpversion' => '1.0',
                    'blocking' => false,
                    'body' => array( 'xmlRequest' => $xml_data),
                    'cookies' => array()
                ));
            }
        }
    }

    // Display the button into the post or page
    function mrm_recipes_insert_mrm_button( $content ) {
        global $post;
        $post_id = $post->ID;
        $post_title = $post->post_title;

        // check postmeta
        $magic_button = get_post_meta($post_id, '_magic_button', true);

        if ( is_single() && $magic_button == 1 ) {
            $slug = get_post_meta($post_id, '_magic_button_slug', true);
            if ( empty($slug) ) {
                $authorname = get_the_author_meta( 'mrm_username', $post->author_id );
                $url = self::HOST .  '/community/' . $authorname;
            } else {
                $url = self::HOST . '/recipe/recipedetail/' . $slug;
            }

            $content.= '<div class="mrm-magic-button__btn" >';
            $content.=  '<a href="'. $url .'" target="_blank" ><img class="mrm-magic-button__img" src="' . plugins_url( 'images/magic-button2x.png', __FILE__ ) . '" > </a>';
            $content.= '</div>';
        }
        return $content;
    }

    // Adds a box to the main column on the Post and Page edit screens:
    public function mrm_recipes_magic_button( $post_type ) {
         $current_user = wp_get_current_user();
        
        // Allowed post types to show meta box:
        // $post_types = array( 'post', 'page' ); // if post type page is included, sample only
        $post_types = array( 'post' ); 
        
        $mrm_location = get_user_meta( $current_user->ID, 'mrm_button_location', true);
        $position = '';
        $type = '';
        
        if ( $mrm_location == 'above_post_left' ) {
            $position = 'advanced';
            $type = 'high';
        } elseif ( $mrm_location == 'above_post_right' ) {
            $position = 'side';
            $type = 'high';
        } elseif ( $mrm_location == 'below_post_left' ) {
            $position = 'normal';
            $type = 'low';
        } elseif ( $mrm_location == 'below_post_right' ) {
            $position = 'side';
            $type = 'low';
        }
        
        if ( in_array( $post_type, $post_types ) ) { 
            // Add a meta box to the administrative interface:
            add_meta_box(
                'magic-button-meta-box', // HTML 'id' attribute of the edit screen section.
                'Magic Button', // Title of the edit screen section, visible to user.
                array( &$this, 'mrm_recipes_magic_button_meta_box' ), // Function that prints out the HTML for the edit screen section.
                $post_type, // The type of Write screen on which to show the edit screen section.
                $position, // The part of the page where the edit screen section should be shown.
                $type // The priority within the context where the boxes should show.
            );
        }
    }

    // Callback that prints the box content:
    public function mrm_recipes_magic_button_meta_box( $post ) {
        $current_user = wp_get_current_user();
        
        $magic_button = get_post_meta($post->ID, '_magic_button', true);

        // Form field to display:
        ?>
            <label class="screen-reader-text" for="mrm_magic_button">Magic Button</label>
            <input type="checkbox" name="mrm_magic_button" value="1" id="mrm_magic_button"  <?php if ( esc_attr($magic_button) == 1 ) { echo "checked" ;} ?>/>Add This Recipe to MRM
        <?php 

        // display a hat with the myrecipemagic link
        if ( esc_attr($magic_button) == 1 ) {
            $slug = get_post_meta($post->ID, '_magic_button_slug', true);
            if ( empty($slug) ) {
                $authorname = get_the_author_meta( 'mrm_username', $post->author_id );
                $url = self::HOST .  '/community/' . $authorname;
            } else {
                $url = self::HOST . '/recipe/recipedetail/' . $slug;
            }
            echo '<a href="' . $url . '" target="_blank"><img src="' . plugins_url( 'images/chef_icon.png', __FILE__ ) . '" > </a>';
        }

        // Display the nonce hidden form field:
        wp_nonce_field (
            plugin_basename(__FILE__), // Action name.
            'mrm_magic_button_meta_box' // Nonce name.
        );
    }
                
    // Save our custom data when the post is saved:
    public function mrm_recipes_magic_button_save_postdata ( $post_id ) {

        // Is the current user is authorised to do this action?
        $can_edit_page = isset ( $_POST['post_type'] ) && $_POST['post_type'] === 'page' && current_user_can( 'edit_page', $post_id );
        if ( $can_edit_page || current_user_can( 'edit_post', $post_id ) ) {

            // Stop WP from clearing custom fields on autosave:
            if ( ( ! defined( 'DOING_AUTOSAVE' ) || ! DOING_AUTOSAVE  ) && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {

                // Nonce verification:
                if ( isset( $_POST['mrm_magic_button_meta_box'] ) && wp_verify_nonce( $_POST['mrm_magic_button_meta_box'], plugin_basename(__FILE__) ) ) {

                    // Get the posted magic_button:
                    $magic_button = sanitize_text_field( $_POST['mrm_magic_button'] );

                    // Add, update or delete?
                    if ($magic_button == 1) {
                        // Magic Button exists, so add OR update it:
                        add_post_meta( $post_id, '_magic_button', $magic_button, true ) OR update_post_meta( $post_id, '_magic_button', $magic_button );
                    } else {
                        $magic_button = '0';
                        // Magic Button empty or removed:
                        add_post_meta( $post_id, '_magic_button', $magic_button, true ) OR update_post_meta( $post_id, '_magic_button', $magic_button );
                    }
                }
            }
        }
    }

    public function mrm_recipes_my_admin_error_notice () {
        $class = "update-nag";
        $message = "Please add MRM Username and magic button location into MRM Plugin setting";
        echo "<div class=\"$class\"> <p>$message</p></div>"; 
    }

    // Now move advanced meta boxes after the title:  for above_post_left position only
    public function mrm_recipes_move_magic_button () {

        // Get the globals:
        global $post, $wp_meta_boxes;

        // Output the "advanced" meta boxes:
        do_meta_boxes( get_current_screen(), 'advanced', $post );

        // Remove the initial "advanced" meta boxes
        unset( $wp_meta_boxes['post']['advanced'] );

    }

    public function mrm_recipes_init_process_post () {
            require (ABSPATH . WPINC . '/pluggable.php');
            $current_user = wp_get_current_user();

            $mrm_username = get_user_meta( $current_user->ID, 'mrm_username', true); // true if return value else return an array
            $mrm_location = get_user_meta( $current_user->ID, 'mrm_button_location', true);  


            if ( $mrm_username != '' && $mrm_location != '' ) {   
                
                // Define the custom box:
                add_action('add_meta_boxes', array( &$this, 'mrm_recipes_magic_button' ));
                
                // Do something with the data entered:
                add_action('save_post', array( &$this, 'mrm_recipes_magic_button_save_postdata' ));

                // Fires after the title field.
                add_action('edit_form_after_title', array( &$this, 'mrm_recipes_move_magic_button' ));
            } else {
                add_action( 'admin_notices', array( &$this, 'mrm_recipes_my_admin_error_notice' ) ); 
            }
    }
}

$mrm_recipes_plugin = new MRM_Recipes_Plugin();
