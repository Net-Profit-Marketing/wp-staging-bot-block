"""Disposable real-WordPress HTTP smoke test using the official SQLite test plugin.

Requires Python 3.9+ and PHP with PDO SQLite 3.37+, mbstring, and OpenSSL. Uses only an
ignored .cache directory, a loopback ephemeral port, synthetic users/data, and
the exact supplied plugin ZIP. No existing WordPress configuration is loaded.
This supplements the required MySQL/MariaDB and browser release checklist.
"""
import argparse
import hashlib
import http.client
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.parse
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[1]


def download(url, destination, expected_sha256=None):
    if not destination.exists():
        with urllib.request.urlopen(url, timeout=60) as remote:
            destination.write_bytes(remote.read())
    if expected_sha256 and hashlib.sha256(destination.read_bytes()).hexdigest() != expected_sha256:
        raise RuntimeError("Dependency checksum mismatch: " + destination.name)
    return destination


def extract(archive, destination):
    with zipfile.ZipFile(archive) as source:
        for item in source.infolist():
            target = (destination / item.filename).resolve()
            if not target.is_relative_to(destination.resolve()):
                raise RuntimeError("Unsafe archive path")
        source.extractall(destination)


def main():
    if not __debug__:
        raise RuntimeError("Run without Python -O; assertions are required")
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", required=True)
    parser.add_argument("--version", default="7.1.1", choices=["6.8", "7.1.1"])
    parser.add_argument("--zip", default="dist/staging-bot-block-1.1.0.zip")
    parser.add_argument("--plugin-check", action="store_true", help="Also run official Plugin Check 2.1.0, including runtime checks")
    args = parser.parse_args()
    php = str(Path(args.php).resolve())
    artifact = Path(args.zip).resolve()
    cache = ROOT / ".cache" / "wordpress-tests"
    cache.mkdir(parents=True, exist_ok=True)
    version = args.version
    core_url = f"https://wordpress.org/wordpress-{version}.zip"
    core = download(core_url, cache / f"wordpress-{version}.zip")
    expected = urllib.request.urlopen(core_url + ".md5", timeout=60).read().decode().strip()
    if hashlib.md5(core.read_bytes()).hexdigest() != expected:
        raise RuntimeError("WordPress archive checksum mismatch")
    sqlite = download("https://downloads.wordpress.org/plugin/sqlite-database-integration.3.0.2.zip", cache / "sqlite-3.0.2.zip", "1602e75577ad9b3a7e3e4a6a44a81b9541cdee2124d48928faf61c6fd3cd4f74")
    cli = download("https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar", cache / "wp-cli.phar", "ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c")
    env = os.environ.copy()
    env["WP_CLI_CONFIG_PATH"] = str(cache / "empty-config.yml")
    env["WP_CLI_CACHE_DIR"] = str(cache / "cli-cache")
    Path(env["WP_CLI_CONFIG_PATH"]).write_text("", encoding="utf-8")
    env["SBB_TEST_PASSWORD"] = secrets.token_urlsafe(30)
    env["SBB_DISPOSABLE_TESTS"] = "1"
    env["PATH"] = str(Path(php).parent) + os.pathsep + env.get("PATH", "")
    checks = []
    loaded_extensions = json.loads(subprocess.check_output([php, "-r", "echo json_encode(get_loaded_extensions());"], text=True))
    php_args = [php]
    if "pdo_sqlite" not in loaded_extensions:
        php_args += ["-d", "extension=pdo_sqlite"]
    sqlite_version = subprocess.check_output(php_args + ["-r", 'echo (new PDO("sqlite::memory:"))->query("select sqlite_version()")->fetchColumn();'], text=True).strip()
    if tuple(map(int, sqlite_version.split("."))) < (3, 37, 0):
        raise RuntimeError("SQLite 3.37+ is required by the disposable integration fixture; this PHP bundles " + sqlite_version)
    with tempfile.TemporaryDirectory(prefix="run-", dir=cache) as run_dir:
        run = Path(run_dir)
        extract(core, run)
        site = run / "wordpress"
        plugins = site / "wp-content" / "plugins"
        extract(sqlite, plugins)
        extract(artifact, plugins)
        if args.plugin_check:
            checker = download("https://downloads.wordpress.org/plugin/plugin-check.2.1.0.zip", cache / "plugin-check-2.1.0.zip", "6ff4bd2145f3befcf907df158cc466b1649dafed5686de8369907403c3013fc4")
            extract(checker, plugins)
        shutil.copyfile(plugins / "sqlite-database-integration" / "db.copy", site / "wp-content" / "db.php")
        must_use = site / "wp-content" / "mu-plugins"
        must_use.mkdir(exist_ok=True)
        # Installation/admin visits trigger core update checks even with cron off.
        # Stub only those unrelated services so a deliberately offline fixture
        # does not emit core network warnings. Other outbound HTTP stays blocked.
        (must_use / "offline-updates.php").write_text("""<?php
add_filter('pre_http_request', static function ($pre, $args, $request_url) {
    if (preg_match('#^https?://api\\.wordpress\\.org/(core/version-check|plugins/update-check|themes/update-check)/#', $request_url)) {
        return array('headers' => array(), 'body' => '{"offers":[],"plugins":[],"themes":[],"no_update":[],"translations":[]}', 'response' => array('code' => 200, 'message' => 'OK'), 'cookies' => array(), 'filename' => null);
    }
    return $pre;
}, 10, 3);
""", encoding="utf-8")
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        url = f"http://127.0.0.1:{port}"
        config = """<?php
define('DB_NAME', 'staging_bot_block_test');
define('DB_USER', '');
define('DB_PASSWORD', '');
define('DB_HOST', 'unused-sqlite-test');
define('DB_ENGINE', 'sqlite');
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', true);
define('DISABLE_WP_CRON', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_HOME', 'TEST_URL');
define('WP_SITEURL', 'TEST_URL');
define('AUTH_KEY', 'TEST_SALT');
define('SECURE_AUTH_KEY', 'TEST_SALT');
define('LOGGED_IN_KEY', 'TEST_SALT');
define('NONCE_KEY', 'TEST_SALT');
$table_prefix = 'sbb_test_';
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
require_once ABSPATH . 'wp-settings.php';
"""
        (site / "wp-config.php").write_text(config.replace("TEST_URL", url).replace("TEST_SALT", secrets.token_hex(32)), encoding="utf-8")
        def wp(*command):
            result = subprocess.run(php_args + [str(cli), f"--path={site}", "--no-color", *command], env=env, text=True, capture_output=True)
            if result.returncode:
                diagnostics = site / "wp-content" / "debug.log"
                if diagnostics.exists():
                    shutil.copyfile(diagnostics, cache / f"debug-{version}.log")
                raise RuntimeError(f"WP-CLI {command[0]} failed: {result.stdout} {result.stderr}")
            if result.stderr.strip():
                raise RuntimeError(f"WP-CLI diagnostics: {result.stderr}")
            return result.stdout.strip()
        wp("core", "install", f"--url={url}", "--title=Disposable staging plugin test", "--admin_user=sbb_test_admin", f"--admin_password={env['SBB_TEST_PASSWORD']}", "--admin_email=admin@example.test", "--skip-email")
        wp("plugin", "activate", "staging-bot-block")
        assert wp("core", "version") == version
        defaults_raw = wp("option", "get", "staging_bot_block_options", "--format=json")
        if not defaults_raw.startswith("{"):
            raise RuntimeError(f"Unexpected option output: {defaults_raw!r}")
        defaults = json.loads(defaults_raw)
        assert defaults["enabled"] == 0 and defaults["redirect_type"] == 302
        checks.append("ZIP install, activation, disabled defaults")
        print(wp("eval-file", str(ROOT / "tests" / "wp-integration.php")), flush=True)
        checks.append("Real WordPress API regression suite")
        wp("user", "create", "sbb_test_subscriber", "subscriber@example.test", "--role=subscriber", f"--user_pass={env['SBB_TEST_PASSWORD']}")
        router = run / "router.php"
        router.write_text("""<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file(__DIR__ . '/wordpress' . $path)) { return false; }
require __DIR__ . '/wordpress/index.php';
""", encoding="utf-8")
        log = open(run / "server.log", "w", encoding="utf-8")
        server = subprocess.Popen(php_args + ["-S", f"127.0.0.1:{port}", "-t", str(site), str(router)], stdout=log, stderr=log, env=env)
        def response(path="/", ua="Mozilla/5.0", method="GET", cookie=None):
            connection = http.client.HTTPConnection("127.0.0.1", port, timeout=15)
            headers = {"User-Agent": ua}
            if cookie:
                headers["Cookie"] = cookie
            connection.request(method, path, headers=headers)
            result = connection.getresponse()
            payload = result.read()
            result_headers = {k.lower(): v for k, v in result.getheaders()}
            connection.close()
            return result.status, result_headers, payload
        def set_options(**changes):
            options = dict(defaults, **changes)
            wp("option", "update", "staging_bot_block_options", json.dumps(options), "--format=json")
        def login(username):
            jar = http.cookiejar.CookieJar()
            opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
            opener.open(url + "/wp-login.php").read()
            body = urllib.parse.urlencode({"log": username, "pwd": env["SBB_TEST_PASSWORD"], "wp-submit": "Log In", "redirect_to": url + "/wp-admin/", "testcookie": "1"}).encode()
            opener.open(url + "/wp-login.php", body).read()
            assert any(c.name.startswith("wordpress_logged_in_") for c in jar)
            return "; ".join(f"{c.name}={c.value}" for c in jar), opener
        try:
            for attempt in range(40):
                if server.poll() is not None:
                    raise RuntimeError("PHP test server exited")
                try:
                    if response()[0] == 200:
                        break
                except OSError:
                    time.sleep(0.1)
            else:
                raise RuntimeError("Test server not ready")
            assert response(ua="Googlebot")[0] == 200
            acme_paths = ["/.well-known/acme-challenge/abc123", "/.well-known/acme-challenge/a_b-c",
                          "/.well-known/acme-challenge/abc123?anything=1"]
            invalid_acme_paths = ["/.well-known/acme-challenge/", "/.well-known/acme-challenge",
                                  "/foo/.well-known/acme-challenge/abc", "/.well-known/acme-challenge/?foo=bar"]
            acme_baselines = {path: response(path, "Googlebot") for path in acme_paths}
            assert all(baseline[0] < 500 and baseline[0] != 403 for baseline in acme_baselines.values())
            def check_acme(expected_status, ua="Googlebot"):
                for path, baseline in acme_baselines.items():
                    status, headers, _ = response(path, ua)
                    assert status == baseline[0], path
                    assert headers.get("location") == baseline[1].get("location"), path
                    assert headers.get("x-robots-tag") == baseline[1].get("x-robots-tag"), path
                for path in invalid_acme_paths:
                    status, headers, _ = response(path, ua)
                    assert status == expected_status and headers["x-robots-tag"] == "noindex, nofollow", path
            admin_cookie, admin_opener = login("sbb_test_admin")
            subscriber_cookie, _ = login("sbb_test_subscriber")
            checks.append("Real administrator and subscriber login")
            set_options(enabled=1, extra_user_agents="SyntheticCrawler")
            for ua in ["Googlebot", "SyntheticCrawler"]:
                status, headers, _ = response("/?encoded=%2Fpath&x=1", ua)
                assert status == 403 and headers["x-robots-tag"] == "noindex, nofollow"
                assert "no-cache" in headers.get("cache-control", "")
            assert response(ua="Googlebot", method="HEAD")[0] == 403
            for path in ["/?feed=rss2", "/?sitemap=index", "/?robots=1", "/?preview=true"]:
                assert response(path, "Googlebot")[0] == 403
            for ua in ["Mozilla/5.0", "", "UnknownRobot"]:
                assert response(ua=ua)[0] == 200
            assert response("/?test=/.well-known/acme-challenge/token", "Googlebot")[0] == 403
            check_acme(403)
            checks.append("Block/custom/unknown/empty UAs, GET/HEAD, feed/sitemap/robots/preview, headers/cache, strict ACME bypass")
            set_options(enabled=1, mode="redirect_bots", redirect_url="https://example.com/live?keep=1")
            status, headers, _ = response("/?original=2", "Googlebot")
            assert status == 302 and headers["location"] == "https://example.com/live?keep=1"
            assert headers["x-robots-tag"] == "noindex, nofollow" and "no-cache" in headers["cache-control"]
            assert response()[0] == 200
            check_acme(302)
            set_options(enabled=1, mode="redirect_bots", redirect_url="https://example.com/", redirect_type=301)
            assert response(ua="Googlebot", method="HEAD")[0] == 301
            checks.append("Exact external redirect, query handling, 301/302, HEAD, robots/cache headers")
            set_options(enabled=1, mode="redirect_all", redirect_url="https://example.com/")
            check_acme(302, "Mozilla/5.0")
            checks.append("Valid token/query ACME bypass and empty/nested path enforcement in all three modes")
            assert response()[0] == 302
            assert response(cookie=subscriber_cookie)[0] == 302
            assert response(cookie=admin_cookie)[0] == 200
            assert response("/wp-login.php")[0] == 200
            assert response("/wp-login.php?action=lostpassword")[0] == 200
            assert response("/wp-admin/", cookie=admin_cookie)[0] == 200
            assert response("/?rest_route=/wp/v2/types")[0] == 200
            assert response("/wp-admin/admin-ajax.php")[0] == 400
            assert response("/wp-admin/admin-post.php")[0] == 200
            assert response("/wp-cron.php")[0] == 200
            checks.append("Redirect-all anonymous/subscriber/admin, login/reset/admin/REST/AJAX/admin-post/cron")
            settings_path = "/wp-admin/admin.php?page=block-bot-redirect-setting-page"
            status, _, page = response(settings_path, cookie=admin_cookie)
            html = page.decode()
            assert status == 200 and "Protection active" in html and "302 Temporary" in html
            assert "bb-main.js" in html and "bb-main.css" in html
            assert "bb-main.js" not in response("/wp-admin/", cookie=admin_cookie)[2].decode()
            nonce = re.search(r'name="_wpnonce" value="([^"]+)"', html).group(1)
            form = {"option_page": "staging_bot_block_options_group", "action": "update", "_wpnonce": nonce, "_wp_http_referer": settings_path, "staging_bot_block_options[enabled]": "1", "staging_bot_block_options[mode]": "redirect_all", "staging_bot_block_options[redirect_url]": url + "/loop", "staging_bot_block_options[redirect_type]": "302"}
            invalid_nonce_form = dict(form, _wpnonce="invalid-test-nonce")
            try:
                admin_opener.open(url + "/wp-admin/options.php", urllib.parse.urlencode(invalid_nonce_form).encode())
                raise AssertionError("Invalid nonce accepted")
            except urllib.error.HTTPError as error:
                assert error.code == 403
            saved = admin_opener.open(url + "/wp-admin/options.php", urllib.parse.urlencode(form).encode()).read().decode()
            assert "valid" in saved.lower() and "notice-error" in saved
            assert response()[0] == 200 and response(ua="Googlebot")[0] == 403
            checks.append("Settings rendering/scoped assets, invalid nonce rejected, real nonce save, self-loop rejection and safe fallback")
            set_options()
            assert response(ua="Googlebot")[0] == 200
            wp("option", "add", "sbb_unrelated_test", "keep")
            wp("plugin", "deactivate", "staging-bot-block")
            assert response(ua="Googlebot")[0] == 200
            if args.plugin_check:
                wp("plugin", "activate", "plugin-check")
                # The CLI bootstrap is required before WordPress for runtime checks.
                checker_output = wp("plugin", "check", "staging-bot-block", "--format=strict-json", "--slug=staging-bot-block", "--require=" + str(plugins / "plugin-check" / "cli.php"))
                (cache / f"plugin-check-{version}.json").write_text(checker_output, encoding="utf-8")
                findings = [] if checker_output == "Success: Checks complete. No errors found." else json.loads(checker_output)
                if findings:
                    raise RuntimeError("Plugin Check findings: " + checker_output)
                checks.append("Official Plugin Check 2.1.0 including runtime checks")
                wp("plugin", "deactivate", "plugin-check")
            wp("plugin", "uninstall", "staging-bot-block")
            assert wp("option", "get", "sbb_unrelated_test") == "keep"
            owned = json.loads(wp("option", "list", "--search=staging_bot_block_*", "--format=json"))
            assert owned == []
            checks.append("Disable/deactivate/uninstall removes owned options and preserves unrelated data")
            debug = site / "wp-content" / "debug.log"
            if debug.exists() and debug.read_text(encoding="utf-8").strip():
                diagnostics = cache / f"debug-{version}.log"
                shutil.copyfile(debug, diagnostics)
                raise RuntimeError("WordPress debug.log contains diagnostics: " + str(diagnostics))
            checks.append("No WordPress debug warnings/notices/deprecations")
            result = {"wordpress": version, "php": subprocess.check_output([php, "-r", "echo PHP_VERSION;"], text=True), "database": "SQLite Database Integration 3.0.2 (not MySQL/MariaDB)", "zip_sha256": hashlib.sha256(artifact.read_bytes()).hexdigest(), "checks": checks}
            (cache / f"result-{version}.json").write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
            print(json.dumps(result, indent=2))
        finally:
            server.terminate()
            server.wait(timeout=15)
            log.close()
            debug = site / "wp-content" / "debug.log"
            (cache / f"debug-{version}.log").write_bytes(debug.read_bytes() if debug.exists() else b"")
            shutil.copyfile(run / "server.log", cache / f"server-{version}.log")


if __name__ == "__main__":
    main()
