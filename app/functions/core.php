<?php
use TriTan\Common\Container as c;
use TriTan\Common\Date;
use TriTan\Common\Options\Options;
use TriTan\Common\Options\OptionsMapper;
use TriTan\Common\Context\HelperContext;
use TriTan\Common\FileSystem;
use Cascade\Cascade;
use Respect\Validation\Validator as v;
use Qubus\Hooks\ActionFilterHook;
use Qubus\Exception\Http\Client\NotFoundException;
use Qubus\Exception\Data\TypeException;

define('CURRENT_RELEASE', get_current_release());
define('REQUEST_TIME', ttcms()->obj['app']->req->server['REQUEST_TIME']);

/**
 * Qubus CMS Core Functions
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

/**
 * Turn all URLs into clickable links.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $value
 * @param array  $protocols  http/https, ftp, mail, twitter
 * @param array  $attributes
 * @param string $mode       normal or all
 * @return string
 */
function make_clickable($value, $protocols = ['http', 'mail'], array $attributes = [])
{
    // Link attributes
    $attr = '';
    foreach ($attributes as $key => $val) {
        $attr = ' ' . $key . '="' . htmlentities($val) . '"';
    }

    $links = [];

    // Extract existing links and tags
    $value = preg_replace_callback('~(<a .*?>.*?</a>|<.*?>)~i', function ($match) use (&$links) {
        return '<' . array_push($links, $match[1]) . '>';
    }, $value);

    // Extract text links for each protocol
    foreach ((array) $protocols as $protocol) {
        switch ($protocol) {
            case 'http':
            case 'https':
                $value = preg_replace_callback('~(?:(https?)://([^\s<]+)|(www\.[^\s<]+?\.[^\s<]+))(?<![\.,:])~i', function ($match) use ($protocol, &$links, $attr) {
                    if ($match[1]) {
                        $protocol = $match[1];
                    }
                    $link = $match[2] ?: $match[3];
                    return '<' . array_push($links, "<a $attr href=\"$protocol://$link\">$link</a>") . '>';
                }, $value);
                break;
            case 'mail':
                $value = preg_replace_callback('~([^\s<]+?@[^\s<]+?\.[^\s<]+)(?<![\.,:])~', function ($match) use (&$links, $attr) {
                    return '<' . array_push($links, "<a $attr href=\"mailto:{$match[1]}\">{$match[1]}</a>") . '>';
                }, $value);
                break;
            case 'twitter':
                $value = preg_replace_callback('~(?<!\w)[@#](\w++)~', function ($match) use (&$links, $attr) {
                    return '<' . array_push($links, "<a $attr href=\"https://twitter.com/" . ($match[0][0] == '@' ? '' : 'search/%23') . $match[1] . "\">{$match[0]}</a>") . '>';
                }, $value);
                break;
            default:
                $value = preg_replace_callback('~' . preg_quote($protocol, '~') . '://([^\s<]+?)(?<![\.,:])~i', function ($match) use ($protocol, &$links, $attr) {
                    return '<' . array_push($links, "<a $attr href=\"$protocol://{$match[1]}\">{$match[1]}</a>") . '>';
                }, $value);
                break;
        }
    }

    // Insert all link
    return preg_replace_callback('/<(\d+)>/', function ($match) use (&$links) {
        return $links[$match[1] - 1];
    }, $value);
}

function print_gzipped_page()
{
    global $HTTP_ACCEPT_ENCODING;
    if (headers_sent()) {
        $encoding = false;
    } elseif (strpos($HTTP_ACCEPT_ENCODING, 'x-gzip') !== false) {
        $encoding = 'x-gzip';
    } elseif (strpos($HTTP_ACCEPT_ENCODING, 'gzip') !== false) {
        $encoding = 'gzip';
    } else {
        $encoding = false;
    }

    if ($encoding) {
        $contents = ob_get_contents();
        ob_end_clean();
        header('Content-Encoding: ' . $encoding);
        print("\x1f\x8b\x08\x00\x00\x00\x00\x00");
        $size = strlen($contents);
        $contents = gzcompress($contents, 9);
        $contents = substr($contents, 0, $size);
        print($contents);
        exit();
    } else {
        ob_end_flush();
        exit();
    }
}

function percent($num_amount, $num_total)
{
    $count1 = $num_amount / $num_total;
    $count2 = $count1 * 100;
    $count = number_format($count2, 0);
    return $count;
}

/**
 * Prints generator meta tag in admin head.
 *
 * @since 1.0.0
 * @return string
 */
function head_release_meta()
{
    echo "<meta name='generator' content='Qubus CMS " . CURRENT_RELEASE . "'>\n";
}

/**
 * Prints installed Qubus CMS release in admin footer.
 *
 * @since 1.0.0
 * @return string
 */
function foot_release()
{
    $release = '<strong>' . t__('Release', 'tritan-cms') . '</strong> ' . CURRENT_RELEASE;
    echo ActionFilterHook::getInstance()->applyFilter('admin_release_footer', $release);
}

