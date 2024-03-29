<?php

/**
 * Description of Variations Group
 */
class PTPImporter_Variation_Group {

    private $_db;
    private static $_instance;

    public function __construct() {
        global $wpdb;

        $this->_db = $wpdb;
    }

    public static function getInstance() {
        if ( !self::$_instance ) {
            self::$_instance = new PTPImporter_Variation_Group();
        }

        return self::$_instance;
    }


    /**
     * Get all variation groups
     *
     * @return object $groups
     */
    public function all( $parent = 0) {
        global $ptp_importer;

        $terms = get_terms( $ptp_importer->taxonomy, array(
            'parent' => $parent
        ) );
        $groups = array();

        if( !count( $terms ) ) return null;

        foreach ( $terms as $term ) {
            $sql = "SELECT {$this->_db->term_relationships}.object_id";
            $sql .= " FROM {$this->_db->term_relationships}";
            $sql .= " JOIN {$this->_db->term_taxonomy} ON {$this->_db->term_relationships}.term_taxonomy_id = {$this->_db->term_taxonomy}.term_taxonomy_id"; 
            $sql .= " JOIN {$this->_db->posts} ON {$this->_db->term_relationships}.object_id = {$this->_db->posts}.ID"; 
            $sql .= " WHERE {$this->_db->term_taxonomy}.term_id = '%s'"; 
            $sql .= " AND {$this->_db->posts}.post_type = '%s'";  
            $sql .= " AND {$this->_db->posts}.post_status = '%s'"; 
            $sql .= " GROUP BY {$this->_db->term_relationships}.object_id";

            $_variations = $this->_db->get_results( $this->_db->prepare( $sql, $term->term_id, $ptp_importer->post_type, 'publish' ) );

            if ( !$_variations )
                continue;

            $variations = array();
            $count = 0;

            // Prepare variations
            foreach ( $_variations as $_variation ) {
                $variations[$count]['id'] = $_variation->object_id;
                $variations[$count]['name'] = get_post_field( 'post_title', $_variation->object_id );
                $variations[$count]['price'] = get_post_meta( $_variation->object_id, $ptp_importer->variation_price_meta_key, true );

                $count++;
            }

            // Create new attribute and assign Variations to it
            $term->variations = $variations;
            $term->children   = $this->all( $term->term_id );

            // Add to groups container
            $groups[] = $term;
        }

        return $groups;
    }

    /**
     * Traverses the group tree
     * @param  Array $child Optional.
     */
    public function walker( $child = null, $parents = 0 ) {
        if( is_null( $child ) ) {
            $groups = $this->all();
            $count = 0;
            if ( !$groups ) {
                ?>
                    <tr class="no-variation-groups">
                        <td colspan="4">
                            <p> <?php _e( 'You have not added any variation groups yet.', 'ptp' ) ?> </p>
                        </td>
                    </tr>
                <?php
            }
        } else {
            $groups = $child;
        }

		if(is_array($groups))
        foreach( $groups as $group ) : 
            ++$count;
            $alternate = $count % 2 == 0 ? '' : 'class="alternate"';
        ?>
            <tr <?php echo $alternate; ?> id="variation-group-row-<?php echo $group->term_id; ?>">
                <th scope="row" class="check-column">
                    <label class="screen-reader-text" for="cb-select-<?php echo $group->term_id; ?>" > <?php _e( 'Select ' . $group->name, 'ptp' ) ?> </label>
                    <input type="checkbox" name="delete_variations_groups[]" value="<?php echo $group->term_id; ?>"  for="cb-select-<?php echo $group->term_id; ?>" />
                </th>
                <td class="name column-name">
                    <a href="#"><strong>
                        <?php for( $i = 0; $i < $parents; $i++ ) echo '&mdash;'; ?>
                        <?php echo $group->name; ?>
                    </strong></a>
                    <div class="row-actions">
                        <span class="inline hide-if-no-js"> <a href="#" class="quick-edit-variations-group" data-id="<?php echo $group->term_id; ?>"> <?php _e( 'Edit Variation Group', 'ptp' ) ?> </a> &#124; </span>
                        <span class="delete"> <a href="#" class="delete-variations-group" data-id="<?php echo $group->term_id; ?>"> <?php _e( 'Delete', 'ptp' ) ?> </a> </span>
                    </div>
                </td>
                <td class="description column-description"> <span><?php echo $group->description; ?> </span></td>
                <td class="variations column-variations"> <span><?php echo sizeof( $group->variations ) ?> </span></td>
            </tr>
        <?php
            if( $group->children && count( $group->children ) ) {
                $parents++;
                $this->walker( $group->children, $parents );
                $parents--;
            }
        endforeach;

        return true;
    }

