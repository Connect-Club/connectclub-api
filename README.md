Login registry:
```
docker login registry.gitlab.com
```

Run ConnectClub API for developer:
```
cp key.json.example key.json
cp .env.local.example .env.local
docker-compose up -d
docker-compose exec php composer install
docker-compose exec php chown -R www-data:www-data var/*
docker-compose exec php bin/console doctrine:migrations:migrate --allow-no-migration
docker-compose exec php bin/console doctrine:fixtures:load
```

Load geo locations dump:
```
source .env.local
docker-compose exec -T db psql $DATABASE_URL < mixmind.sql
```

Profiling \ Debug:
```
# If your docker network != '172.17.0.1'
DOCKER_XDEBUG_HOST=$(docker network inspect bridge |awk '/"Gateway"/ {gsub ("\"","") ;print $2}')
# Default values
DOCKER_XDEBUG_PORT=10000
DOCKER_XDEBUG_IDE_KEY=PHPSTORM
XHPROF_ENABLE=1
```

Run tests && Checks:
```
docker-compose exec php vendor/bin/phpstan analyse -l 5 -c phpstan.neon src/
docker-compose exec php bin/console lint:container
docker-compose exec php bin/console lint:yaml config/
docker-compose exec php vendor/sensiolabs/security-checker/security-checker security:check
docker-compose exec php vendor/bin/phpcs --ignore=Migrations/* src/ codeceptionTests/ -p
docker-compose exec php php vendor/bin/codecept run
```
## Gitlab deploy

helm values precedence:
|__source__|
|---|
|[values.yaml](helm/api/values.yaml)|
|[values-&lt;environment name&gt;.yaml](helm/api/values-prod-env.yaml)|
|gitlab &lt;ENV&gt;_ENV variable|
|$EXTRA_ARGS env|

