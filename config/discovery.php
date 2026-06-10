<?php

return [
    'manual_url_limit' => 500,
    'high_fanout_threshold' => 5,
    'directory_link_limit' => 200,
    'directory_timeout' => 10,
    'directory_connect_timeout' => 5,
    'directory_user_agent' => 'TRUSTEPS-CMS-Lab-DiscoveryBot/0.18.3.1',
    'directory_detail_page_limit' => 20,
    'directory_detail_page_hard_limit' => 30,


    'directory_detail_positive_keywords' => [
        '株式会社', '有限会社', '合同会社', '工務店', '建設', '塗装', '設備', '電気', '商店', '会社',
        '事務所', '製作所', '製造', '介護', '福祉', '医院', 'クリニック', '旅館', 'ホテル', '農園',
        'member', 'members', 'shop', 'shops', 'company', 'office', 'detail', 'business', 'member-detail', 'kaiin', 'kamei',
    ],

    'directory_exclude_path_keywords' => [
        '/privacy', '/policy', '/contact', '/inquiry', '/login', '/logout', '/sitemap', '/site-map', '/news', '/blog',
        '/category', '/tag', '/wp-content', '/wp-admin', '/feed', '/rss', '/assets', '/css', '/js', '/images', '/image',
        '/about', '/access', '/recruit', '/entry', '/event', '/calendar', '/download', '/manual', '/search',
    ],

    'directory_exclude_text_keywords' => [
        'home', 'top', 'トップ', 'ホーム', 'お問い合わせ', '問合せ', 'プライバシー', '個人情報', 'サイトマップ',
        'アクセス', 'ログイン', '新着', 'お知らせ', 'もっと見る', '詳しくはこちら', '詳細はこちら', '詳細',
        '一覧', '戻る', '次へ', '前へ', '地図', 'map', 'facebook', 'instagram', 'twitter', 'x', 'youtube',
    ],

    'portal_domains' => [
        'tabelog.com',
        'hotpepper.jp',
        'beauty.hotpepper.jp',
        'suumo.jp',
        'homes.co.jp',
        'athome.co.jp',
        'carsensor.net',
        'goo-net.com',
        'jalan.net',
        'travel.rakuten.co.jp',
        'ekiten.jp',
        'navitime.co.jp',
        'mapion.co.jp',
        'itp.ne.jp',
        'townpage.goo.ne.jp',
        'iタウンページ.jp',
    ],

    'sns_domains' => [
        'instagram.com',
        'facebook.com',
        'twitter.com',
        'x.com',
        'youtube.com',
        'youtu.be',
        'tiktok.com',
        'line.me',
        'lin.ee',
        'note.com',
        'ameblo.jp',
    ],

    'builder_domains' => [
        'wixsite.com',
        'jimdo.com',
        'jimdosite.com',
        'jimdofree.com',
        'peraichi.com',
        'amebaownd.com',
        'ownd.site',
        'studio.site',
        'strikingly.com',
        'webnode.jp',
    ],

    'ec_domains' => [
        'base.shop',
        'thebase.in',
        'stores.jp',
        'stores.tokyo',
        'shopify.com',
        'myshopify.com',
        'rakuten.co.jp',
        'yahoo.co.jp',
        'shopping.yahoo.co.jp',
        'amazon.co.jp',
        'minne.com',
        'creema.jp',
    ],

    'map_domains' => [
        'maps.google.com',
        'google.com',
        'google.co.jp',
        'maps.app.goo.gl',
        'goo.gl',
    ],
];
