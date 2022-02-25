<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Class Disciple_Tools_Meetings_Base
 * Load the core post type hooks into the Disciple.Tools system
 */
class Disciple_Tools_Meetings_Base  {

    /**
     * Define post type variables
     * @var string
     */
    public $post_type = "meetings";
    public $single_name = 'Meeting';
    public $plural_name = 'Meetings';
    public static function post_type(){
        return 'meetings';
    }

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        //setup post type
        add_action( 'after_setup_theme', [ $this, 'after_setup_theme' ], 100 );
        add_filter( 'dt_set_roles_and_permissions', [ $this, 'dt_set_roles_and_permissions' ], 20, 1 ); //after contacts

        //setup tiles and fields
        add_action( 'p2p_init', [ $this, 'p2p_init' ] );
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 10, 2 );
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 10, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 20, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );

        // hooks
        add_action( "post_connection_removed", [ $this, "post_connection_removed" ], 10, 4 );
        add_action( "post_connection_added", [ $this, "post_connection_added" ], 10, 4 );
        add_filter( "dt_post_update_fields", [ $this, "dt_post_update_fields" ], 10, 3 );
        add_filter( "dt_post_create_fields", [ $this, "dt_post_create_fields" ], 10, 2 );
        add_action( "dt_post_created", [ $this, "dt_post_created" ], 10, 3 );
        add_action( "dt_comment_created", [ $this, "dt_comment_created" ], 10, 4 );

        //list
        add_filter( "dt_user_list_filters", [ $this, "dt_user_list_filters" ], 10, 2 );
        add_filter( "dt_filter_access_permissions", [ $this, "dt_filter_access_permissions" ], 20, 2 );
        add_filter( "dt_capabilities", [ $this, "dt_capabilities" ], 100, 1 );
    }

    public function after_setup_theme(){
        if ( class_exists( 'Disciple_Tools_Post_Type_Template' ) ) {
            new Disciple_Tools_Post_Type_Template( $this->post_type, $this->single_name, $this->plural_name );
        }
    }

    /**
     * Adding capability meta.
     */
    public function dt_capabilities( $capabilities ){
        $capabilities['access_meetings'] = [
            'source' => 'Meetings',
            'description' => 'The user can view meetings.'
        ];
        $capabilities['update_meetings'] = [
            'source' => 'Meetings',
            'description' => 'The user can edit existing existing.'
        ];
        $capabilities['create_meetings'] = [
            'source' => 'Meetings',
            'description' => 'The user can create meetings.'
        ];
        return $capabilities;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/roles-permissions.md#rolesd
     */
    public function dt_set_roles_and_permissions( $expected_roles ){

        // if the user can access contact they also can access this post type
        foreach ( $expected_roles as $role => $role_value ){

            if ( isset( $expected_roles[$role]["permissions"]['access_groups'] ) && $expected_roles[$role]["permissions"]['access_groups'] ){
                $expected_roles[$role]["permissions"]["access_meetings"] = true;
                $expected_roles[$role]["permissions"]["update_meetings"] = true;
                $expected_roles[$role]["permissions"]["create_meetings"] = true;
            }
        }
        return $expected_roles;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/fields.md
     */
    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === $this->post_type ){
            $fields["date"] = [
                "name" => "Date",
                "type" => "date",
                "tile" => "status",
                "in_create_form" => true
            ];
            $fields["meeting_notes"] = [
                "name" => "Meeting Notes",
                "type" => "text",
                "tile" => "details",
                "in_create_form" => true
            ];
            $fields["contacts"] = [
                "name" => "Contacts",
                "type" => "connection",
                "p2p_direction" => "from",
                "post_type" => "contacts",
                "tile" => "other",
                "p2p_key" => "meetings_to_contacts",
            ];
            $fields["groups"] = [
                "name" => "Groups",
                "type" => "connection",
                "p2p_direction" => "from",
                "post_type" => "groups",
                "tile" => "other",
                "p2p_key" => "meetings_to_groups"
            ];
            $fields['tags'] = [
                'name'        => __( 'Tags', 'disciple_tools' ),
                'description' => _x( 'A useful way to group related items and can help group contacts associated with noteworthy characteristics. e.g. business owner, sports lover. The contacts can also be filtered using these tags.', 'Optional Documentation', 'disciple_tools' ),
                'type'        => 'tags',
                'default'     => [],
                'tile'        => 'other',
                'icon' => get_template_directory_uri() . "/dt-assets/images/tag.svg",
            ];
        }
        if ( $post_type === "contacts" ){
            $fields["meetings"] = [
                "name" => "Meetings",
                "type" => "connection",
                "p2p_direction" => "to",
                "post_type" => "meetings",
                "tile" => "other",
                "p2p_key" => "meetings_to_contacts"
            ];
        }
        if ( $post_type === "groups" ){
            $fields["meetings"] = [
                "name" => "Meetings",
                "type" => "connection",
                "p2p_direction" => "to",
                "post_type" => "meetings",
                "tile" => "other",
                "p2p_key" => "meetings_to_groups"
            ];
        }

        return $fields;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/fields.md#declaring-connection-fields
     */
    public function p2p_init(){
        /**
         * Connection to contacts
         */
        p2p_register_connection_type(
            [
                'name'           => $this->post_type."_to_contacts",
                'from'           => $this->post_type,
                'to'             => 'contacts',
            ]
        );
        /**
         * Connection to groups
         */
        p2p_register_connection_type(
            [
                'name'           => $this->post_type."_to_groups",
                'from'           => $this->post_type,
                'to'             => 'groups',
            ]
        );
    }

    /**
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/field-and-tiles.md
     */
    public function dt_details_additional_tiles( $tiles, $post_type = "" ){
        if ( $post_type === $this->post_type ){
            $tiles["other"] = [ "label" => __( "Other", 'disciple_tools' ) ];
        }
        return $tiles;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/field-and-tiles.md#add-custom-content
     */
    public function dt_details_additional_section( $section, $post_type ){
    }

    /**
     * action when a post connection is added during create or update
     * @todo catch field changes and do additional processing
     *
     * The next three functions are added, removed, and updated of the same field concept
     */
    public function post_connection_added( $post_type, $post_id, $field_key, $value ){
    }

    //action when a post connection is removed during create or update
    public function post_connection_removed( $post_type, $post_id, $field_key, $value ){
    }

    //filter at the start of post update
    public function dt_post_update_fields( $fields, $post_type, $post_id ){
        return $fields;
    }


    //filter when a comment is created
    public function dt_comment_created( $post_type, $post_id, $comment_id, $type ){
    }

    // filter at the start of post creation
    public function dt_post_create_fields( $fields, $post_type ){
        if ( $post_type === $this->post_type ) {
            if ( !isset( $fields["date"] ) ){
                $fields["date"] = time();
            }
        }
        return $fields;
    }

    //action when a post has been created
    public function dt_post_created( $post_type, $post_id, $initial_fields ){
    }


    //build list page filters
    public static function dt_user_list_filters( $filters, $post_type ){
        /**
         * @todo process and build filter lists
         */
        if ( $post_type === self::post_type() ){
            $post_label_plural = DT_Posts::get_post_settings( $post_type )['label_plural'];

            $filters["tabs"][] = [
                "key" => "default",
                "label" => __( "Default Filters", 'disciple_tools' ),
                "order" => 7
            ];
            $filters["filters"][] = [
                'ID' => 'all_my_meetings',
                'tab' => 'default',
                'name' => sprintf( _x( "All %s", 'All records', 'disciple_tools' ), $post_label_plural ),
                'labels' =>[
                    [
                        'id' => 'all',
                        'name' => sprintf( _x( "All %s I can view", 'All records I can view', 'disciple_tools' ), $post_label_plural ),
                    ]
                ],
                'query' => [
                    'sort' => '-post_date',
                ],
            ];
            $filters["filters"][] = [
                'ID' => 'recent',
                'tab' => 'default',
                'name' => __( "My Recently Viewed", 'disciple_tools' ),
                'query' => [
                    'dt_recent' => true
                ],
                'labels' => [
                    [ "id" => 'recent', 'name' => __( "Last 30 viewed", 'disciple_tools' ) ]
                ]
            ];
        }
        return $filters;
    }

    // access permission
    public static function dt_filter_access_permissions( $permissions, $post_type ){
        if ( $post_type === self::post_type() ){
            if ( DT_Posts::can_view_all( $post_type ) ){
                $permissions = [];
            }
        }
        return $permissions;
    }

    // scripts
    public function scripts(){
        if ( is_singular( $this->post_type ) ){
            // @todo add enqueue scripts
            dt_write_log( __METHOD__ );
        }
    }
}


