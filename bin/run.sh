#!/usr/bin/env bash

export USER_UID=$(id -u)
export USER_GID=$(id -g www-data)

set -e



COMPOSE="docker compose --env-file .env -f docker/docker-compose.yml"

case "${1:-start}" in
    start)
        #chmod -R ug+rwX app/backend/storage app/backend/bootstrap/cache
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
