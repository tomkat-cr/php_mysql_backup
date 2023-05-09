# .DEFAULT_GOAL := local
# .PHONY: tests
SHELL := /bin/sh

# General Commands
help:
	cat Makefile

run:
	sh run.sh

docker:
	docker-compose up

down:
	docker-compose down