/**
 * Prints a list of timezones which includes
 * current time.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @return array Timezone list.
 */
function generate_timezone_list()
{
    static $regions = array(
        \DateTimeZone::AFRICA,
        \DateTimeZone::AMERICA,
        \DateTimeZone::ANTARCTICA,
        \DateTimeZone::ASIA,
        \DateTimeZone::ATLANTIC,
        \DateTimeZone::AUSTRALIA,
        \DateTimeZone::EUROPE,
        \DateTimeZone::INDIAN,
        \DateTimeZone::PACIFIC
    );

    $timezones = [];
    foreach ($regions as $region) {
        $timezones = array_merge($timezones, \DateTimeZone::listIdentifiers($region));
    }

    $timezone_offsets = [];
    foreach ($timezones as $timezone) {
        $tz = new \DateTimeZone($timezone);
        $timezone_offsets[$timezone] = $tz->getOffset(new \DateTime());
    }

    // sort timezone by timezone name
    ksort($timezone_offsets);

    $timezone_list = [];
    foreach ($timezone_offsets as $timezone => $offset) {
        $offset_prefix = $offset < 0 ? '-' : '+';
        $offset_formatted = gmdate('H:i', abs($offset));

        $pretty_offset = "UTC${offset_prefix}${offset_formatted}";

        $t = new \DateTimeZone($timezone);
        $c = new \DateTime(null, $t);
        $current_time = $c->format('g:i A');

        $timezone_list[$timezone] = "(${pretty_offset}) $timezone - $current_time";
    }

    return $timezone_list;
}

/**
 * Get age by birthdate.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $birthdate User's birth date.
 * @return mixed
 */
function get_age($birthdate = '0000-00-00')
{
    $birth = new Date($birthdate);
    $age = $birth->date->age;

    if ($birthdate <= '0000-00-00' || $age <= 0) {
        return t__('Unknown', 'tritan-cms');
    }
    return $age;
}

/**
 * Converts a string into unicode values.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $string
 * @return mixed
 */
function unicoder($string)
{
    $p = str_split(trim($string));
    $new_string = '';
    foreach ($p as $val) {
        $new_string .= '&#' . ord($val) . ';';
    }
    return $new_string;
}

/**
 * Returns the layout header information
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string (optional) $layout_dir loads layouts from specified folder
 * @return mixed
 */
function get_layouts_header($layout_dir = '')
{
    $layouts_header = [];
    if ($handle = opendir($layout_dir)) {
        while ($file = readdir($handle)) {
            if (is_file($layout_dir . $file)) {
                if (strpos($layout_dir . $file, '.layout.php')) {
                    $fp = fopen($layout_dir . $file, 'r');
                    // Pull only the first 8kiB of the file in.
                    $layout_data = fread($fp, 8192);
                    fclose($fp);

                    preg_match('|Layout Name:(.*)$|mi', $layout_data, $name);
                    preg_match('|Layout Slug:(.*)$|mi', $layout_data, $layout_slug);

                    foreach ([
                      'name',
                      'layout_slug'
                    ] as $field) {
                        if (!empty(${$field})) {
                            ${$field} = trim(${$field}[1]);
                        } else {
                            ${$field} = '';
                        }
                    }
                    $layout_data = [
                        'filename' => $file,
                        'Name' => $name,
                        'Title' => $name,
                        'Slug' => $layout_slug
                    ];
                    $layouts_header[] = $layout_data;
                }
            } elseif ((is_dir($layout_dir . $file)) && ($file != '.') && ($file != '..')) {
                get_layouts_header($layout_dir . $file . '/');
            }
        }

        closedir($handle);
    }
    return $layouts_header;
}

/**
 * Strips out all duplicate values and compact the array.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param mixed $a An array that be compacted.
 * @return mixed
 */
function array_unique_compact($a)
{
    $tmparr = array_unique($a);
    $i = 0;
    foreach ($tmparr as $v) {
        $newarr[$i] = $v;
        $i ++;
    }
    return $newarr;
}

/**
 * Checks the mime type of a file.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $file  File to check.
 * @param int $mode     Perform a full check or extension check only.
 * @return bool
 */
function check_mime_type($file, $mode = 0)
{
    if ('' == _trim($file)) {
        $message = t__('Invalid file: empty file given.', 'tritan-cms');
        _incorrectly_called(__FUNCTION__, $message, '1.0.0');
        return;
    }

    // mode 0 = full check
    // mode 1 = extension check only
    $mime_types = [
        'txt' => 'text/plain',
        'csv' => 'text/plain',
        // images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        // archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        // adobe
        'pdf' => 'application/pdf',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',
        // ms office
        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        'docx' => 'application/msword',
        'xlsx' => 'application/vnd.ms-excel',
        'pptx' => 'application/vnd.ms-powerpoint'
    ];

    $ext = strtolower(array_pop(explode('.', $file)));

    if (function_exists('mime_content_type') && $mode == 0) {
        $mimetype = mime_content_type($file);
        return $mimetype;
    }

    if (function_exists('finfo_open') && $mode == 0) {
        $finfo = finfo_open(FILEINFO_MIME);
        $mimetype = finfo_file($finfo, $file);
        finfo_close($finfo);
        return $mimetype;
    } elseif (array_key_exists($ext, $mime_types)) {
        return $mime_types[$ext];
    }
}

