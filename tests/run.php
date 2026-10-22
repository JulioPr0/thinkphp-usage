<?php
declare(strict_types=1);

use app\exception\ConflictException;
use app\exception\ValidationException;
use app\service\ArticleService;
use app\service\Migrator;
use app\support\ArticleFilters;
use app\support\Csv;
use app\support\Slug;
use app\validate\ArticleInput;
use think\facade\Db;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!extension_loaded('pdo_sqlite') || !function_exists('proc_open')) {
    fwrite(STDERR, "Tests require pdo_sqlite and proc_open.\n");
    exit(1);
}
$root = dirname(__DIR__);
$temporary = sys_get_temp_dir() . '/thinkpress-tests-' . bin2hex(random_bytes(6));
mkdir($temporary, 0700, true);
putenv('PHP_ENV_NAME=tests_' . bin2hex(random_bytes(6)));
putenv('PHP_DB_DRIVER=sqlite');
putenv('PHP_DB_DATABASE=' . $temporary . '/test.sqlite');
putenv('PHP_APP_DEBUG=false');
putenv('PHP_COOKIE_SECURE=false');
$app = new think\App($root . DIRECTORY_SEPARATOR);
$app->initialize();
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . "\n");
    exit(1);
});
$pdo = Db::connect()->connect();
$migrator = new Migrator();
$migrationDirectory = $root . '/database/migrations';
$migrator->run($pdo, $migrationDirectory);
$service = new ArticleService();
$passed = 0;
$failed = 0;

