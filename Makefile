# .DEFAULT_GOAL := local
# .PHONY: tests
SHELL := /bin/sh

# General Commands
help:
	cat Makefile

run:
	sh run.sh run $1

docker: up

up:
	sh run.sh up

down:
	sh run.sh down

exec:
	sh run.sh exec

remove:
	sh run.sh remove

	