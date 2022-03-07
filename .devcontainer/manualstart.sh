docker network create localhost
docker-compose up -f docker-compose.yml docker-compose.linux.yml
docker-compose exec web bash httpdocs/.devcontainer/postCreateCommand.sh