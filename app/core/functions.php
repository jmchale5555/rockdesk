<?php

use Core\Image;
use Core\Session;

defined('ROOTPATH') or exit('Access Denied');

/** check which php extensions are required **/
check_extensions();
function check_extensions()
{

    $required_extensions = [

        'gd',
        'mysqli',
        'pdo_mysql',
        'pdo_sqlite',
        'curl',
        'fileinfo',
        'intl',
        'ldap',
        'exif',
        'mbstring',
    ];

    $not_loaded = [];

    foreach ($required_extensions as $ext)
    {

        if (!extension_loaded($ext))
        {
            $not_loaded[] = $ext;
        }
    }

    if (!empty($not_loaded))
    {
        show("Please ensure that the following PHP extensions are installed and loaded: <br>" . implode("<br>", $not_loaded));
        die;
    }
}

function show($stuff)
{
    echo "<pre>";
    print_r($stuff);
    echo "</pre>";
}

function dd($stuff)
{
    echo "<pre>";
    var_dump($stuff);
    echo "</pre>";

    die();
}

function esc($str = "")
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize_rich_text(string $html): string
{
    $html = trim($html);

    if ($html === '')
    {
        return '';
    }

    if (!class_exists(HTMLPurifier::class))
    {
        return esc($html);
    }

    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    $config->set('HTML.Allowed', 'div,p,br,strong,b,em,i,del,ul,ol,li,blockquote,pre,a[href],img[src|alt]');
    $config->set('Attr.AllowedFrameTargets', []);
    $config->set('HTML.TargetBlank', true);
    $config->set('HTML.Nofollow', true);
    $config->set('AutoFormat.AutoParagraph', true);
    $config->set('AutoFormat.RemoveEmpty', true);
    $config->set('URI.AllowedSchemes', [
        'http' => true,
        'https' => true,
        'mailto' => true,
    ]);

    $html = (new HTMLPurifier($config))->purify($html);

    return remove_untrusted_inline_images($html);
}

