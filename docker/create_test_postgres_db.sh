#!/bin/bash
set -e

psql -v ON_ERROR_STOP=1 -U "${POSTGRES_USER:=postgres}" <<-EOSQL
    CREATE DATABASE $POSTGRES_TEST_DB;
EOSQL