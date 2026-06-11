<?php
$file = '/var/www/trusteps-cms-lab/deploy.sh';
$content = file_get_contents($file);

$old = 'MIGRATION_STATUS="success"
CURRENT_STEP="rebuild_cache"';

$new = 'MIGRATION_STATUS="success"

CURRENT_STEP="post_deploy_hook"
echo "[15.5/17] Run post_deploy hook if exists"
if [ -f "$APP_DIR/post_deploy.php" ]; then
  echo "Found post_deploy.php. Running..."
  php "$APP_DIR/post_deploy.php"
  rm -f "$APP_DIR/post_deploy.php"
  echo "post_deploy.php completed and removed."
else
  echo "No post_deploy.php found. Skipping."
fi

CURRENT_STEP="rebuild_cache"';

if (strpos($content, $old) === false) {
    echo "ERROR: Pattern not found.\n";
    exit(1);
}

if (strpos($content, 'post_deploy_hook') !== false) {
    echo "ALREADY_EXISTS\n";
    exit(0);
}

$result = str_replace($old, $new, $content);
file_put_contents($file, $result);
echo "OK\n";