function check(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function test(string $name, callable $callback): void
{
    global $passed, $failed;
    try {
        $callback();
        $passed++;
        echo "PASS $name\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "FAIL $name: " . $exception->getMessage() . "\n";
    }
}
function rejects(callable $callback, string $class): Throwable
{
    try {
        $callback();
    } catch (Throwable $exception) {
        check($exception instanceof $class, 'Expected ' . $class . ', got ' . get_class($exception));
        return $exception;
    }
    throw new RuntimeException('Expected an exception.');
}
function payload(array $replace = []): array
{
    return array_merge(['title' => 'Contoh artikel', 'summary' => 'Ringkasan contoh', 'content' => 'Konten artikel yang cukup panjang untuk pengujian.', 'status' => 'draft', 'slug' => '', 'category_id' => '', 'published_at' => ''], $replace);
}
$filters = static fn (array $values = []) => ArticleFilters::fromArray($values);
$now = new DateTimeImmutable('2026-10-22 15:00:00', new DateTimeZone('Asia/Jakarta'));

test('Published without date uses the supplied clock', function () use ($now) {
    check(ArticleInput::validate(payload(['status' => 'published']), $now)['published_at'] === '2026-10-22 15:00:00');
});
test('Invalid calendar dates produce a field error', function () {
    $error = rejects(fn () => ArticleInput::validate(payload(['published_at' => '2026-02-30T10:00'])), ValidationException::class);
    check(isset($error->errors()['published_at']));
});
test('Nested values cannot become article strings', function () {
    rejects(fn () => ArticleInput::validate(payload(['title' => ['injected']])), ValidationException::class);
});
test('Unrecognized statuses are rejected', function () {
    rejects(fn () => ArticleInput::validate(payload(['status' => 'administrator'])), ValidationException::class);
});
test('Unicode title limits count characters', function () {
    ArticleInput::validate(payload(['title' => str_repeat('中', 150)]));
    rejects(fn () => ArticleInput::validate(payload(['title' => str_repeat('中', 151)])), ValidationException::class);
});
test('Content limit respects MySQL TEXT byte capacity', function () {
    rejects(fn () => ArticleInput::validate(payload(['content' => str_repeat('中', 20001)])), ValidationException::class);
});
test('Draft clears its publication date', function () {
    check(ArticleInput::validate(payload(['published_at' => '2027-10-22T10:00']))['published_at'] === null);
});
test('Only approved fields survive validation', function () {
    $data = ArticleInput::validate(payload(['id' => 999, 'deleted_at' => '2026-01-01', 'version' => 999]));
    check(!isset($data['id']) && !isset($data['deleted_at']) && !isset($data['version']));
});
test('Pagination and sorting use strict allowlists', function () use ($filters) {
    $data = $filters(['per_page' => 999999, 'sort' => 'title; DROP TABLE articles', 'page' => -2, 'q' => ['array']]);
    check($data['per_page'] === 10 && $data['sort'] === 'recent' && $data['page'] === 1 && $data['q'] === '');
});
test('Slug normalization handles punctuation and non-Latin fallback', function () {
    check(Slug::make('Hello, PHP 8!') === 'hello-php-8');
    check(Slug::make('中文文章') !== '');
});
test('CSV neutralizes formulas and leading whitespace', function () {
    foreach (['=SUM(1,1)', '+cmd', '-cmd', '@cmd', "\t=cmd", '  =cmd'] as $cell) {
        check(str_starts_with(Csv::cell($cell), "'"));
    }
    check(Csv::cell('Ordinary text') === 'Ordinary text');
});
test('Migrations are idempotent', function () use ($migrator, $pdo, $migrationDirectory) {
    check($migrator->run($pdo, $migrationDirectory) === []);
});
test('Migration preserves articles from the original schema', function () use ($migrator, $migrationDirectory) {
    $legacy = new PDO('sqlite::memory:');
    $legacy->exec('CREATE TABLE articles (id INTEGER PRIMARY KEY, title VARCHAR(150), summary VARCHAR(255), content TEXT, status VARCHAR(20), published_at DATETIME, created_at DATETIME, updated_at DATETIME)');
    $legacy->exec("INSERT INTO articles VALUES (1,'Existing article',NULL,'Original content','published',NULL,'2026-01-01 10:00:00','2026-01-01 10:00:00')");
    $migrator->run($legacy, $migrationDirectory);
    $row = $legacy->query('SELECT * FROM articles')->fetch(PDO::FETCH_ASSOC);
    check($row['content'] === 'Original content' && $row['slug'] !== '' && $row['published_at'] === '2026-01-01 10:00:00');
});
$firstId = $service->save(payload(['title' => 'Zebra', 'status' => 'published']));
$secondId = $service->save(payload(['title' => 'Alpha']));
$scheduledId = $service->save(payload(['title' => 'Future article', 'status' => 'published', 'published_at' => '2099-01-01T10:00']));
test('Generated slugs stay unique', function () use ($service) {
    $id = $service->save(payload(['title' => 'Zebra']));
    check($service->find($id)['slug'] === 'zebra-2');
});
test('Title sorting takes precedence over creation time', function () use ($service, $filters) {
    check($service->listing($filters(['sort' => 'title']))['rows'][0]['title'] === 'Alpha');
});
test('Search treats percent and underscore literally', function () use ($service, $filters) {
    $service->save(payload(['title' => '100%_coverage']));
    check($service->listing($filters(['q' => '%_']))['total'] === 1);
});
test('An unknown category cannot be saved', function () use ($service) {
    rejects(fn () => $service->save(payload(['category_id' => 9999])), ValidationException::class);
});
test('Duplicate explicit slugs return a validation error', function () use ($service) {
    rejects(fn () => $service->save(payload(['slug' => 'zebra'])), ValidationException::class);
});
test('Optimistic locking prevents lost updates', function () use ($service, $secondId) {
    $article = $service->find($secondId);
    $service->save(payload(['title' => 'Alpha updated', 'version' => $article['version']]), $secondId);
    rejects(fn () => $service->save(payload(['title' => 'Stale edit', 'version' => $article['version']]), $secondId), ConflictException::class);
    check($service->find($secondId)['title'] === 'Alpha updated');
});
test('Public queries hide draft and future articles', function () use ($service, $filters) {
    $rows = $service->listing($filters(['status' => 'draft', 'view' => 'trash']), true);
    check($rows['total'] === 1 && $rows['rows'][0]['title'] === 'Zebra');
});
test('Invalid bulk selections leave every article unchanged', function () use ($service, $firstId) {
    rejects(fn () => $service->bulk([$firstId, 99999], 'trash'), ConflictException::class);
    check($service->find($firstId)['deleted_at'] === null);
});
test('Trash removes visibility and restore creates a draft', function () use ($service, $filters, $firstId) {
    $service->bulk([$firstId], 'trash');
    check($service->listing($filters(), true)['total'] === 0);
    $service->bulk([$firstId], 'restore');
    $row = $service->find($firstId);
    check($row['status'] === 'draft' && $row['published_at'] === null && $row['deleted_at'] === null);
    $service->bulk([$firstId], 'publish');
});
test('Duplication always creates a new draft', function () use ($service, $firstId) {
    $copy = $service->find($service->duplicate($firstId));
    check($copy['status'] === 'draft' && $copy['published_at'] === null && $copy['slug'] !== 'zebra');
});


$categoryService = new \app\service\CategoryService();
test('Category renaming preserves its slug', function () use ($categoryService) {
    $categoryService->save('Operations');
    $category = Db::name('categories')->where('name', 'Operations')->find();
    $categoryService->save('IT Operations', (int) $category['id']);
    check(Db::name('categories')->where('id', $category['id'])->value('slug') === 'operations');
});
test('Used categories cannot be deleted, including trashed articles', function () use ($categoryService, $service) {
    $id = (int) Db::name('categories')->where('slug', 'operations')->value('id');
    $articleId = $service->save(payload(['category_id' => $id]));
    $service->bulk([$articleId], 'trash');
    rejects(fn () => $categoryService->delete($id), ValidationException::class);
    check(Db::name('categories')->where('id', $id)->count() === 1);
});
test('Unused categories can be removed', function () use ($categoryService) {
    $categoryService->save('Temporary');
    $id = (int) Db::name('categories')->where('name', 'Temporary')->value('id');
    $categoryService->delete($id);
    check(Db::name('categories')->where('id', $id)->count() === 0);
});
test('Archived articles preserve their publication history', function () {
    $data = ArticleInput::validate(payload(['status' => 'archived', 'published_at' => '2026-01-01T12:00']));
    check($data['published_at'] === '2026-01-01 12:00:00');
});

$password = 'test-password-12345';
Db::name('users')->insert(['name' => 'Test Admin', 'email' => 'admin@example.test', 'password' => password_hash($password, PASSWORD_DEFAULT), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
check($socket !== false, 'Cannot allocate test server port.');
$address = stream_socket_get_name($socket, false);
fclose($socket);
$log = $temporary . '/server.log';
$process = proc_open([PHP_BINARY, '-S', $address, '-t', $root . '/public', $root . '/public/router.php'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root);
check(is_resource($process), 'Cannot start HTTP test server.');
$base = 'http://' . $address;
for ($attempt = 0; $attempt < 50; $attempt++) {
    $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
    if ($probe) { fclose($probe); break; }
    usleep(100000);
}
$cookies = [];
function http(string $method, string $path, array $data = [], ?array &$jar = null): array
{
    global $base, $cookies;
    if ($jar === null) { $jar =& $cookies; }
    $headers = ['Content-Type: application/x-www-form-urlencoded'];
    if ($jar) { $headers[] = 'Cookie: ' . implode('; ', array_map(static fn ($key, $value) => $key . '=' . $value, array_keys($jar), $jar)); }
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $method === 'GET' ? '' : http_build_query($data), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $body = file_get_contents($base . $path, false, $context);
    $responseHeaders = array_map(static fn ($line) => preg_replace('/^([^:]+):\s*/', '$1: ', $line), $http_response_header ?? []);
    preg_match('~HTTP/\S+ (\d+)~', $responseHeaders[0] ?? '', $matches);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $match)) { $jar[$match[1]] = $match[2]; }
    }
    return ['status' => (int) ($matches[1] ?? 0), 'body' => (string) $body, 'headers' => implode("\n", $responseHeaders)];
}
function token(array $page): string
{
    preg_match('/name="_token" value="([a-f0-9]{64})"/', $page['body'], $match);
    check(isset($match[1]), 'CSRF token not found in page. HTTP ' . $page['status'] . ' ' . substr($page['body'], 0, 200));
    return $match[1];
}
try {
    test('Public blog renders and hides unpublished content', function () {
        $page = http('GET', '/blog');
        check($page['status'] === 200, 'HTTP ' . $page['status']);
        check(str_contains($page['body'], 'Zebra') && !str_contains($page['body'], 'Future article'));
    });
    test('All management routes require authentication', function () {
        foreach (['/dashboard', '/articles', '/articles/create', '/articles/export', '/categories', '/account'] as $path) {
            $page = http('GET', $path);
            check($page['status'] === 302 && str_contains($page['headers'], 'Location: /login'), $path . ' is not protected: HTTP ' . $page['status'] . ' ' . $page['headers']);
        }
    });
    test('Controller auto-routing cannot bypass middleware', function () {
        check(http('GET', '/article/index')['status'] === 404);
        check(http('GET', '/articles/1/trash')['status'] === 404);
    });
    $login = http('GET', '/login');
    $csrf = token($login);
    test('Session cookies are HttpOnly and SameSite', function () use ($login) {
        check(stripos($login['headers'], 'httponly') !== false && stripos($login['headers'], 'samesite=lax') !== false);
    });
    test('CSRF rejects forged POST requests', function () {
        check(http('POST', '/login', ['email' => 'admin@example.test', 'password' => 'test-password-12345'])['status'] === 419);
    });
    test('Incorrect login returns a generic validation error', function () use ($csrf) {
        $page = http('POST', '/login', ['_token' => $csrf, 'email' => 'admin@example.test', 'password' => 'incorrect']);
        check($page['status'] === 422 && str_contains($page['body'], 'Email atau password tidak sesuai.'));
    });
    $oldCookie = $cookies['thinkpress_session'] ?? '';
    $loggedIn = http('POST', '/login', ['_token' => $csrf, 'email' => 'admin@example.test', 'password' => $password]);
    test('Successful login rotates the session ID', function () use ($loggedIn, $oldCookie, &$cookies) {
        check($loggedIn['status'] === 302 && str_contains($loggedIn['headers'], '/dashboard'));
        check(($cookies['thinkpress_session'] ?? '') !== $oldCookie);
    });
    test('Every management template renders', function () {
        foreach (['/dashboard', '/articles', '/articles?view=trash', '/articles/create', '/articles/1', '/articles/1/edit', '/categories', '/account'] as $path) {
            $page = http('GET', $path);
            check($page['status'] === 200, $path . ': HTTP ' . $page['status']);
            check(!str_contains($page['body'], 'Warning:'), $path . ': PHP warning');
        }
    });
    $csrf = token(http('GET', '/articles/create'));
    test('Form validation preserves submitted values', function () use ($csrf) {
        $page = http('POST', '/articles', payload(['_token' => $csrf, 'title' => 'Preserved title', 'content' => 'short']));
        check($page['status'] === 422 && str_contains($page['body'], 'Preserved title'));
    });
    test('HTML from article content is escaped in every view', function () use ($csrf, $service) {
        $page = http('POST', '/articles', payload(['_token' => $csrf, 'title' => '<script>alert(1)</script>', 'summary' => '<img src=x onerror=alert(1)>', 'content' => '<script>alert(2)</script> sufficient content', 'status' => 'published']));
        check($page['status'] === 302);
        $row = Db::name('articles')->where('title', '<script>alert(1)</script>')->find();
        foreach (['/articles', '/articles/' . $row['id'], '/articles/' . $row['id'] . '/edit', '/read/' . $row['slug'], '/blog'] as $path) {
            $response = http('GET', $path);
            check($response['status'] === 200 && !str_contains($response['body'], '<script>alert(1)</script>') && str_contains($response['body'], '&lt;script&gt;'), $path . ': unsafe or missing escaped value');
        }
    });
    test('Stale edits return HTTP 409 and preserve the user input', function () use ($csrf, $service, $secondId) {
        $version = (int) $service->find($secondId)['version'];
        $service->save(payload(['title' => 'Latest version', 'version' => $version]), $secondId);
        $page = http('POST', '/articles/' . $secondId . '/update', payload(['_token' => $csrf, 'title' => 'Keep this unsaved text', 'version' => $version]));
        check($page['status'] === 409 && str_contains($page['body'], 'Keep this unsaved text'));
        check($service->find($secondId)['title'] === 'Latest version');
    });
    test('Category HTTP creation, renaming and deletion work', function () use ($csrf) {
        check(http('POST', '/categories', ['_token' => $csrf, 'name' => 'HTTP category'])['status'] === 302);
        $id = (int) Db::name('categories')->where('name', 'HTTP category')->value('id');
        check(http('POST', '/categories/' . $id . '/update', ['_token' => $csrf, 'name' => 'Renamed category'])['status'] === 302);
        check(Db::name('categories')->where('id', $id)->value('name') === 'Renamed category');
        check(http('POST', '/categories/' . $id . '/delete', ['_token' => $csrf])['status'] === 302);
        check(Db::name('categories')->where('id', $id)->count() === 0);
    });
    test('Scheduled and draft URLs return 404 publicly', function () use ($service, $scheduledId, $secondId) {
        foreach ([$scheduledId, $secondId] as $id) {
            check(http('GET', '/read/' . $service->find($id)['slug'])['status'] === 404);
        }
    });
    test('CSV export carries download headers and neutralizes formulas', function () use ($service) {
        $service->save(payload(['title' => '=SUM(1,1)']));
        $page = http('GET', '/articles/export');
        check($page['status'] === 200 && str_contains($page['headers'], 'text/csv') && str_contains($page['headers'], 'attachment;'));
        check(str_contains($page['body'], "'=SUM(1,1)"));
    });
    test('Security headers are present on management responses', function () {
        $headers = http('GET', '/dashboard')['headers'];
        check(stripos($headers, 'Content-Security-Policy:') !== false && stripos($headers, 'X-Content-Type-Options: nosniff') !== false && stripos($headers, 'no-store') !== false);
    });
    test('Rate limit survives separate browser sessions', function () {
        $jar = [];
        $csrf = token(http('GET', '/login', [], $jar));
        for ($i = 0; $i < 6; $i++) {
            $page = http('POST', '/login', ['_token' => $csrf, 'email' => 'missing@example.test', 'password' => 'incorrect'], $jar);
        }
        check(str_contains($page['body'], 'Terlalu banyak percobaan.'));
        $secondJar = [];
        $secondToken = token(http('GET', '/login', [], $secondJar));
        $page = http('POST', '/login', ['_token' => $secondToken, 'email' => 'missing@example.test', 'password' => 'incorrect'], $secondJar);
        check(str_contains($page['body'], 'Terlalu banyak percobaan.'));
    });
    test('Changing password invalidates the current session', function () use ($csrf) {
        $page = http('POST', '/account/password', ['_token' => $csrf, 'current_password' => 'test-password-12345', 'password' => 'updated-password-12345', 'password_confirmation' => 'updated-password-12345']);
        check($page['status'] === 302);
        check(http('GET', '/dashboard')['status'] === 302);
        $user = Db::name('users')->where('email', 'admin@example.test')->find();
        check(password_verify('updated-password-12345', $user['password']) && (int) $user['session_version'] === 2);
    });
} finally {
    proc_terminate($process);
    proc_close($process);
}
if ($failed > 0) {
    echo "\nHTTP log: $log\n";
    echo substr((string) file_get_contents($log), -4000) . "\n";
} else {
    foreach (glob($temporary . '/*') ?: [] as $path) { if (is_file($path)) { unlink($path); } }
    rmdir($temporary);
}
echo "\nResults: $passed passed, $failed failed.\n";
exit($failed > 0 ? 1 : 0);
