<?php
/*
 * Plugin Name: ANUD REST API plugin
 */

class ANUD_REST_Controller extends WP_REST_Controller {

    public function register_routes() {
        $namespace = 'anud/v1';

        $path = 'route';
        register_rest_route( $namespace, '/' . $path, [
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'route_handler' ),
            ),
        ]);     

        $path = 'route_raw';
        register_rest_route( $namespace, '/' . $path, [
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'route_raw_handler' ),
            ),
        ]);     

        //$path = 'route_raw';
        register_rest_route( $namespace, '/menu/(?P<menu_name>.+)', [
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'menu_handler' ),
            ),
        ]);     

    }


/*
 * Handlers
 */

    public function route_handler($request) {
//        $parameters = $request->get_query_params();
//        $path = $parameters["path"];
        $path = $request->get_query_params()["path"];

        $query = $this->query_data_by_path($path);
    
        $result = $this->process_result($query);
        $result['path_meta'] = $this->get_path_meta($path);

        $result['raw'] = $query;
    
        return $result;
    }
    
    public function route_raw_handler($request) {
//        $parameters = $request->get_query_params();
        $path = $request->get_query_params()["path"];
        return $this->query_data_by_path($path);
    }

    public function menu_handler($request) {
        return wp_get_nav_menu_items($request['menu_name']);
    }

/*
* Protected methods
*/

    protected function query_data_by_path($path) {
        global $wp;
        global $wp_query;

        $_SERVER['REQUEST_URI'] = $path;
        $wp->parse_request();
        $wp->query_posts();

        return $wp_query;
    }

    protected function process_result($data) {
        $result = array();
        if( $data ) {
            $result['is_singular'] = !!$data->is_singular;
            if( $data->is_singular ) {
                $result['post'] = $this->process_post($data->post);
                //return "singular";
            } else {
                if( $data->posts ) {
                    $result['posts'] = array();
                    foreach($data->posts as $p) {
                        $result['posts'][] = $this->process_post($p);
                    }
                }
                //return "plural";
            }
        }
    	return $result;
    }
    protected function process_post($post) {
        $result = array();
        if ( ! empty( $post ) ) {
        	$result['ID'] = $post->ID;
        	$result['permalink'] = get_permalink($post->ID);
        	$result['meta'] = get_post_meta($post->ID);
        	$result['sidebar_menu_name'] = get_post_meta($post->ID, 'sidebar_menu', true);
        	if( $result['sidebar_menu_name'] ) {
        	    $result['sidebar_menu'] = wp_get_nav_menu_items($result['sidebar_menu_name']);
        	}
            if ( ! empty( $post->post_content ) ) {
        		$result['content'] = array(
        			'raw'       => $post->post_content,
        			'rendered'  => apply_filters( 'the_content', $post->post_content ),
        		);
        	}
            if ( ! empty( $post->post_title ) ) {
        		$result['title'] = array(
        			'raw'       => $post->post_title,
        			'rendered' => get_the_title( $post->ID ),
        		);
        	}
            if ( ! empty( $post->post_excerpt ) ) {
        		$result['excerpt'] = array(
            		'raw'       => $post->post_excerpt,
        			//'rendered'  => apply_filters( 'the_content', $post->post_content ),
        		);
        	}
        	$result['thumbnail_url'] = get_the_post_thumbnail_url( $post->ID );
    	}
    	return $result;
    }
    protected function get_path_meta($path) {
        $result = array();
        $result['raw_items'] = [];
        $result['_cs_replacements'] = null;
        $result['cs_modifiable'] = get_option( 'cs_modifiable', array() );
//        $result['wp_get_sidebars_widgets'] = wp_get_sidebars_widgets();
//_cs_replacements
        $sidebar_menu = null;
        $sidebar_menu_name = null;
        $comsep_context_name = null;
        $comsep_context_path = null;

/*
//        'themonic-sidebar'
//        if( is_array($result['_cs_replacements']) ) {
                ob_start();
                dynamic_sidebar( 'themonic-sidebar' );
                $result['sidebar_rendered'] = ob_get_contents();
                ob_end_clean();
//        }
*/

        while( count( $path_items = $this->split_path( $path ) ) > 0 ) {
            $item_data = array();
            $query = $this->query_data_by_path($path);

            if( $query ) {
                $page_type = null;
                $custom_menu_name = null;
                if( $query->is_posts_page ) {
                    $page_type = "news_list";
                }
                if( self::OPTIONS_BY_TYPE[$page_type] && self::OPTIONS_BY_TYPE[$page_type]['custom_menu'] ) {
                    $custom_menu_name = self::OPTIONS_BY_TYPE[$page_type]['custom_menu'];
                }

                $post = $query->post;
                if ( $query->is_singular && ! empty( $post ) ) {
                	$item_data['ID'] = $post->ID;
                	$item_data['permalink'] = get_permalink($post->ID);

//                	$item_data['sidebar_menu_name'] = get_post_meta($post->ID, 'sidebar_menu', true);

                    if( !$sidebar_menu_name ) {
                        $sidebar_menu_name = get_post_meta($post->ID, 'sidebar_menu', true);
                    }
/*
                    if( !$sidebar_menu_name ) {
                        $sidebar_menu_name = $custom_menu_name;
                    }
*/
                    if( !$comsep_context_name ) {
                        $comsep_context_name = get_post_meta($post->ID, 'comsep_context', true);
                        if( $comsep_context_name )
                            $comsep_context_path = $path;
//                            $comsep_context_path = '/' . join('/', $path_items) . '/';
/*
                        else {
                            if('/library/==$path') {
                                $comsep_context_name = 'lib';
                                $comsep_context_path = $path;
                            }
                        }
*/
                    }

                	if( !$result['_cs_replacements'] ) {
                	    $result['_cs_replacements'] = get_post_meta($post->ID, '_cs_replacements', true);
                	}
                }
                if( !$sidebar_menu_name ) {
                    $sidebar_menu_name = $custom_menu_name;
                }
            }


            if( !$comsep_context_name ) {
                if('/library/'==$path) {
                    $comsep_context_name = 'lib';
                    $comsep_context_path = $path;
                }
            }


            $item = array_pop( $path_items );
            $path = $this->join_path_items($path_items);
        }

///*
    	if( $sidebar_menu_name ) {
            $sidebar_menu = wp_get_nav_menu_items( $sidebar_menu_name );
    	}
//*/

        $result['sidebar_menu'] = $sidebar_menu;
        $result['comsep_context_name'] = $comsep_context_name;
        $result['comsep_context_path'] = $comsep_context_path;

/*
//        'themonic-sidebar'
        if( is_array($result['_cs_replacements']) ) {
            $widgets = wp_get_sidebars_widgets();
            $sidebar_name = $result['_cs_replacements']['themonic-sidebar'];
            if( $sidebar_name ) {
                ob_start();
                dynamic_sidebar( $sidebar_name );
                $result['sidebar_rendered'] = ob_get_contents();
                ob_end_clean();
            }
        }
*/

        return $result;
    }
    protected function split_path($path) {
        return array_reduce( explode('/', $path), function($acc, $item) { if($item) $acc[] = $item; return $acc; }, array() );
    }
    protected function join_path_items($items) {
        return ('/' . join('/', $items) . ( count($items)>0 ? '/' : '' ));
    }

///*
    const OPTIONS_BY_TYPE = array(
        'news_list' => array(
            'custom_menu' => 'fe_news_menu'
        )
    );
//*/

}

add_action('rest_api_init', function () {           
    $anud_rest_controller = new ANUD_REST_Controller();
    $anud_rest_controller->register_routes();
});



?>