/**
 * Validates a plugin and checks to make sure there are no syntax and/or
 * parsing errors.
 *
 * Uses `activate_plugin`, `activate_{$plugin_name}`, and `activated_plugin`
 * actions hooks.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $plugin_name Name of the plugin file (i.e. disqus.plugin.php).
 */
function validate_plugin($plugin_name)
{
    $plugin = str_replace('.plugin.php', '', $plugin_name);

    if (!(
            new \TriTan\Common\FileSystem(
                ActionFilterHook::getInstance()
            )
            )->exists(TTCMS_PLUGIN_DIR . $plugin . DS . $plugin_name, false)
    ) {
        $file = TTCMS_PLUGIN_DIR . $plugin_name;
    } else {
        $file = TTCMS_PLUGIN_DIR . $plugin . DS . $plugin_name;
    }

    $error = (
            new \TriTan\Common\FileSystem(
                ActionFilterHook::getInstance()
            )
            )->checkSyntax($file);

    if (check_qubus_exception($error)) {
        ttcms()->obj['flash']->error(
            t__(
                'Plugin could not be activated because it triggered a <strong>fatal error</strong>. <br /><br />',
                'tritan-cms'
            ) . $error->getMessage()
        );
        return false;
    }

    try {
        if ((
                new \TriTan\Common\FileSystem(
                    ActionFilterHook::getInstance()
                )
                )->exists($file)
        ) {
            include_once($file);
        }
    } catch (NotFoundException $e) {
        Cascade::getLogger('error')->error(
            sprintf(
                'FILESTATE[%s]: File not found: %s',
                $e->getCode(),
                $e->getMessage()
            )
        );
    }

    /**
     * Fires before a specific plugin is activated.
     *
     * $pluginName refers to the plugin's
     * name (i.e. disqus.plugin.php).
     *
     * @since 1.0.0
     * @param string $plugin_name The plugin's base name.
     */
    ActionFilterHook::getInstance()->doAction('activate_plugin', $plugin_name);

    /**
     * Fires as a specifig plugin is being activated.
     *
     * $pluginName refers to the plugin's
     * name (i.e. disqus.plugin.php).
     *
     * @since 1.0.0
     * @param string $plugin_name The plugin's base name.
     */
    ActionFilterHook::getInstance()->doAction('activate_' . $plugin_name);

    /**
     * Activate the plugin if there are no errors.
     *
     * @since 1.0.0
     * @param string $plugin_name The plugin's base name.
     */
    activate_plugin($plugin_name);

    /**
     * Fires after a plugin has been activated.
     *
     * $pluginName refers to the plugin's
     * name (i.e. disqus.plugin.php).
     *
     * @since 1.0.0
     * @param string $plugin_name The plugin's base name.
     */
    ActionFilterHook::getInstance()->doAction('activated_plugin', $plugin_name);
}

/**
 * Check the plugins directory and retrieve all plugin files with plugin data.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $plugin_folder Relative path to single plugin folder.
 *                              Default value: ''
 * @return array Key is the plugin file path and the value is an array
 *               of the plugin data.
 */
function get_plugins($plugin_folder = '')
{
    $cache_plugins = ttcms()->obj['cache']->read('plugins', 'plugins');
    if (! $cache_plugins) {
        $cache_plugins = [];
    }

    if (isset($cache_plugins[ $plugin_folder ])) {
        return $cache_plugins[ $plugin_folder ];
    }
    $ttcms_plugins  = [];
    $plugin_root = TTCMS_PLUGIN_DIR;
    if (! empty($plugin_folder)) {
        $plugin_root .= $plugin_folder;
    }
    // Files in plugins directory
    $plugins_dir  = @ opendir($plugin_root);
    $plugin_files = [];
    if ($plugins_dir) {
        while (($file = readdir($plugins_dir)) !== false) {
            if (substr($file, 0, 1) == '.') {
                continue;
            }
            if (is_dir($plugin_root . '/' . $file)) {
                $plugins_subdir = @ opendir($plugin_root . '/' . $file);
                if ($plugins_subdir) {
                    while (($subfile = readdir($plugins_subdir)) !== false) {
                        if (substr($subfile, 0, 1) == '.') {
                            continue;
                        }
                        if (substr($subfile, -11) == '.plugin.php') {
                            $plugin_files[] = "$file/$subfile";
                        }
                    }
                    closedir($plugins_subdir);
                }
            } else {
                if (substr($file, -11) == '.plugin.php') {
                    $plugin_files[] = $file;
                }
            }
        }
        closedir($plugins_dir);
    }
    if (empty($plugin_files)) {
        return $ttcms_plugins;
    }
    foreach ($plugin_files as $plugin_file) {
        if (! is_readable("$plugin_root/$plugin_file")) {
            continue;
        }
        $plugin_data = get_plugin_data("$plugin_root/$plugin_file"); //Do not apply markup/translate as it'll be cached.
        if (empty($plugin_data['Name'])) {
            continue;
        }
        $ttcms_plugins[ plugin_basename($plugin_file) ] = $plugin_data;
    }

    uasort($ttcms_plugins, 'element_sort_callback');
    $cache_plugins[ $plugin_folder ] = $ttcms_plugins;
    ttcms()->obj['cache']->set('plugins', $cache_plugins, 'plugins');

    return $ttcms_plugins;
}

