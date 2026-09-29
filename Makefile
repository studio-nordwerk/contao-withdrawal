.PHONY: up down reset check e2e

up:
	docker compose up -d --build
	docker compose exec -T php composer install --no-interaction
	docker compose exec -T php php bin/console contao:migrate --no-interaction --no-backup
	./scripts/seed.sh
	vp install
	vp exec playwright install chromium

down:
	docker compose down

reset:
	docker compose down
	docker compose up -d --build
	docker compose exec -T php php vendor/bin/contao-setup --no-interaction
	docker compose exec -T php php bin/console contao:migrate --no-interaction --no-backup
	./scripts/seed.sh

check:
	docker compose exec -T php vendor/bin/ecs check /workspace/src /workspace/tests /workspace/Resources/contao /workspace/scripts --config=/workspace/ecs.php --no-progress-bar
	docker compose exec -T php vendor/bin/twig-cs-fixer lint /workspace/templates /workspace/Resources/contao/templates
	docker compose exec -T php composer validate --strict /workspace/composer.json
	docker compose exec -T php composer normalize --dry-run /workspace/composer.json
	docker compose exec -T php php bin/console lint:twig /workspace/templates /workspace/Resources/contao/templates
	docker compose exec -T php php bin/console lint:yaml /workspace/config /workspace/app/config /workspace/translations
	docker compose exec -T php php bin/console lint:container
	docker compose exec -T php vendor/bin/phpstan analyse --configuration=/workspace/phpstan.neon.dist
	docker compose exec -T php vendor/bin/phpunit --configuration=/workspace/phpunit.xml.dist
	$(MAKE) e2e

e2e:
	vp check
	vp exec playwright test
