docker build --tag c2spark:latest . > /dev/null
docker run c2spark c-to-json.py "$1"