/**
 * Takes an array and turns it into an object.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param array $array Array of data.
 */
function convert_array_to_object(array $array)
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = convert_array_to_object($value);
        }
    }
    return (object) $array;
}

/**
 * Strip close comment and close php tags from file headers.
 *
 * @file app/functions/core.php
 *
 * @access private
 * @since 1.0.0
 * @param string $str Header comment to clean up.
 * @return string
 */
function _ttcms_cleanup_file_header_comment($str)
{
    return trim(preg_replace("/\s*(?:\*\/|\?>).*/", '', $str));
}

/**
 * Retrieve metadata from a file.
 *
 * Searches for metadata in the first 8kB of a file, such as a plugin or layout.
 * Each piece of metadata must be on its own line. Fields can not span multiple
 * lines, the value will get cut at the end of the first line.
 *
 * If the file data is not within that first 8kB, then the author should correct
 * their plugin file and move the data headers to the top.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $file Path to the file.
 * @param array $default_headers List of headers, in the format array('HeaderKey' => 'Header Name').
 * @param string $context Optional. If specified adds filter hook "extra_{$context}_headers".
 *                        Default empty.
 * @return array Array of file headers in `HeaderKey => Header Value` format.
 */
function get_file_data($file, $default_headers, $context = '')
{
    // We don't need to write to the file, so just open for reading.
    $fp = fopen($file, 'r');
    // Pull only the first 8kB of the file in.
    $file_data = fread($fp, 8192);
    // PHP will close file handle.
    fclose($fp);
    // Make sure we catch CR-only line endings.
    $file_data = str_replace("\r", "\n", $file_data);
    /**
     * Filter extra file headers by context.
     *
     * The dynamic portion of the hook name, `$context`, refers to
     * the context where extra headers might be loaded.
     *
     * @since 1.0.0
     *
     * @param array $extra_context_headers Empty array by default.
     */
    if ($context && $extra_headers = ActionFilterHook::getInstance()->applyFilter("extra_{$context}_headers", [])) {
        $extra_headers = array_combine($extra_headers, $extra_headers); // keys equal values
        $all_headers = array_merge($extra_headers, (array) $default_headers);
    } else {
        $all_headers = $default_headers;
    }
    foreach ($all_headers as $field => $regex) {
        if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $file_data, $match) && $match[1]) {
            $all_headers[$field] = _ttcms_cleanup_file_header_comment($match[1]);
        } else {
            $all_headers[$field] = '';
        }
    }
    return $all_headers;
}

/**
 * Parses the plugin contents to retrieve plugin's metadata.
 *
 * The metadata of the plugin's data searches for the following in the plugin's
 * header. All plugin data must be on its own line. For plugin description, it
 * must not have any newlines or only parts of the description will be displayed
 * and the same goes for the plugin data. The below is formatted for printing.
 *
 * /*
 * Plugin Name: Name of Plugin
 * Plugin URI: Link to plugin information
 * Description: Plugin Description
 * Author: Plugin author's name
 * Author URI: Link to the author's web site
 * Version: Plugin version value.
 * Text Domain: Optional. Unique identifier, should be same as the one used in
 * load_plugin_textdomain()
 *
 * The first 8kB of the file will be pulled in and if the plugin data is not
 * within that first 8kB, then the plugin author should correct their plugin
 * and move the plugin data headers to the top.
 *
 * The plugin file is assumed to have permissions to allow for scripts to read
 * the file. This is not checked however and the file is only opened for
 * reading.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 *
 * @param string $plugin_file Path to the plugin file
 * @return array {
 *         Plugin data. Values will be empty if not supplied by the plugin.
 *
 *         @type string $Name Name of the plugin. Should be unique.
 *         @type string $Title Title of the plugin and link to the plugin's site (if set).
 *         @type string $Description Plugin description.
 *         @type string $Author Author's name.
 *         @type string $AuthorURI Author's website address (if set).
 *         @type string $Version Plugin version.
 *         @type string $TextDomain Plugin textdomain.
 *         @type string $DomainPath Plugins relative directory path to .mo files.
 *         }
 */
