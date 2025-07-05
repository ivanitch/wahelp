include .env

build:
	docker compose up -d --build

up:
	docker compose up -d

stop:
	docker compose stop

down:
	docker compose down -v

restart:
	docker compose restart

reload:
	docker compose down -v
	docker compose up -d

ps:
	docker compose ps

logs:
	docker compose logs -f

app:
	docker exec -it $(APP_NAME)_php-fpm /bin/bash

users:
	curl -X POST -F "csv_file=@$(USERS_FILE_CSV)" $(UPLOAD_FILE_ENDPOINT)