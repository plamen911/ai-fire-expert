#!/bin/bash
#
# Server Setup Script for Laravel RAG App
# Run as root on a fresh Ubuntu 24.04 VPS (e.g., Hetzner CX22)
#
# Usage: ssh root@<VPS_IP> 'bash -s' < deploy/setup-server.sh
#

set -euo pipefail

DEPLOY_USER="deploy"
APP_DIR="/home/${DEPLOY_USER}/laravel_rag"

echo "==> Updating system packages..."
apt update && apt upgrade -y

echo "==> Installing Docker..."
curl -fsSL https://get.docker.com | sh

echo "==> Installing Docker Compose plugin..."
apt install -y docker-compose-plugin

echo "==> Creating deploy user..."
if ! id "${DEPLOY_USER}" &>/dev/null; then
    adduser --disabled-password --gecos "" "${DEPLOY_USER}"
    usermod -aG docker "${DEPLOY_USER}"
    echo "${DEPLOY_USER} ALL=(ALL) NOPASSWD:ALL" > "/etc/sudoers.d/${DEPLOY_USER}"

    # Copy SSH keys from root to deploy user
    mkdir -p "/home/${DEPLOY_USER}/.ssh"
    cp /root/.ssh/authorized_keys "/home/${DEPLOY_USER}/.ssh/"
    chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh"
    chmod 700 "/home/${DEPLOY_USER}/.ssh"
    chmod 600 "/home/${DEPLOY_USER}/.ssh/authorized_keys"
fi

echo "==> Configuring firewall..."
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "==> Setting up swap (1GB)..."
if [ ! -f /swapfile ]; then
    fallocate -l 1G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> Installing fail2ban..."
apt install -y fail2ban
systemctl enable fail2ban
systemctl start fail2ban

echo "==> Setting up automatic security updates..."
apt install -y unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades

echo ""
echo "============================================"
echo " Server setup complete!"
echo "============================================"
echo ""
echo " Next steps:"
echo "  1. SSH in as deploy user:  ssh ${DEPLOY_USER}@$(hostname -I | awk '{print $1}')"
echo "  2. Clone your repo:        git clone <your-repo-url> ${APP_DIR}"
echo "  3. Create .env.production:  cp ${APP_DIR}/.env.production.example ${APP_DIR}/.env.production"
echo "  4. Edit .env.production:    nano ${APP_DIR}/.env.production"
echo "     - Set APP_KEY (run: php artisan key:generate --show)"
echo "     - Set DB_PASSWORD and DOCKER_DB_PASSWORD (same strong password)"
echo "     - Set API keys (GROQ, OPENAI, COHERE)"
echo "     - Set APP_URL to your domain"
echo "  5. Deploy:                  cd ${APP_DIR} && bash deploy/deploy.sh"
echo ""