function get_plugin_data($plugin_file)
{
    $default_headers = [
        'Name' => 'Plugin Name',
        'PluginURI' => 'Plugin URI',
        'Version' => 'Version',
        'Description' => 'Description',
        'Author' => 'Author',
        'AuthorURI' => 'Author URI',
        'TextDomain' => 'Text Domain'
    ];
    $plugin_data = get_file_data($plugin_file, $default_headers, 'plugin');
    $plugin_data['Title'] = $plugin_data['Name'];
    $plugin_data['AuthorName'] = $plugin_data['Author'];
    return $plugin_data;
}

/*function get_plugin_data_test($plugin_path)
{
    $_plugin_path = remove_trailing_slash($plugin_path);
    $plugin_data = json_decode(
        preg_replace(
            '/[\x00-\x1F\x80-\xFF]/',
            '',
            file_get_contents($_plugin_path . DS . 'plugin.json')
        ),
        true
    );
    return $plugin_data;
}*/

/**
 * Converts seconds to time format.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param numeric $seconds
 */
function convert_seconds_to_time($seconds)
{
    $ret = "";

    /** get the days */
    $days = intval(intval($seconds) / (3600 * 24));
    if ($days > 0) {
        $ret .= "$days days ";
    }

    /** get the hours */
    $hours = (intval($seconds) / 3600) % 24;
    if ($hours > 0) {
        $ret .= "$hours hours ";
    }

    /** get the minutes */
    $minutes = (intval($seconds) / 60) % 60;
    if ($minutes > 0) {
        $ret .= "$minutes minutes ";
    }

    /** get the seconds */
    $seconds = intval($seconds) % 60;
    if ($seconds > 0) {
        $ret .= "$seconds seconds";
    }

    return $ret;
}

/**
 * Add the template to the message body.
 *
 * Looks for {content} into the template and replaces it with the message.
 *
 * Uses `email_template` filter hook.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $body The message to templatize.
 * @return string $email The email surrounded by template.
 */
function set_email_template($body)
{
    $tpl = (new FileSystem(ActionFilterHook::getInstance()))->getContents(APP_PATH . 'views' . DS . '_layouts' . DS . 'system_email.tpl');

    $template = ActionFilterHook::getInstance()->applyFilter('email_template', $tpl);

    return str_replace('{content}', $body, $template);
}

/**
 * Replace variables in the template.
 *
 * Uses `email_template_tags` filter hook.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $template Template with variables.
 * @return string Template with variables replaced.
 */
function replace_template_vars($template)
{
    $qudb = app()->qudb;

    $option = new Options(
        new OptionsMapper(
            $qudb,
            new HelperContext()
        )
    );
    $var_array = [
        'site_name' => $option->read('sitename'),
        'site_url' => site_url(),
        'site_description' => $option->read('site_description'),
        'admin_email' => $option->read('admin_email'),
        'date_format' => $option->read('date_format'),
        'time_format' => $option->read('time_format')
    ];

    $to_replace = ActionFilterHook::getInstance()->applyFilter('email_template_tags', $var_array);

    foreach ($to_replace as $tag => $var) {
        $template = str_replace(' . $tag . ', $var, $template);
    }

    return $template;
}

/**
 * Process the HTML version of the text.
 *
 * Uses `email_template_body` filter hook.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $text
 * @param string $title
 * @return string
 */
function process_email_html($text, $title)
{
    // Convert URLs to links
    $links = make_clickable($text);

    // Add template to message
    $template = set_email_template($links);

    // Replace title tag with $title.
    $body = str_replace('{title}', $title, $template);

    // Replace variables in email
    $message = ActionFilterHook::getInstance()->applyFilter('email_template_body', replace_template_vars($body));

    return $message;
}

/**
 * Retrieve the domain name.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @return string
 */
function get_domain_name()
{
    $server_name = strtolower(ttcms()->obj['app']->req->server['SERVER_NAME']);
    if (substr($server_name, 0, 4) == 'www.') {
        $server_name = substr($server_name, 4);
    }
    return $server_name;
}

/**
 * SQL Like operator in PHP.
 *
 * Returns `true` if match else `false`.
 *
 * Example Usage:
 *
 *      php_like('%uc%','Lucy'); //true
 *      php_like('%cy', 'Lucy'); //true
 *      php_like('lu%', 'Lucy'); //true
 *      php_like('%lu', 'Lucy'); //false
 *      php_like('cy%', 'Lucy'); //false
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $pattern
 * @param string $subject
 * @return bool
 */
function php_like($pattern, $subject): bool
{
    $match = str_replace('%', '.*', preg_quote($pattern, '/'));
    return (bool) preg_match("/^{$match}$/i", $subject);
}

/**
 * SQL Where operator in PHP.
 *
 * @since 1.0.0
 * @param string $key
 * @param string $operator
 * @param type $pattern
 * @return bool
 * @throws \\Qubus\\Exception\\Data\\TypeException
 */
