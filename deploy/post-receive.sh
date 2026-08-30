#!/bin/bash
# Referência do hook instalado em ~/repo/ecodiffusore.git/hooks/post-receive no servidor.
# Faz checkout do código enviado por push diretamente na pasta do domínio.

TARGET="/home/u719183319/domains/ecodiffusorebrasil.com.br"
GIT_DIR="/home/u719183319/repo/ecodiffusore.git"

git --work-tree="$TARGET" --git-dir="$GIT_DIR" checkout -f main

echo "Deploy concluído em $(date)"