function remove_untrusted_inline_images(string $html): string
{
    return (string)preg_replace_callback('/<img\b[^>]*\bsrc=("|\')([^"\']+)\1[^>]*>/i', static function (array $matches): string {
        return is_trusted_inline_image_src(html_entity_decode($matches[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ? $matches[0] : '';
    }, $html);
}

function is_trusted_inline_image_src(string $src): bool
{
    $src = trim($src);
    $root = rtrim(ROOT, '/');
    $path = '/tickets/attachment/';

    if (str_starts_with($src, $root . $path))
    {
        return ctype_digit(substr($src, strlen($root . $path)));
    }

    return str_starts_with($src, $path) && ctype_digit(substr($src, strlen($path)));
}

function rich_text_to_plain_text(string $html): string
{
    return trim(html_entity_decode(strip_tags(sanitize_rich_text($html)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

function render_rich_text(string $html): string
{
    return sanitize_rich_text($html);
}

function redirect($path)
{
    header("Location: " . ROOT . "/" . $path);
    die;
}

function csrf_token(): string
{
    if (empty($_SESSION['CSRF_TOKEN']))
    {
        $_SESSION['CSRF_TOKEN'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['CSRF_TOKEN'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . esc(csrf_token()) . '">';
}

function csrf_token_is_valid(?string $token): bool
{
    return !empty($_SESSION['CSRF_TOKEN'])
        && is_string($token)
        && hash_equals($_SESSION['CSRF_TOKEN'], $token);
}

function require_csrf(): void
{
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null))
    {
        http_response_code(419);
        die('Invalid form token');
    }
}

function current_user(): mixed
{
    return $_SESSION['USER'] ?? null;
}

function current_user_id(): ?int
{
    $user = current_user();

    if (!empty($user->id))
    {
        return (int)$user->id;
    }

    return null;
}

function current_user_role(): string
{
    $user = current_user();

    if (!empty($user->role))
    {
        return (string)$user->role;
    }

    return 'guest';
}

function has_role(array|string $roles, mixed $user = null): bool
{
    $roles = is_array($roles) ? $roles : [$roles];
    $user = $user ?? current_user();

    if (empty($user->role))
    {
        return false;
    }

    return in_array((string)$user->role, $roles, true);
}

function is_staff_or_admin(mixed $user = null): bool
{
    return has_role(['staff', 'admin'], $user);
}

function is_admin(mixed $user = null): bool
{
    return has_role('admin', $user);
}

function require_login(): void
{
    if (empty(current_user()))
    {
        message('Please sign in to continue.');
        redirect('login');
    }
}

function require_role(array|string $roles): void
{
    require_login();

    if (!has_role($roles))
    {
        http_response_code(403);
        die('Access denied');
    }
}

function can_access_ticket(mixed $ticket, mixed $user = null): bool
{
    $user = $user ?? current_user();

    if (empty($ticket) || empty($user) || empty($user->id))
    {
        return false;
    }

    if (is_staff_or_admin($user))
    {
        return true;
    }

    return isset($ticket->user_id) && (int)$ticket->user_id === (int)$user->id;
}

function require_ticket_access(mixed $ticket): void
{
    require_login();

    if (!can_access_ticket($ticket))
    {
        http_response_code(404);
        die('Not found');
    }
}

function is_final_active_admin(mixed $user, int $activeAdminCount): bool
{
    if (empty($user) || !is_admin($user))
    {
        return false;
    }

    if (isset($user->is_active) && (int)$user->is_active !== 1)
    {
        return false;
    }

    return $activeAdminCount <= 1;
}

function password_reset_required(mixed $user = null): bool
{
    $user = $user ?? current_user();

    return !empty($user) && (int)($user->must_reset_password ?? 0) === 1;
}

function can_change_local_password(mixed $user = null): bool
{
    $user = $user ?? current_user();

    return !empty($user) && ($user->auth_provider ?? 'local') === 'local';
}

function is_password_reset_allowed_route(string $controller): bool
{
    return in_array(strtolower($controller), ['password', 'logout'], true);
}

function require_password_reset_complete(string $controller): void
{
    if (password_reset_required() && !is_password_reset_allowed_route($controller))
    {
        message('You must reset your temporary password before continuing.');
        redirect('password');
    }
}

/** load image. if not exist, load placeholder **/
function get_image(mixed $file = '', string $type = 'post'): string
{

    $file = $file ?? '';
    if (file_exists($file))
    {
        return ROOT . "/" . $file;
    }

    if ($type == 'user')
    {
        return ROOT . "/assets/images/user.webp";
    }
    else
    {
        return ROOT . "/assets/images/no_image.png";
    }
}

/** returns pagination links **/
function get_pagination_vars(): array
{
    $vars = [];
    $vars['page']         = $_GET['page'] ?? 1;
    $vars['page']         = (int)$vars['page'];
    $vars['prev_page']     = $vars['page'] <= 1 ? 1 : $vars['page'] - 1;
    $vars['next_page']     = $vars['page'] + 1;

    return $vars;
}

/** Adds message to session to be displayed after redirect etc **/
function message(string $msg = null, bool $clear = false)
{
    $ses     = new Session();

    if (!empty($msg))
    {
        $ses->set('message', $msg);
    }
    else
	if (!empty($ses->get('message')))
    {

        $msg = $ses->get('message');

        if ($clear)
        {
            $ses->pop('message');
        }
        return $msg;
    }

    return false;
}

/** grab part of the URL, you know the first second or third section (0,1,2,3) **/
function URL($key): mixed
{
    $URL = $_GET['url'] ?? 'home';
    $URL = explode("/", trim($URL, "/"));

    switch ($key)
    {
        case 'page':
        case 0:
            return $URL[0] ?? null;
            break;
        case 'section':
        case 'slug':
        case 1:
            return $URL[1] ?? null;
            break;
        case 'action':
        case 2:
            return $URL[2] ?? null;
            break;
        case 'id':
        case 3:
            return $URL[3] ?? null;
            break;
        default:
            return null;
            break;
    }

    return $URL;
}


/** displays input values after a page refresh **/
function old_checked(string $key, string $value, string $default = ""): string
{

    if (isset($_POST[$key]))
    {
        if ($_POST[$key] == $value)
        {
            return ' checked ';
        }
    }
    else
    {

        if ($_SERVER['REQUEST_METHOD'] == "GET" && $default == $value)
        {
            return ' checked ';
        }
    }

    return '';
}


function old_value(string $key, mixed $default = "", string $mode = 'post'): mixed
{
    $POST = ($mode == 'post') ? $_POST : $_GET;
    if (isset($POST[$key]))
    {
        return $POST[$key];
    }

    return $default;
}

function old_select(string $key, mixed $value, mixed $default = "", string $mode = 'post'): mixed
{
    $POST = ($mode == 'post') ? $_POST : $_GET;
    if (isset($POST[$key]))
    {
        if ($POST[$key] == $value)
        {
            return " selected ";
        }
    }
    else

  if ($default == $value)
    {
        return " selected ";
    }

    return "";
}


/** returns a human readable date format **/
function get_date($date)
{
    return date("jS M, Y", strtotime($date));
}



/** converts image paths from relative to absolute **/
function add_root_to_images($contents)
{

    preg_match_all('/<img[^>]+>/', $contents, $matches);
    if (is_array($matches) && count($matches) > 0)
    {

        foreach ($matches[0] as $match)
        {

            preg_match('/src="[^"]+/', $match, $matches2);
            if (!strstr($matches2[0], 'http'))
            {

                $contents = str_replace($matches2[0], 'src="' . ROOT . '/' . str_replace('src="', "", $matches2[0]), $contents);
            }
        }
    }

    return $contents;
}

/** converts images from text editor content to actual files **/
function remove_images_from_content($content, $folder = "uploads/")
{

    if (!file_exists($folder))
    {
        mkdir($folder, 0744, true);
        file_put_contents($folder . "index.php", "Access Denied!");
    }

    //remove images from content
    preg_match_all('/<img[^>]+>/', $content, $matches);
    $new_content = $content;

    if (is_array($matches) && count($matches) > 0)
    {

        $image_class = new Image();
        foreach ($matches[0] as $match)
        {

            if (strstr($match, "http"))
            {
                //ignore images with links already
                continue;
            }

            // get the src
            preg_match('/src="[^"]+/', $match, $matches2);

            // get the filename
            preg_match('/data-filename="[^\"]+/', $match, $matches3);

            if (strstr($matches2[0], 'data:'))
            {

                $parts = explode(",", $matches2[0]);
                $basename = $matches3[0] ?? 'basename.jpg';
                $basename = str_replace('data-filename="', "", $basename);

                $filename = $folder . "img_" . sha1(rand(0, 9999999999)) . $basename;

                $new_content = str_replace($parts[0] . "," . $parts[1], 'src="' . $filename, $new_content);
                file_put_contents($filename, base64_decode($parts[1]));

                //resize image
                $image_class->resize($filename, 1000);
            }
        }
    }

    return $new_content;
}

/** deletes images from text editor content after making an edit to a page **/
function delete_images_from_content(string $content, string $content_new = ''): void
{

    //delete images from content
    if (empty($content_new))
    {

        preg_match_all('/<img[^>]+>/', $content, $matches);

        if (is_array($matches) && count($matches) > 0)
        {
            foreach ($matches[0] as $match)
            {

                preg_match('/src="[^"]+/', $match, $matches2);
                $matches2[0] = str_replace('src="', "", $matches2[0]);

                if (file_exists($matches2[0]))
                {
                    unlink($matches2[0]);
                }
            }
        }
    }
    else
    {

        //compare old to new and delete from old what inst in the new
        preg_match_all('/<img[^>]+>/', $content, $matches);
        preg_match_all('/<img[^>]+>/', $content_new, $matches_new);

        $old_images = [];
        $new_images = [];

        /** collect old images **/
        if (is_array($matches) && count($matches) > 0)
        {
            foreach ($matches[0] as $match)
            {

                preg_match('/src="[^"]+/', $match, $matches2);
                $matches2[0] = str_replace('src="', "", $matches2[0]);

                if (file_exists($matches2[0]))
                {
                    $old_images[] = $matches2[0];
                }
            }
        }

        /** collect new images **/
        if (is_array($matches_new) && count($matches_new) > 0)
        {
            foreach ($matches_new[0] as $match)
            {

                preg_match('/src="[^"]+/', $match, $matches2);
                $matches2[0] = str_replace('src="', "", $matches2[0]);

                if (file_exists($matches2[0]))
                {
                    $new_images[] = $matches2[0];
                }
            }
        }


        /** compare and delete all that dont appear in the new array **/
        foreach ($old_images as $img)
        {

            if (!in_array($img, $new_images))
            {

                if (file_exists($img))
                {
                    unlink($img);
                }
            }
        }
    }
}