function php_where(string $key, string $operator, $pattern): bool
{
    switch ($operator) {
        case '=':
            $filter = $key == $pattern;
            break;
        case '>':
            $filter = $key > $pattern;
            break;
        case '>=':
            $filter = $key >= $pattern;
            break;
        case '<':
            $filter = $key < $pattern;
            break;
        case '<=':
            $filter = $key <= $pattern;
            break;
        case 'in':
            $filter = in_array($key, (array) $pattern);
            break;
        case 'not in':
            $filter = !in_array($key, (array) $pattern);
            break;
        case 'match':
            $filter = (bool) preg_match($pattern, $key);
            break;
        case 'between':
            if (!is_array($pattern) or count($pattern) < 2) {
                throw new TypeException("Query 'between' needs exactly 2 items in array.");
            }
            $filter = $key >= $pattern[0] && $key <= $pattern[1];
            break;
    }
    return $filter;
}

/**
 * Create a backup of Qubus CMS install.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param type $source Path/directory to zip.
 * @param type $destination Target for zipped file.
 * @return mixed
 */
function backup_system($source, $destination)
{
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new \ZipArchive();
    if (!$zip->open($destination, $zip::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true) {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) {
                continue;
            }

            $file = realpath($file);

            if (is_dir($file) === true) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            } elseif (is_file($file) === true) {
                $zip->addFromString(
                    str_replace($source . '/', '', $file),
                    (new FileSystem(ActionFilterHook::getInstance()))->getContents($file)
                );
            }
        }
    } elseif (is_file($source) === true) {
        $zip->addFromString(basename($source), (new FileSystem(ActionFilterHook::getInstance()))->getContents($source));
    }

    return $zip->close();
}

/**
 * Used to retrieve values within a range.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param mixed $val
 * @param mixed $min
 * @param mixed $max
 * @return bool
 */
function ttcms_between($val, $min, $max)
{
    return ($val - $min) * ($val - $max) <= 0;
}

/**
 * Sort array of objects by field.
 *
 * Example Usage:
 *
 *      sort_list($post,'post_id','ASC', false);
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param array $objects        Array of objects to sort.
 * @param string/array $orderby Name of field or array of fields to filter by.
 * @param string $order         (ASC|DESC)
 * @param bool $preserve_keys   Whether to preserve keys.
 * @return array Returns a sorted array.
 */
function sort_list(&$objects, $orderby = [], $order = 'ASC', $preserve_keys = false)
{
    if (!is_array($objects)) {
        return [];
    }

    $util = new \TriTan\ListUtil($objects);
    return $util->sort($orderby, $order, $preserve_keys);
}

/**
 * Pluralizes a word if quantity is not one.
 *
 * Example Usage:
 *
 *      pluralize(4, 'cat'); // cats
 *      pluralize(3, 'kitty'); // kitties
 *      pluralize(2, 'octopus', 'octopii'); // octopii
 *      pluralize(1, 'mouse', 'mice'); // mouse
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param int $quantity     Number of items.
 * @param string $singular  Singular form of word.
 * @param string $plural    Plural form of word; function will attempt to deduce plural form from singular if not provided.
 * @return string Pluralized word if quantity is not one, otherwise singular.
 */
function pluralize($quantity, $singular, $plural = null)
{
    if ($quantity == 1 || !strlen($singular)) {
        return $singular;
    }

    if ($plural !== null) {
        return $plural;
    }

    $last_letter = strtolower($singular[strlen($singular) - 1]);
    switch ($last_letter) {
        case 'y':
            return substr($singular, 0, -1) . 'ies';
        case 's':
            return $singular . 'es';
        default:
            return $singular . 's';
    }
}

/**
 * Validates a url.
 *
 * Example Usage:
 *
 *      if(validate_url('https://google.com/')) {
 *          //do something;
 *      }
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $url Url to validate.
 * @return bool True if valid, false otherwise.
 */
function validate_url($url)
{
    return v::filterVar(FILTER_VALIDATE_URL)->validate($url);
}

/**
 * Get a list of themes available for a specific site.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $active The name to check against.
 * @return array Theme options to choose from.
 */
function get_site_themes($active = null)
{
    $themes = (
        new \TriTan\Common\FileSystem(
            ActionFilterHook::getInstance()
        ))->directoryListing(c::getInstance()->get('theme_dir'));

    if (is_array($themes)) {
        foreach ($themes as $theme) {
            echo '<option value="' . $theme . '"' . selected($theme, $active, false) . '>' . $theme . '</option>';
        }
    }
}

/**
 * Enqueues stylesheets.
 *
 * Uses `default_css_pipeline`, `plugin_css_pipeline` and `theme_css_pipeline`
 * filter hooks.
 *
 * Example Usage:
 *
 *      ttcms_enqueue_css('default', '//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css')
 *      ttcms_enqueue_css('plugin', ['fontawesome','select2-css'], false, plugin_basename( dirname(__FILE__) ))
 *      ttcms_enqueue_css('theme', 'style.css')
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $config            Set whether to use `default` config or `plugin` config.
 * @param string|array $asset       Relative path or URL to stylesheet(s) to enqueue.
 * @param bool|string $minify       Enable css assets pipeline (concatenation and minification).
 *                                  Use a string that evaluates to `true` to provide the salt of the pipeline hash.
 *                                  Use 'auto' to automatically calculate the salt from your assets last modification time.
 * @param string|null $plugin_slug  Plugin slug to set plugin's asset location
 * @return string Stylesheet asset(s).
 */
