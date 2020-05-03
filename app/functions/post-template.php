<?php
use TriTan\Common\Container as c;
use Qubus\Hooks\ActionFilterHook;

/**
 * Displays the permalink for the current post.
 *
 * Uses `the_permalink` filter.
 *
 * @file app/functions/post-template.php
 *
 * @since 1.0.0
 * @param int|array|Post $post Post object, id or array.
 * @return string Post permalink.
 */
function the_permalink($post = 0)
{
    /**
     * Filters the display of the permalink for the current post.
     *
     * @since 1.0.0
     * @param string         $permalink The permalink for the current post.
     * @param int|array|Post $post      Post object, id or array. Default 0.
     */
    return ActionFilterHook::getInstance()->applyFilter('the_permalink', get_permalink($post), $post);
}

/**
 * The Qubus CMS post filter.
 *
 * Uses `the_content` filter.
 *
 * @file app/functions/post-template.php
 *
 * @since 1.0.0
 * @param int|Post $post Optional. Post id or post object.
 *                       Default is global TriTan\Common\Container::getInstance()->get('post_id') if set.
 * @return string Post content.
 */
function the_content($post = 0)
{
    $post_content = get_post_content($post);
    $post_content = ActionFilterHook::getInstance()->applyFilter('the_content', $post_content);
    $post_content = str_replace(']]>', ']]&gt;', $post_content);
    return $post_content;
}

/**
 * Display the id of the current post in the loop.
 *
 * @file app/functions/post-template.php
 *
 * @since 1.0.0
 * @return int Post id on success or false if global post_id is not set.
 */
function the_postid()
{
    return (int) get_the_postid();
}

/**
 * Display the title of the current post in the loop.
 *
 * @file app/functions/post-template.php
 *
 * @since 1.0.0
 * @param string $before HTML output before post title.
 * @param string $after  HTML output after post title.
 * @return string
 */
function the_title(string $before = '', string $after = '')
{
    $the_title = $before . get_post_title() . $after;

    if (strlen($the_title) == 0) {
        return;
    }
    /**
     * Filters the post title.
     *
     * @since 1.0.0
     * @param string $the_title The post title.
     * @param string $before HTML output before post title.
     * @param string $after  HTML output after post title.
     */
    return ActionFilterHook::getInstance()->applyFilter('the_title', $the_title, $before, $after);
}

/**
 * Displays the post's datetime.
 *
 * @file app/functions/post-template.php
 *
 * @since 1.0.0
 * @param string         $before HTML output before post datetime.
 * @param string         $after  HTML output after post datetime.
 * @return string Post datetime.
 */
function the_datetime(string $before = '', string $after = '')
{
    $the_datetime = $before . get_post_datetime() . $after;
    /**
     * Filters the post datetime.
     *
     * @since 1.0.0
     * @param string         $the_datetime Post datetime.
     * @param string         $before       HTML output before the datetime.
     * @param string         $after        HTML output after the datetime.
     */
    return ActionFilterHook::getInstance()->applyFilter('the_datetime', $the_datetime, $before, $after);
}

/**
 * Displays the post's date.
 *
 * @since 1.0.0
 * @param string $before HTML output before post date.
 * @param string $after  HTML output after post date.
 * @param string $type
 * @return string Post date.
 */
function the_date(string $before = '', string $after = '', string $type = 'published')
{
    $the_date = $before . get_post_date($type) . $after;
    /**
     * Filters the date.
     *
     * @since 1.0.0
     */
    return ActionFilterHook::getInstance()->applyFilter("the_{$type}_date", $the_date, $before, $after, $type);
}

/**
 * Displays the post's time.
 *
 * @since 1.0.0
 * @param string $before HTML output before post time.
 * @param string $after  HTML output after post time.
 * @param string $type
 * @return string Post time.
 */
function the_time(string $before = '', string $after = '', string $type = 'published')
{
    $the_time = $before . get_post_time($type) . $after;
    /**
     * Filters the time.
     *
     * @since 1.0.0
     */
    return ActionFilterHook::getInstance()->applyFilter("the_{$type}_time", $the_time, $before, $after, $type);
}

function has_posts()
{
    $query = c::getInstance()->get('query');
    return $query->hasPosts();
}

function the_post()
{
    $query = c::getInstance()->get('query');
    $query->thePost();
}

function the_post_class($class = '', $post_id = null)
{
    return 'class="' . join(' ', get_post_class($class, $post_id)) . '"';
}

/**
 * Retrieves and displays post meta value.
 *
 * @file app/functions/post-template.php
 *
 * @since 1.0.0
 * @param string $key     Post meta key.
 * @return string Post meta value.
 */
function the_meta(string $key)
{
    $the_meta = get_postmeta(c::getInstance()->get('post_id'), $key, true);
    /**
     * Filters post meta.
     *
     * @since 1.0.0
     * @param mixed  $the_meta Post meta value.
     * @param string $key      Post meta key.
     */
    return ActionFilterHook::getInstance()->applyFilter('the_meta', $the_meta, $key);
}

function the_author()
{
    $the_author = get_post_author();
    /**
     * Filters the post author.
     *
     * @since 1.0.0
     * @param string $the_author Post author.
     */
    return ActionFilterHook::getInstance()->applyFilter('the_author', $the_author);
}

function the_author_id()
{
    $the_author_id = get_post_author_id();
    /**
     * Filters the post author id.
     *
     * @since 1.0.0
     * @param int $the_author_id Post author id.
     */
    return ActionFilterHook::getInstance()->applyFilter('the_author_id', $the_author_id);
}

function siteinfo($show = '')
{
    return get_siteinfo($show, 'display');
}
