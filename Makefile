# .DEFAULT_GOAL := local
# .PHONY: tests
SHELL := /bin/sh

# General Commands
help:
	cat Makefile

run:
	sh run.sh

docker:
	cd scripts
	docker-compose up

down:
	cd scripts
	docker-compose down
	