function ttcms_enqueue_css($config, $asset, $minify = false, $plugin_slug = null)
{
    if ($config === 'default') {
        $options = [
            'public_dir' => remove_trailing_slash(BASE_PATH),
            'css_dir' => 'static' . DS . 'assets' . DS . 'css',
            'pipeline' => ActionFilterHook::getInstance()->applyFilter('default_css_pipeline', $minify),
            'pipeline_dir' => 'minify',
            'collections' => [
                'colorpicker-css' => 'bootstrap-colorpicker/bootstrap-colorpicker.min.css',
                'fontawesome' => '//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css',
                'ionicons' => '//cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css',
                'datatables-css' => 'datatables/dataTables.bootstrap.css',
                'select2-css' => 'select2/select2.min.css',
                'datetimepicker-css' => 'bootstrap-datetimepicker/bootstrap-datetimepicker.min.css',
                'switchery-css' => 'bootstrap-switchery/switchery.min.css'
            ]
        ];
        $default = new \Stolz\Assets\Manager($options);
        $default->reset()->add($asset);
    } elseif ($config === 'plugin') {
        $options = [
            'public_dir' => remove_trailing_slash(BASE_PATH),
            'css_dir' => 'plugins' . DS . $plugin_slug . DS . 'assets' . DS . 'css',
            'pipeline' => ActionFilterHook::getInstance()->applyFilter('plugin_css_pipeline', $minify),
            'pipeline_dir' => 'minify'
        ];
        $default = new \Stolz\Assets\Manager($options);
        $default->reset()->add($asset);
    } elseif ($config === 'theme') {
        $options = [
            'public_dir' => remove_trailing_slash(BASE_PATH),
            'css_dir' => 'private' . DS . 'sites' . DS . c::getInstance()->get('site_id') . DS . 'themes' . DS . c::getInstance()->get('active_theme') . DS . 'assets' . DS . 'css',
            'pipeline' => ActionFilterHook::getInstance()->applyFilter('theme_css_pipeline', $minify),
            'pipeline_dir' => 'minify'
        ];
        $default = new \Stolz\Assets\Manager($options);
        $default->reset()->add($asset);
    }
    echo $default->css();
}

/**
 * Enqueues javascript.
 *
 * Uses `default_js_pipeline`, `plugin_js_pipeline` and `theme_js_pipeline`
 * filter hooks.
 *
 * Example Usage:
 *
 *      ttcms_enqueue_js('default', 'jquery-ui')
 *      ttcms_enqueue_js('plugin', 'select2-js', false, plugin_basename( dirname(__FILE__) ))
 *      ttcms_enqueue_js('theme', 'config.js')
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $config            Set whether to use `default`, `plugin`  or `theme` config.
 * @param string|array $asset       Relative path or URL to javascripts(s) to enqueue.
 * @param bool|string $minify       Enable js assets pipeline (concatenation and minification).
 *                                  Use a string that evaluates to `true` to provide the salt of the pipeline hash.
 *                                  Use 'auto' to automatically calculate the salt from your assets last modification time.
 * @param string|null $plugin_slug  Plugin slug to set plugin's asset location.
 * @return string Javascript asset(s).
 */
function ttcms_enqueue_js($config, $asset, $minify = false, $plugin_slug = null)
{
    if ($config === 'default') {
        $options = [
            'public_dir' => remove_trailing_slash(BASE_PATH),
            'js_dir' => 'static' . DS . 'assets' . DS . 'js',
            'pipeline' => ActionFilterHook::getInstance()->applyFilter('default_js_pipeline', $minify),
            'pipeline_dir' => 'minify',
            'collections' => [
                'jquery' => '//cdnjs.cloudflare.com/ajax/libs/jquery/2.2.4/jquery.js',
                'jquery-ui' => [
                    'jquery',
                    '//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js'
                ],
                'colorpicker-js' => [
                    'bootstrap-colorpicker/bootstrap-colorpicker.min.js',
                    'bootstrap-colorpicker/config.js'
                ],
                'datatables-js' => [
                    'datatables/jquery.dataTables.min.js',
                    'datatables/dataTables.bootstrap.min.js',
                    'pages/datatable.js'
                ],
                'datetimepicker-js' => 'bootstrap-datetimepicker/bootstrap-datetimepicker.min.js',
                'select2-js' => [
                    'select2/select2.full.min.js',
                    'pages/select2.js'
                ],
                'switchery-js' => 'bootstrap-switchery/switchery.min.js'
            ]
        ];
        $default = new \Stolz\Assets\Manager($options);
        $default->reset()->add($asset);
    } elseif ($config === 'plugin') {
        $options = [
            'public_dir' => remove_trailing_slash(BASE_PATH),
            'js_dir' => 'plugins' . DS . $plugin_slug . DS . 'assets' . DS . 'js',
            'pipeline' => ActionFilterHook::getInstance()->applyFilter('plugin_js_pipeline', $minify),
            'pipeline_dir' => 'minify'
        ];
        $default = new \Stolz\Assets\Manager($options);
        $default->reset()->add($asset);
    } elseif ($config === 'theme') {
        $options = [
            'public_dir' => remove_trailing_slash(BASE_PATH),
            'js_dir' => 'private' . DS . 'sites' . DS . c::getInstance()->get('site_id') . DS . 'themes' . DS . c::getInstance()->get('active_theme') . DS . 'assets' . DS . 'js',
            'pipeline' => ActionFilterHook::getInstance()->applyFilter('theme_js_pipeline', $minify),
            'pipeline_dir' => 'minify'
        ];
        $default = new \Stolz\Assets\Manager($options);
        $default->reset()->add($asset);
    }
    echo $default->js();
}

