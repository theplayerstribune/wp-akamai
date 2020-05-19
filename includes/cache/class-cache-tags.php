<?php

namespace Akamai\WordPress\Cache;

/**
 * Cache_Tags is a singleton for managing cache tag behavior.
 *
 * Handles basic rules for generating cache tags (ie surrogate keys),
 * and determining which tags are relevant to (emitted as headers or
 * sent in a purge request) for a given post.
 *
 * @todo:
 *   - get_tags_for_purge_author( ??? ) : [string]
 *
 * @since   0.7.0
 * @package Akamai\WordPress\Cache
 */
class Cache_Tags {

    /**
     * The one instance of Cache_Tags.
     *
     * @since 0.7.0
     * @var   Cache_Tags
     */
    private static $instance;

    /**
     * The template types to always (usually) include when purging.
     *
     * @since 0.7.0
     * @var   array
     */
    public static $always_purged_templates = [
        'post',
        'home',
        'feed',
        '404',
    ];

    /**
     * Standard tag codes for types of tags.
     *
     * @since 0.7.0
     * @var   array
     */
    public static $default_codes = [
        'post'      => 'p',
        'term'      => 't',
        'author'    => 'a',
        'template'  => 'tm',
        'multisite' => 's',
    ];

    /**
     * Instantiate or return the one Cache_Tags instance.
     *
     * @since  0.7.0
     * @param  string     $plugin The Plugin class instance.
     * @return Cache_Tags The created instance.
     */
    public static function instance( $plugin ) {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self( $plugin );
        }
        return self::$instance;
    }

    /**
     * A reference to the Akamai Plugin class instance.
     *
     * @since  0.7.0
     * @access protected
     * @var    string $plugin The Akamai Plugin class instance.
     */
    protected $plugin;

    /**
     * Instantiates an instance.
     *
     * @since  0.7.0
     * @access protected
     * @param  string $plugin The Akamai class instance.
     */
    protected function __construct( $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * A helper for standardizing / customizing tag generation.
     *
     * @since  0.7.0
     * @param  string $name  The code to identify the type of tag.
     * @param  string $value The value (usually an int ID) for the tag.
     * @return string A formatted tag part (code-value).
     */
    public function tag_part( $name, $value ) {
        $code = apply_filters(
            "akamai_{$name}_code", static::$default_codes[$name] );
        return sprintf( '%s-%s', $code, $value );
    }

    /**
     * A helper for standardizing / customizing tag generation.
     *
     * @since  0.7.0
     * @return string The current unique site code.
     */
    public function get_site_code() {
        $site_code = $this->plugin->setting( 'unique-sitecode' );
        if ( $site_code === '' ) {
            return urlencode( $this->plugin->get_hostname() );
        }
        return $site_code;
    }

    /**
     * A helper for standardizing / customizing tag generation.
     *
     * @since  0.7.0
     * @return string The unique site code (-part) for the current site.
     *                Same as the site code, unless multisite, then it's
     *                unique to the current site/blog.
     */
    public function get_site_prefix() {
        if ( is_multisite() ) {
            $tag_part = $this->tag_part( 'multisite', get_current_blog_id() );
            return $this->get_site_code() . '-' . $tag_part;
        } else {
            return $this->get_site_code();
        }
    }

    /**
     * A tag to represent an entire site / blog (in multisite).
     *
     * @since  0.7.0
     * @return string The unique site prefix prepended to '-all'.
     */
    public function get_site_tag() {
        return $this->get_site_prefix() . '-all';
    }

    /**
     * Post tag helper to generate standardized, unique tags.
     *
     * @since  0.7.0
     * @param  \WP_Post|int $value The post or post id to generate a tag for.
     * @return string The tag.
     */
    public function get_post_tag( $value ) {
        if ( $value instanceof \WP_Post ) {
            $value = $value->ID;
        }
        $tag_part = $this->tag_part( 'post', (string) $value );
        return $this->get_site_prefix() . '-' . $tag_part;
    }

    /**
     * Term tag helper to generate standardized, unique tags.
     *
     * @since  0.7.0
     * @param  \WP_Term|int $value The term or term id to generate a tag for.
     * @return string The tag.
     */
    public function get_term_tag( $value ) {
        if ( $value instanceof \WP_Term ) {
            $value = $value->term_id;
        }
        $tag_part = $this->tag_part( 'term', (string) $value );
        return $this->get_site_prefix() . '-' . $tag_part;
    }

    /**
     * User (author) tag helper to generate standardized, unique tags.
     *
     * @since  0.7.0
     * @param  \WP_User|int $value The user or user id to generate a tag for.
     * @return string The tag.
     */
    public function get_author_tag( $value ) {
        if ( $value instanceof \WP_User ) {
            $value = $value->id;
        }
        $tag_part = $this->tag_part( 'author', (string) $value );
        return $this->get_site_prefix() . '-' . $tag_part;
    }

    /**
     * Template tag helper to generate standardized, unique tags.
     *
     * @since  0.7.0
     * @param  string $value The template type to generate a tag for.
     * @return string The tag.
     */
    public function get_template_tag( $template_type ) {
        $tag_part = $this->tag_part( 'template', $template_type );
        return $this->get_site_prefix() . '-' . $tag_part;
    }

    /**
     * Formats the always purged template types as tags, and then filters
     * to allow more.
     *
     * @since  0.7.0
     * @return array The list of built tags for always purged types.
     */
    public function always_purged_tags() {
        foreach ( static::$always_purged_templates as $template_type ) {
            $tags[] = $this->get_template_tag( $template_type );
        }
        return apply_filters(
            'akamai_always_purged_tags', $tags, static::$instance );
    }

    /**
     * Builds the always cached tags (associated with the site).
     *
     * @since  0.7.0
     * @return array The list of always cached tags.
     */
    public function always_cached_tags() {
        $tags = [ $this->get_site_code() ];
        if ( is_multisite() ) {
            $tags[] = $this->get_site_prefix();
        }
        return apply_filters(
            'akamai_always_cached_tags', $tags, static::$instance );
    }

    /**
     * Get the given post's author tag, wrapped in an array for simplicity's
     * sake.
     *
     * @since  0.7.0
     * @param  \WP_Post $post The post to search for related author information.
     * @return array    The author tag(s).
     */
    public function related_author_tags( $post ) {
        if ( is_int( $post ) ) {
            $post = get_post( $post );
        }
        if ( ! empty( $post ) ) {
            $author_id = absint( $post->post_author );
            if ( $author_id > 0 ) {
                return [ $this->get_author_tag( $author_id ) ];
            }
        }
        return [];
    }

    /**
     * Get the term link pages for all terms associated with a post
     * every taxonomy. Filter taxonomies for fun and profit.
     *
     * @since  0.7.0
     * @param  \WP_Post $post The post to search for related term information.
     * @return array    The related term tags.
     */
    public function related_term_tags( $post ) {
        $tags = [];

        if ( is_int( $post ) ) {
            $post = get_post( $post );
        }
        if ( ! empty( $post ) ) {
            $taxonomies = apply_filters(
                'akamai_purge_taxonomies',
                (array) get_taxonomies()
            );

            foreach ( $taxonomies as $taxonomy ) {
                $terms = wp_get_post_terms(
                    $post->ID,
                    $taxonomy,
                    [ 'fields' => 'ids' ]
                );

                if ( is_array( $terms ) ) {
                    foreach ( $terms as $term ) {
                        if ( $term ) {
                            $tags[] = $this->get_term_tag( $term );
                        }
                    }
                }
            }
        }
        return $tags;
    }

    /**
     * Get all the posts that have this term assigned to them.
     *
     * @since  0.7.0
     * @param  \WP_Term $term The term to search for related posts.
     * @param  string   $taxonomy Optional. The taxonomy of the term.
     * @return array    The related post tags.
     */
    public function related_post_tags( $term, $taxonomy ) {
        $tags = [];

        if ( ! is_string( $taxonomy ) ) {
            $taxonomy = '';
        }
        if ( is_int( $term ) ) {
            $term = get_term( $term, $taxonomy );
        }

        if ( ! empty( $term ) ) {
            $post_types = apply_filters(
                'akamai_purge_post_types',
                [ 'post', 'page' ]
            );
            $post_statuses = apply_filters(
                'akamai_purge_post_statuses',
                [ 'publish', 'trash', 'future', 'draft' ]
            );
            $post_ids = get_posts([
                'post_type' => $post_types,
                'post_status' => $post_statuses,
                'tax_query' => [
                    [
                        'taxonomy' => $taxonomy,
                        'terms'    => $term->term_id
                    ]
                ],
                'fields' => 'ids',
                'numberposts' => -1,
            ]);
            foreach ( $post_ids as $post_id ) {
                if ( $post_id ) {
                    $tags[] = $this->get_post_tag( $post_id );
                }
            }
        }

        return $tags;
    }

    /**
     * Get any ancestor (parent, etc) terms in the given term's taxonomy.
     *
     * @since  0.7.0
     * @param  \WP_Term $term The term to search for ancestor terms.
     * @param  string   $taxonomy Optional. The taxonomy of the term.
     * @return array    The related term tags.
     */
    public function ancestor_term_tags( $term, $taxonomy ) {
        $tags = [];

        if ( ! is_string( $taxonomy ) ) {
            $taxonomy = '';
        }
        if ( is_int( $term ) ) {
            $term = get_term( $term, $taxonomy );
        }

        if ( ! empty( $term ) ) {
            $term_ids = get_ancestors( $term->term_id, $taxonomy, 'taxonomy' );
            foreach ( $term_ids as $term_id ) {
                if ( $term_id ) {
                    $tags[] = $this->get_term_tag( $term_id );
                }
            }
        }

        return $tags;
    }

    /**
     * Get a list of tags to send in cache tag headers for a single post
     * (page, post, attachment, CPT, &c).
     *
     * @since  0.7.0
     * @param  \WP_Post $post The post to generate emitted tags for.
     * @param  bool     $related Optional. Also emit related posts, terms and
     *                           authors. Defaults to true.
     * @return array    The tags.
     */
    public function get_tags_for_emit_post( $post, $related = true ) {
        $tags = [];

        if ( is_int( $post ) ) {
            $post = get_post( $post );
        }
        if ( empty( $post ) ) {
            return apply_filters(
                'akamai_emit_post_tags', $tags, $post, static::$instance );
        }
        $tags[] = $this->get_post_tag( $post );

        if ( $related ) {
            $r_posts = apply_filters(
                'akamai_emit_post_related_posts', [], $post, static::$instance );
            $r_terms = apply_filters(
                'akamai_emit_post_related_terms',
                $this->related_term_tags( $post ),
                $post,
                static::$instance
            );
            $r_authors = apply_filters(
                'akamai_emit_post_related_authors',
                $this->related_author_tags( $post ),
                $post,
                static::$instance
            );
            $tags = array_merge( $tags, $r_posts, $r_terms, $r_authors );
        }

        return apply_filters(
            'akamai_emit_post_tags', $tags, $post, static::$instance );
    }

    /**
     * Get a list of tags to send to Akamai for purging when a post needs
     * to be purged.
     *
     * @since  0.7.0
     * @param  \WP_Post $post The post to generate purge tags for.
     * @param  bool     $related Optional. Also purge related posts, terms and
     *                  authors. Defaults to true.
     * @param  bool     $always  Optional. Also purge the always-purged tags.
     *                  Defaults to true.
     * @return array    The tags.
     */
    public function get_tags_for_purge_post( $post, $related = true, $always = true ) {
        $tags = [];

        if ( is_int( $post ) ) {
            $post = get_post( $post );
        }
        if ( empty( $post ) ) {
            return apply_filters(
                'akamai_purge_post_tags', $tags, $post, static::$instance );
        }
        $tags[] = $this->get_post_tag( $post );

        if ( $related ) {
            $r_posts = apply_filters(
                'akamai_purge_post_related_posts', [], $post, static::$instance );
            $r_terms = apply_filters(
                'akamai_purge_post_related_terms',
                $this->related_term_tags( $post ),
                $post,
                static::$instance
            );
            $r_authors = apply_filters(
                'akamai_purge_post_related_authors',
                $this->related_author_tags( $post ),
                $post,
                static::$instance
            );
            $tags = array_merge( $tags, $r_posts, $r_terms, $r_authors );
        }

        if ( $always ) {
            $tags = array_merge( $this->always_purged_tags(), $tags );
        }

        return apply_filters(
            'akamai_purge_post_tags', $tags, $post, static::$instance );
    }

    /**
     * Get a list of tags to send in cache tag headers for a single term
     * (tag, category, &c).
     *
     * @since  0.7.0
     * @param  \WP_Term $term The post to generate emitted tags for.
     * @param  bool     $related Optional. Also emit related posts, terms and
     *                  authors. Defaults to true.
     * @return array    The tags.
     */
    public function get_tags_for_emit_term( $term, $taxonomy, $related = true ) {
        $tags = [];

        if ( ! is_string( $taxonomy ) ) {
            $taxonomy = '';
        }
        if ( is_int( $term ) ) {
            $term = get_term( $term, $taxonomy );
        }
        if ( empty( $term ) ) {
            return apply_filters(
                'akamai_emit_term_tags', $tags, $term, static::$instance );
        }
        $tags[] = $this->get_term_tag( $term );

        if ( $related ) {
            $r_terms = apply_filters(
                'akamai_emit_term_ancestor_terms',
                $this->ancestor_term_tags( $term, $taxonomy ),
                $term,
                $taxonomy,
                static::$instance
            );
            $r_posts = apply_filters(
                'akamai_emit_term_related_posts',
                $this->related_post_tags( $term, $taxonomy ),
                $term,
                $taxonomy,
                static::$instance
            );
            $tags = array_merge( $tags, $r_terms, $r_posts );
        }

        return apply_filters(
            'akamai_emit_term_tags', $tags, $term, static::$instance );
    }

    /**
     * Get a list of tags to send to Akamai for purging when a term needs
     * to be purged.
     *
     * @since  0.7.0
     * @param  \WP_Term $term The post to generate purge tags for.
     * @param  bool     $related Optional. Also purge related posts, terms and
     *                  authors. Defaults to true.
     * @param  bool     $always  Optional. Also purge the always-purged tags.
     *                  Defaults to true.
     * @return array    The tags.
     */
    public function get_tags_for_purge_term( $term, $taxonomy, $related = true, $always = true ) {
        $tags = [];

        if ( ! is_string( $taxonomy ) ) {
            $taxonomy = '';
        }
        if ( is_int( $term ) ) {
            $term = get_term( $term, $taxonomy );
        }
        if ( empty( $term ) ) {
            return apply_filters(
                'akamai_purge_term_tags', $tags, $term, static::$instance );
        }
        $tags[] = $this->get_term_tag( $term );

        if ( $related ) {
            $r_terms = apply_filters(
                'akamai_purge_term_ancestor_terms',
                $this->ancestor_term_tags( $term, $taxonomy ),
                $term,
                $taxonomy,
                static::$instance
            );
            $r_posts = apply_filters(
                'akamai_purge_term_related_posts',
                $this->related_post_tags( $term, $taxonomy ),
                $term,
                $taxonomy,
                static::$instance
            );
            $tags = array_merge( $tags, $r_terms, $r_posts );
        }

        if ( $always ) {
            $tags = array_merge( $this->always_purged_tags(), $tags );
        }

        return apply_filters(
            'akamai_purge_term_tags', $tags, $term, static::$instance );
    }

    /**
     * Get the purge this specific site tag, in a multisite setup this
     * is a bit more targeted than purging all.
     *
     * @since  0.7.0
     * @return array List of tags necessary to purge the specifically
     *               current multisite site.
     */
    public function get_tags_for_purge_multisite_site() {
        return [ $this->get_site_tag() ];
    }

    /**
     * Get the purge ALL tag: ie the unique site code plus '-all'.
     *
     * @since  0.7.0
     * @return array List of tags necessary to purge the entire site
     *               (or all sites in multisite).
     */
    public function get_tags_for_purge_all() {
        return [ $this->get_site_code() . '-all' ];
    }

    /**
     * Get the tags that need to be universally emitted to allow simple
     * purging of sites and multisite properties.
     *
     * @since  0.7.0
     * @return array List of tags necessary to identify the specifically
     *               current multisite site and/or the site as whole.
     */
    public function get_tags_for_emit_universal() {
        return array_unique(
            [
                $this->get_site_tag(),
                $this->get_site_code() . '-all',
            ]
        );
    }
}
