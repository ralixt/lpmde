<?php
header("Content-Type: text/plain");
echo "getenv KEYCLOAK_URL: " . var_export(getenv("KEYCLOAK_URL"), true) . "\n";
echo "getenv KEYCLOAK_REALM: " . var_export(getenv("KEYCLOAK_REALM"), true) . "\n";
echo "_SERVER KEYCLOAK_URL: " . var_export($_SERVER["KEYCLOAK_URL"] ?? 'NOT_SET', true) . "\n";
