#!/bin/bash -e

docker-compose exec php bin/console d:d:d --force --no-interaction --env codeception || echo "Error drop database"
docker-compose exec php bin/console d:d:c --no-interaction --env codeception
docker-compose exec php bin/console d:m:m --allow-no-migration --no-interaction --env codeception
docker-compose exec php bin/console d:f:l --no-interaction --env codeception
docker-compose exec php php vendor/bin/codecept run