    /**
     * Get variation group
     *
     * @param int $term_id
     * @return object $group
     */
    public function group( $term_id ) {
        global $ptp_importer;

        $sql = "SELECT {$this->_db->term_relationships}.object_id";
        $sql .= " FROM {$this->_db->term_relationships}";
        $sql .= " JOIN {$this->_db->term_taxonomy} ON {$this->_db->term_relationships}.term_taxonomy_id = {$this->_db->term_taxonomy}.term_taxonomy_id"; 
        $sql .= " JOIN {$this->_db->posts} ON {$this->_db->term_relationships}.object_id = {$this->_db->posts}.ID"; 
        $sql .= " WHERE {$this->_db->term_taxonomy}.term_id = '%s'";
        $sql .= " AND {$this->_db->posts}.post_type = '%s'";  
        $sql .= " AND {$this->_db->posts}.post_status = '%s'"; 
        $sql .= " GROUP BY {$this->_db->term_relationships}.object_id";

        $_variations = $this->_db->get_results( $this->_db->prepare( $sql, $term_id, $ptp_importer->post_type, 'publish' ) );
        $group = get_term_by( 'id', $term_id, $ptp_importer->taxonomy );

        if ( !$_variations )
            return false;

        $variations = array();
        $count = 0;

        // Prepare variations
        foreach ( $_variations as $_variation ) {
            $variations[$count]['id'] = $_variation->object_id;
            $variations[$count]['name'] = get_post_field( 'post_title', $_variation->object_id );
            $variations[$count]['price'] = get_post_meta( $_variation->object_id, $ptp_importer->variation_price_meta_key, true );
            $variations[$count]['is-downloadable'] = get_post_meta( $_variation->object_id, $ptp_importer->variation_is_downloadable_meta_key, true );
            $variations[$count]['downloadable-width'] = get_post_meta( $_variation->object_id, $ptp_importer->variation_downloadable_width_meta_key, true );
            $variations[$count]['downloadable-height'] = get_post_meta( $_variation->object_id, $ptp_importer->variation_downloadable_height_meta_key, true );

            $count++;
        }

        $group->variations = $variations;

        return $group;
    }

    /**
     * Add variation group
     *
     * @param string $name
     * @param string $description
     * @param array $variations
     * @return int term id
     */
    public function add( $name, $description, $variations, $parent = 0 ) {
        global $ptp_importer;

        $term = wp_insert_term(
            $name,
            $ptp_importer->taxonomy,
            array(
                'description' => $description,
                'parent'      => $parent
            )
        );

        if ( is_wp_error( $term ) )
            return false;

        $post_ids = array();
        foreach ( $variations as $variation ) {
            $args = array(
                'post_title'    => trim( $variation['name'] ),
                'post_content'  => '',
                'post_status'   => 'publish', 
                'post_type'     => $ptp_importer->post_type,
                'post_author'   => get_current_user_id(),
                'tax_input'     => array( $ptp_importer->taxonomy => array( (int)$term['term_id'] ) )
            );

            $post_id = wp_insert_post( $args );
            $post_ids[] = $post_id;

            add_post_meta( $post_id, $ptp_importer->variation_price_meta_key, $variation['price'] );
            add_post_meta( $post_id, $ptp_importer->variation_is_downloadable_meta_key, $variation['downloadable'] );
            add_post_meta( $post_id, $ptp_importer->variation_downloadable_width_meta_key, $variation['downloadable-width'] );
            add_post_meta( $post_id, $ptp_importer->variation_downloadable_height_meta_key, $variation['downloadable-height'] );
        }

        if ( !$post_ids )
            return false;

        return $term['term_id'];
    }

