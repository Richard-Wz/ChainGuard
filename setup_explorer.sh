#!/usr/bin/env bash
set -euo pipefail

EXPLORER_PATH="$HOME/go/src/github/Kae/explorer"
echo "🔄 Starting Hyperledger Explorer setup..."
echo "🔁 Removing existing crypto material from $EXPLORER_PATH"
sudo rm -rf ./organizations

echo "🔁 Copying crypto material from $EXPLORER_PATH"
if [[ ! -d "$EXPLORER_PATH" ]]; then
  echo "❌ Explorer path not found: $EXPLORER_PATH"
  exit 1
fi

sudo cp -r ../fabric-samples/test-network/organizations .
echo "✅ Copied crypto material to $EXPLORER_PATH"

sudo chown -R $(id -u):$(id -g) organizations

# ——— CONFIGURATION ———
FABRIC_ORG_PATH="./organizations"
ORG_NAME="org1.example.com"
USER_NAME="Admin@org1.example.com"  # Changed to Admin
PROFILE_PATH="./connection-profile/test-network.json"

# ——— Locate private key filename ———
KEY_FULL_PATH=$(find "$FABRIC_ORG_PATH/peerOrganizations/$ORG_NAME/users/$USER_NAME/msp/keystore" -name "*_sk" | head -n1)

if [[ ! -f "$KEY_FULL_PATH" ]]; then
  echo "❌ Private key file not found!"
  exit 1
fi

# Extract filename only
KEY_FILENAME=$(basename "$KEY_FULL_PATH")
DOCKER_PATH="/tmp/crypto/peerOrganizations/$ORG_NAME/users/$USER_NAME/msp/keystore/$KEY_FILENAME"

echo "✅ Found private key file: $KEY_FILENAME"
echo "🔁 Replacing adminPrivateKey path with: $DOCKER_PATH"

# ——— Update only the privateKey path ———
TMP_FILE=$(mktemp)

jq --arg key "$DOCKER_PATH" \
   '.organizations.Org1MSP.adminPrivateKey.path = $key' \
   "$PROFILE_PATH" > "$TMP_FILE"

# Also update the signedCert path
jq --arg cert "/tmp/crypto/peerOrganizations/$ORG_NAME/users/$USER_NAME/msp/signcerts/cert.pem" \
   '.organizations.Org1MSP.signedCert.path = $cert' \
   "$TMP_FILE" > "${TMP_FILE}.2"

mv "${TMP_FILE}.2" "$PROFILE_PATH"

echo "✅ Updated $PROFILE_PATH with correct paths"

# ——— Restart Explorer ———
echo "🔁 Stopping existing Explorer containers..."
# docker-compose down

echo "🚀 Starting Hyperledger Explorer..."
docker-compose up -d
docker-compose ps