#!/bin/sh
set -eu
project_dir="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$project_dir"
docker info >/dev/null
docker run --rm --mount "type=bind,source=$project_dir,target=/app" -w /app php:8.3-cli-bookworm php -r '
$path=".env";
if (!file_exists($path)) copy(".env.example",$path);
$contents=file_get_contents($path);
if (!preg_match("/^APP_KEY=.+$/m",$contents)) {
    $key="APP_KEY=base64:".base64_encode(random_bytes(32));
    $contents=preg_match("/^APP_KEY=/m",$contents)?preg_replace("/^APP_KEY=.*$/m",$key,$contents):$contents."\n".$key."\n";
    file_put_contents($path,$contents);
}
chmod($path,0600);
echo "Configuración local lista; la clave no se muestra.\n";
'
docker compose up --build -d --wait
docker compose exec -T app php artisan db:seed
printf '%s\n' 'Aplicación local: http://127.0.0.1:8000'
