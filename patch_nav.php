<?php
$file = '/var/www/trusteps-cms-lab/resources/views/layouts/app.blade.php';
$content = file_get_contents($file);

$old = "                <a class=\"nav-link {{ request()->routeIs('discovery.*') ? 'active' : '' }}\" href=\"{{ route('discovery.lab') }}\">
                    候補収集ラボ
                </a>
                <a class=\"nav-link {{ request()->routeIs('directory-sources.index') || request()->routeIs('directory-sources.show') ? 'active' : '' }}\" href=\"{{ route('directory-sources.index') }}\">
                    名簿元管理
                </a>
                <a class=\"nav-link {{ request()->routeIs('directory-sources.lab') || request()->routeIs('directory-sources.lab.*') ? 'active' : '' }}\" href=\"{{ route('directory-sources.lab') }}\">
                    名簿元収集
                </a>
                <a class=\"nav-link {{ request()->routeIs('directory-sources.shokokai-bulk-html*') ? 'active' : '' }}\" href=\"{{ route('directory-sources.shokokai-bulk-html') }}\">
                    商工会HTML取込
                </a>
                <a class=\"nav-link {{ request()->routeIs('directory-sources.shokokai-web-search*') ? 'active' : '' }}\" href=\"{{ route('directory-sources.shokokai-web-search') }}\">
                    商工会WEBサーチ
                </a>
                <a class=\"nav-link {{ request()->routeIs('resolver.official-sites.*') ? 'active' : '' }}\" href=\"{{ route('resolver.official-sites.index') }}\">
                    公式HP取得
                </a>";

$new = "                <a class=\"nav-link {{ request()->routeIs('bizmaps.*') ? 'active' : '' }}\" href=\"{{ route('bizmaps.import') }}\">
                    BIZMAPSインポート
                </a>";

if (strpos($content, $old) === false) {
    echo "ERROR: Pattern not found.\n";
    exit(1);
}

$result = str_replace($old, $new, $content);
file_put_contents($file, $result);
echo "OK\n";
