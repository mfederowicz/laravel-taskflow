#!/usr/bin/env bash

export USER_UID=$(id -u)
export USER_GID=$(id -g www-data 2>/dev/null || id -g)

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "Created .env from .env.example."
        echo "Set application keys before running the stack:"
        echo "  ./bin/artisan key:generate   # APP_KEY"
        echo "  ./bin/artisan jwt:secret     # JWT_SECRET"
        echo "  ./bin/artisan passport:keys  # Passport OAuth keys"
    else
        echo "Error: missing .env (and no .env.example to copy). Cannot run the stack." >&2
        exit 1
    fi
fi

set -e



COMPOSE="docker compose --env-file .env -f docker/docker-compose.yml"

case "${1:-start}" in
    start)
        #chmod -R ug+rwX backend/storage backend/bootstrap/cache
        $COMPOSE up -d
        ;;

    build)
        $COMPOSE up -d --build
        ;;

    stop)
        $COMPOSE down
        ;;

    restart)
        $COMPOSE restart
        ;;

    logs)
        $COMPOSE logs -f
        ;;

    status)
        $COMPOSE ps
        ;;

    *)
        echo "Usage: ./bin/run.sh [start|build|stop|restart|logs|status]"
        exit 1
        ;;
esac