    /**
     * Update variation
     *
     * @param int $term_id
     * @param string $name
     * @param string $description
     * @param array $variations
     * @return int term id
     */
    public function update( $term_id, $name, $description, $variations, $parent = 0 ) {
        global $ptp_importer;

        $product_obj = PTPImporter_Product::getInstance();

        $term = wp_update_term(
            $term_id,
            $ptp_importer->taxonomy,
            array(
                'name'        => $name,
                'description' => $description,
                'parent'      => $parent
            )
        );

        if ( is_wp_error( $term ) )
            return false;

        // Pull data of the group passed
        $group = $this->group( $term_id );
        $variations = array_values( $variations );

        // Get new variations
        $new = $this->new_variations( $group->variations, $variations );

        // If there are new Variations added
        if ( $new ) {
            foreach ( $new as $variation ) {
                $args = array(
                    'post_title'    => $variation['name'],
                    'post_content'  => '',
                    'post_status'   => 'publish', 
                    'post_type'     => $ptp_importer->post_type,
                    'post_author'   => get_current_user_id(),
                    'tax_input'     => array( $ptp_importer->taxonomy => array( (int)$term_id ) )
                );

                $post_id = wp_insert_post( $args );

                add_post_meta( $post_id, $ptp_importer->variation_price_meta_key, $variation['price'] );
                add_post_meta( $post_id, $ptp_importer->variation_is_downloadable_meta_key, $variation['downloadable'] );
                add_post_meta( $post_id, $ptp_importer->variation_downloadable_width_meta_key, $variation['downloadable-width'] );
                add_post_meta( $post_id, $ptp_importer->variation_downloadable_height_meta_key, $variation['downloadable-height'] );
            }

            // Add these variations to old products.
            $product_obj->add_variations( $term_id, $new );
        }

        // Get removed variations
        $removed = $this->removed_variations( $group->variations, $variations );

        // If there are removed Variations
        if ( $removed ) {
            foreach ( $removed as $variation ) {
                wp_delete_post( $variation['id'], true );
                delete_post_meta( $variation['id'], $ptp_importer->variation_price_meta_key, $variation['price'] );
            }

            // Add these variations to old products.
            $product_obj->remove_variations( $term_id, $removed );
        }

        // Get updateed variations
        $updated = $this->updated_variations( $group->variations, $variations );

        if ( !$updated )
            return $term_id;

        // Update old Variations
        foreach ( $updated['updated'] as $variation ) {
            $args = array(
                'ID'            => $variation['id'],
                'post_title'    => $variation['name'],
            );

            $post_id = wp_update_post( $args );
            update_post_meta( $variation['id'], $ptp_importer->variation_price_meta_key, $variation['price'] );
            update_post_meta( $post_id, $ptp_importer->variation_is_downloadable_meta_key, $variation['downloadable'] );
            update_post_meta( $post_id, $ptp_importer->variation_downloadable_width_meta_key, $variation['downloadable-width'] );
            update_post_meta( $post_id, $ptp_importer->variation_downloadable_height_meta_key, $variation['downloadable-height'] );
        }

        // Update these variations of old products.
        $product_obj->update_variations( $term_id, $updated['replaced'], $updated['updated'] );

        return $term_id;
    }

    /**
     * Delete variation group(s)
     *
     * @param array $term_ids
     * @return boolean true|false
     */
    public function delete( $term_ids ) {
        global $ptp_importer;

        foreach ( $term_ids as $term_id ) {
            // Pull data of the group passed
            $group = $this->group( $term_id );

            foreach ( $group->variations as $variation ) {
                wp_delete_post( $variation['id'], true );
                delete_post_meta( $variation['id'], $ptp_importer->variation_price_meta_key, $variation['price'] );
            }

            wp_delete_term( $term_id, $ptp_importer->taxonomy );

            // Remvoe category - variation_group association
            $sql = "DELETE FROM {$this->_db->taxonomymeta} WHERE {$this->_db->taxonomymeta}.meta_value = '%s'"; 

            $res = $this->_db->get_results( $this->_db->prepare( $sql, $term_id ) );
        }

        return true;
    }

