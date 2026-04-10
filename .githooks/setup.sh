#!/bin/sh
# A executer une seule fois apres avoir clone le repo.
# Configure git pour utiliser les hooks partages du projet.

git config core.hooksPath .githooks
echo "Hooks git configures. La branche 'main' est protegee contre les pushs directs."
