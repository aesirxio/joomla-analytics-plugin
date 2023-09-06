# yarn init
init:
	docker-compose run --rm php-npm yarn install

# Build package
build:
	docker-compose run --rm php-npm yarn build

# Watch js changes when developing
watch:
	docker-compose run --rm php-npm yarn watch

# Just open console the container
sh:
	docker-compose run --rm php-npm sh

# Down all
down:
	docker-compose down --remove-orphans

# Rebuild Dockerfile
docker-build:
	docker-compose build