/**
 * Generates a random password drawn from the defined set of characters.
 *
 * Uses `random_lib` library to create passwords with far less predictability.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param int  $length              Optional. The length of password to generate. Default 12.
 * @param bool $special_chars       Optional. Whether to include standard special characters.
 *                                  Default true.
 * @param bool $extra_special_chars Optional. Whether to include other special characters.
 *                                  Default false.
 * @return string The system generated password.
 */
function generate_random_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false)
{
    return (
        new \TriTan\Common\Password\PasswordGenerate(
            ActionFilterHook::getInstance()
        )
    )->generate($length, $special_chars, $extra_special_chars);
}

/**
 * Returns the maintenance mode route.
 *
 * @since 1.0.0
 * @param object $app The application object.
 * @return bool|route
 */
function ttcms_maintenance_mode($app)
{
    $qudb = app()->qudb;

    if ((
            new Options(
                new OptionsMapper(
                    $qudb,
                    new HelperContext()
                )
            ))->read('maintenance_mode') == (int) 0
    ) {
        return false;
    }

    $path_info = new TriTan\Common\Uri(ActionFilterHook::getInstance());

    if (strpos($path_info->getPathInfo('/logout'), "/logout") === 0) {
        return false;
    }

    if (strpos($path_info->getPathInfo('/reset-password'), "/reset-password") === 0) {
        return false;
    }

    $app->match('GET|POST', '/(.*)', function () use ($app) {
        $app->res->_format('json', 503);
        exit();
    });

    return $app;
}

/**
 * Checks whether a file or directory exists.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @param string $filename  Path to the file or directory.
 * @param bool $throw       Determines whether to do a simple check or throw an exception.
 *                          Default: true.
 * @return boolean <b>true</b> if the file or directory specified by
 * <i>$filename</i> exists; <b>false</b> otherwise.
 * @throws NotFoundException If file does not exist.
 */
function check_file_exists($filename, $throw = true)
{
    return (
        new \TriTan\Common\FileSystem(
            ActionFilterHook::getInstance()
        )
    )->exists($filename, $throw);
}

/**
 * Sorts a structured array by 'Name' property.
 *
 * Callback for uasort() within get_plugins().
 *
 * @file app/functions/core.php
 *
 * @access private
 * @since 1.0.0
 * @param array $a First item for comparison. The compared items should be
 *                 associative arrays that optionally include a 'Name' key.
 * @param array $b Second item for comparison.
 * @return int Return 0, -1, or 1 based on two string comparison.
 */
function element_sort_callback($a, $b)
{
    $a_name = (is_array($a) && isset($a['Name'])) ? $a['Name'] : 0;
    $b_name = (is_array($b) && isset($b['Name'])) ? $b['Name'] : 0;
    if ($a_name == $b_name) {
        return 0;
    }
    return ($a_name < $b_name) ? -1 : 1;
}

/**
 * Converts date to GMT format.
 *
 * @since 1.0.0
 * @param string $string The date to be converted.
 * @param string $format The format string for the converted date.
 */
function convert_date_to_gmt(string $string = 'now', string $format = 'Y-m-d H:i:s')
{
    $date = str_replace(['AM', 'PM'], get_user_timezone(), $string);
    return (new Date(strtotime($date), new \DateTimeZone('GMT')))->format($format);
}

/**
 * Get the current screen.
 *
 * @file app/functions/core.php
 *
 * @since 1.0.0
 * @return string|null Current screen or null if screen is not defined.
 */
function get_current_screen()
{
    $current_screen = c::getInstance()->has('screen_child') ?
        c::getInstance()->get('screen_child') :
        c::getInstance()->get('screen_parent');

    if (!isset($current_screen)) {
        return null;
    }

    return $current_screen;
}

/**
 * Checks which CMS is installed (TriTan or Qubus).
 *
 * @since 1.0.0
 * @return string CMS name.
 */
function which_cms()
{
    $cms = explode("\n", (new FileSystem(ActionFilterHook::getInstance()))->getContents('RELEASE'));
    return $cms[0];
}
