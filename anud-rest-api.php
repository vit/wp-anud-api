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
    }

    public function route_handler($request) {
//        $parameters = $request->get_query_params();
//        $path = $parameters["path"];
        $path = $request->get_query_params()["path"];

        $query = $this->query_data_by_path($path);
    
        $result = $this->process_result($query);
        $result['path_meta'] = $this->get_path_meta($path);
    
        return $result;
    }
    
    public function route_raw_handler($request) {
//        $parameters = $request->get_query_params();
        $path = $request->get_query_params()["path"];
        return $this->query_data_by_path($path);
    }

    // ***********************************************************

    function query_data_by_path($path) {
        global $wp;
        global $wp_query;

        $_SERVER['REQUEST_URI'] = $path;
        $wp->parse_request();
        $wp->query_posts();

        return $wp_query;
    }

    function process_result($data) {
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
    function process_post($post) {
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
    function get_path_meta($path) {
        $result = array();

        $sidebar_menu = null;

        while( count( $path_items = $this->split_path( $path ) ) > 0 ) {
            $item_data = array();
            $query = $this->query_data_by_path($path);

            if( $query ) {
                $post = $query->post;
                if ( ! empty( $post ) ) {
                	$item_data['ID'] = $post->ID;
                	$item_data['permalink'] = get_permalink($post->ID);
                	//$item_data['meta'] = get_post_meta($post->ID);
                	$item_data['sidebar_menu_name'] = get_post_meta($post->ID, 'sidebar_menu', true);
                	if( $item_data['sidebar_menu_name'] && !$menu) {
                        //$item_data['sidebar_menu'] = wp_get_nav_menu_items($item_data['sidebar_menu_name']);
                        $sidebar_menu = wp_get_nav_menu_items($item_data['sidebar_menu_name']);
                	}
                }

            }

//            $result[] = array( 'path' => $path, 'data' => $item_data );

            $item = array_pop( $path_items );
            $path = $this->join_path_items($path_items);
        }

//        $path_items = $this->split_path( $path );
//        $path_joined = $this->join_path_items($path_items);

//        $result['path_items'] = $path_items;
//        $result['path_joined'] = $path_joined;

        $result['sidebar_menu'] = $sidebar_menu;

        return $result;
    }
    function split_path($path) {
        return array_reduce( explode('/', $path), function($acc, $item) { if($item) $acc[] = $item; return $acc; }, array() );
    }
    function join_path_items($items) {
        return ('/' . join('/', $items) . ( count($items)>0 ? '/' : '' ));
    }

}

add_action('rest_api_init', function () {           
    $anud_rest_controller = new ANUD_REST_Controller();
    $anud_rest_controller->register_routes();
});



?>