    /**
     * Identify Variations to be updated
     *
     * @param $old
     * @param $mixed
     * @return $array $old_variations
     */
    public function updated_variations( $old, $mixed ) {
        $old_ids = array();
        foreach ( $old as $variation ) {
            $old_ids[] = $variation['id'];
        }

        $mixed_ids = array();
        foreach ( $mixed as $variation ) {
            $mixed_ids[] = $variation['id'];
        }

        $intersect_ids = array_intersect( $old_ids, $mixed_ids );

        $intersect_variations = array();
        foreach ( $mixed as $variation ) {
            if ( !in_array( $variation['id'], $intersect_ids ) )
                continue;
            
            $intersect_variations[] = $variation;
        }

        $replaced_variations = array();
        $updated_variations = array();

        // Filter old variations so we only update the edited ones
        foreach ( $intersect_variations as $intersect_variation ) {
            for ( $i = 0; $i < count($old); $i++ ) {
                if ( $intersect_variation['id'] == $old[$i]['id'] && ( $intersect_variation['name'] != $old[$i]['name'] 
                    || $intersect_variation['price'] != $old[$i]['price']
                    || $intersect_variation['downloadable'] != $old[$i]['downloadable']
                    || $intersect_variation['downloadable-width'] != $old[$i]['downloadable-width'] 
                    || $intersect_variation['downloadable-height'] != $old[$i]['downloadable-height'] ) ) {
                    $replaced_variations[] = $old[$i];
                    $updated_variations[] = $intersect_variation;
                }
            }
        }

        return array( 'replaced' => $replaced_variations, 'updated' => $updated_variations );
    }

    /**
     * Identify Variations to be created
     *
     * @param $old
     * @param $mixed
     * @return $array $new_variations
     */
    public function new_variations( $old, $mixed ) {
        $old_ids = array();
        foreach ( $old as $variation ) {
            $old_ids[] = $variation['id'];
        }

        $mixed_ids = array();
        foreach ( $mixed as $variation ) {
            $mixed_ids[] = $variation['id'];
        }

        $diff = array_diff( $mixed_ids, $old_ids );

        if ( !$diff )
            return false;

        $new_variations = array();
        foreach ( $mixed as $variation ) {
            if ( !in_array( $variation['id'], $diff ) )
                continue;
            
            $new_variations[] = $variation;
        }

        return $new_variations;
    }

    /**
     * Identify Variations to be deleted
     *
     * @param $old
     * @param $mixed
     * @return $array $removed_variations
     */
    public function removed_variations( $old, $mixed ) {
        $old_ids = array();
        foreach ( $old as $variation ) {
            $old_ids[] = $variation['id'];
        }

        $mixed_ids = array();
        foreach ( $mixed as $variation ) {
            $mixed_ids[] = $variation['id'];
        }

        $diff = array_diff( $old_ids, $mixed_ids );

        if ( !$diff )
            return false;

        $removed_variations = array();
        foreach ( $old as $variation ) {
            if ( !in_array( $variation['id'], $diff ) )
                continue;
            
            $removed_variations[] = $variation;
        }

        return $removed_variations;
    }

    /**
     * Checks if a group has variations
     *
     * @param int $term_id
     * @return boolean
     */
    public function has_variations( $term_id ) {
        $sql = "SELECT {$this->_db->term_relationships}.object_id";
        $sql .= " FROM {$this->_db->term_relationships}";
        $sql .= " JOIN {$this->_db->term_taxonomy} ON {$this->_db->term_relationships}.term_taxonomy_id = {$this->_db->term_taxonomy}.term_taxonomy_id"; 
        $sql .= " JOIN {$this->_db->posts} ON {$this->_db->term_relationships}.object_id = {$this->_db->posts}.ID"; 
        $sql .= " WHERE {$this->_db->term_taxonomy}.term_id = '%s'"; 
        $sql .= " AND {$this->_db->posts}.post_status = '%s'"; 
        $sql .= " GROUP BY {$this->_db->term_relationships}.object_id";

        $variations = $this->_db->get_row( $this->_db->prepare( $sql, $term_id, 'publish' ) );

        if ( !$variations )
            return false;

        return true;
    }
}