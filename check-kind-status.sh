#!/usr/bin/env sh
# Script simplifié pour tester que tout fonctionne

echo "=== Vérification du PoC Kind pour LPMDE ==="
echo ""

echo "[1] Vérification des pods Kubernetes..."
kubectl get pods -n lpmde-sandbox --no-headers

echo ""
echo "[2] Vérification des ports en écoute..."
netstat -an 2>/dev/null | grep -E "5672|15672|8080" | grep LISTEN || echo "Ports pas encore en écoute"

echo ""
echo "[3] Vérification du conteneur Docker..."
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null | grep -E "lpmde|CONTAINER"

echo ""
echo "[4] Vérification de Symfony..."
docker exec lpmde-web-kind sh -c "php bin/console about 2>&1 | head -3" 2>/dev/null || echo "Symfony pas accessible"

echo ""
echo "[5] Vérification de la connexion RabbitMQ..."
docker exec lpmde-web-kind sh -c "php bin/console messenger:stats 2>&1" 2>/dev/null || echo "RabbitMQ pas accessible"

echo ""
echo "=== Résumé ==="
echo "✓ Cluster Kind: $(kubectl get nodes -n lpmde-sandbox 2>/dev/null | grep -c Ready || echo '❌')"
echo "✓ Docker container: $(docker ps --format '{{.Names}}' 2>/dev/null | grep -c lpmde-web-kind || echo '❌')"
echo "✓ Accédez à : http://localhost:8000"
