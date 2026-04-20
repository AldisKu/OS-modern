#!/usr/bin/env bash
set -euo pipefail

# OrderSprinter Modern Interface - Complete Setup Script
# Downloads sources, installs dependencies, deploys, and starts services
# Usage: bash setup-modern-os.sh

REPO_URL="https://github.com/AldisKu/orders.git"
REPO_BRANCH="main"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
  echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
  echo -e "${GREEN}[✓]${NC} $1"
}

log_warn() {
  echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
  echo -e "${RED}[ERROR]${NC} $1"
}

confirm() {
  local prompt="$1"
  local response
  read -p "$(echo -e ${YELLOW}$prompt${NC}) (y/n) " response
  [[ "$response" =~ ^[Yy]$ ]]
}

# ============================================================================
# STEP 1: Detect WEBROOT
# ============================================================================
detect_webroot() {
  log_info "Detecting WEBROOT..."
  
  local candidates=(
    "/var/www/webapp"
    "/var/www/html"
    "/srv/www/htdocs"
    "/srv/www"
    "/var/www"
  )
  
  for d in "${candidates[@]}"; do
    if [[ -f "$d/php/contenthandler.php" ]] && [[ -f "$d/waiter.html" ]]; then
      log_success "Found WEBROOT: $d"
      echo "$d"
      return 0
    fi
  done
  
  log_error "WEBROOT not found in standard locations"
  return 1
}

# ============================================================================
# STEP 2: Detect Git Repo Folder
# ============================================================================
detect_git_folder() {
  log_info "Detecting git repository folder..."
  
  # Check if we're already in a git repo
  if [[ -d ".git" ]]; then
    log_success "Found git repo in current directory"
    pwd
    return 0
  fi
  
  # Check common locations
  local candidates=(
    "$HOME/ordersprinter"
    "$HOME/orders"
    "/opt/ordersprinter"
    "/opt/orders"
  )
  
  for d in "${candidates[@]}"; do
    if [[ -d "$d/.git" ]]; then
      log_success "Found git repo: $d"
      echo "$d"
      return 0
    fi
  done
  
  log_warn "No existing git repo found"
  return 1
}

# ============================================================================
# STEP 3: Check Dependencies
# ============================================================================
check_dependencies() {
  log_info "Checking dependencies..."
  
  local missing=()
  
  # Check for curl or wget
  if ! command -v curl &> /dev/null && ! command -v wget &> /dev/null; then
    missing+=("curl or wget")
  fi
  
  # Check for git
  if ! command -v git &> /dev/null; then
    log_warn "git not found - will install"
  fi
  
  # Check for Node.js
  if ! command -v node &> /dev/null; then
    log_warn "Node.js not found - will install"
  fi
  
  # Check for npm
  if ! command -v npm &> /dev/null; then
    log_warn "npm not found - will install with Node.js"
  fi
  
  if [[ ${#missing[@]} -gt 0 ]]; then
    log_error "Missing required tools: ${missing[*]}"
    return 1
  fi
  
  log_success "All required tools available"
  return 0
}

# ============================================================================
# STEP 4: Install Dependencies
# ============================================================================
install_dependencies() {
  log_info "Installing dependencies..."
  
  # Detect OS
  if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    OS=$ID
  else
    log_error "Cannot detect OS"
    return 1
  fi
  
  # Install git if needed
  if ! command -v git &> /dev/null; then
    log_info "Installing git..."
    if [[ "$OS" == "ubuntu" ]] || [[ "$OS" == "debian" ]]; then
      sudo apt-get update
      sudo apt-get install -y git
    elif [[ "$OS" == "centos" ]] || [[ "$OS" == "rhel" ]]; then
      sudo yum install -y git
    else
      log_error "Unsupported OS: $OS"
      return 1
    fi
    log_success "git installed"
  fi
  
  # Install Node.js if needed
  if ! command -v node &> /dev/null; then
    log_info "Installing Node.js..."
    if [[ "$OS" == "ubuntu" ]] || [[ "$OS" == "debian" ]]; then
      curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
      sudo apt-get install -y nodejs
    elif [[ "$OS" == "centos" ]] || [[ "$OS" == "rhel" ]]; then
      curl -fsSL https://rpm.nodesource.com/setup_18.x | sudo bash -
      sudo yum install -y nodejs
    else
      log_error "Unsupported OS: $OS"
      return 1
    fi
    log_success "Node.js installed"
  fi
  
  log_success "All dependencies installed"
  return 0
}

# ============================================================================
# STEP 5: Download/Clone Sources
# ============================================================================
download_sources() {
  local git_folder="$1"
  
  if [[ -d "$git_folder/.git" ]]; then
    log_info "Git repo already exists at $git_folder"
    if confirm "Update existing repo with git pull?"; then
      cd "$git_folder"
      git pull origin "$REPO_BRANCH"
      log_success "Repository updated"
    fi
  else
    log_info "Cloning repository to $git_folder..."
    mkdir -p "$(dirname "$git_folder")"
    git clone --branch "$REPO_BRANCH" "$REPO_URL" "$git_folder"
    log_success "Repository cloned"
  fi
}

# ============================================================================
# STEP 6: Run Deploy Script
# ============================================================================
run_deploy() {
  local git_folder="$1"
  local webroot="$2"
  
  log_info "Running deployment script..."
  
  if [[ ! -f "$git_folder/deploy-modern.sh" ]]; then
    log_error "Deploy script not found at $git_folder/deploy-modern.sh"
    return 1
  fi
  
  cd "$git_folder"
  WEBROOT="$webroot" sudo bash deploy-modern.sh --v
  
  log_success "Deployment complete"
  return 0
}

# ============================================================================
# STEP 7: Setup Broker Service
# ============================================================================
setup_broker_service() {
  local webroot="$1"
  local broker_path="$webroot/modern/broker/server.js"
  
  log_info "Setting up broker systemd service..."
  
  if [[ ! -f "$broker_path" ]]; then
    log_error "Broker server not found at $broker_path"
    return 1
  fi
  
  # Create systemd service file
  local service_file="/etc/systemd/system/ordersprinter-broker.service"
  
  log_info "Creating systemd service file..."
  sudo tee "$service_file" > /dev/null <<EOF
[Unit]
Description=OrderSprinter WebSocket Broker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=$webroot/modern/broker
ExecStart=/usr/bin/node $broker_path
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF
  
  log_success "Service file created"
  
  # Reload systemd
  log_info "Reloading systemd..."
  sudo systemctl daemon-reload
  
  # Enable service
  log_info "Enabling broker service..."
  sudo systemctl enable ordersprinter-broker.service
  
  log_success "Broker service configured"
  return 0
}

# ============================================================================
# STEP 8: Start Broker Service
# ============================================================================
start_broker_service() {
  log_info "Starting broker service..."
  
  sudo systemctl start ordersprinter-broker.service
  
  # Wait for broker to start
  sleep 2
  
  # Check if running
  if sudo systemctl is-active --quiet ordersprinter-broker.service; then
    log_success "Broker service started"
    
    # Check broker health
    log_info "Checking broker health..."
    if curl -s http://127.0.0.1:3077/health > /dev/null 2>&1; then
      log_success "Broker is healthy"
      return 0
    else
      log_warn "Broker health check failed - may still be starting"
      return 0
    fi
  else
    log_error "Broker service failed to start"
    sudo systemctl status ordersprinter-broker.service
    return 1
  fi
}

# ============================================================================
# STEP 9: Create Config File
# ============================================================================
create_config_file() {
  local git_folder="$1"
  local webroot="$2"
  local config_file="$git_folder/.ordersprinter-config"
  
  log_info "Creating configuration file..."
  
  cat > "$config_file" <<EOF
# OrderSprinter Configuration
# Generated: $TIMESTAMP

# Paths
GIT_FOLDER="$git_folder"
WEBROOT="$webroot"
DEPLOY_SCRIPT="$git_folder/deploy-modern.sh"

# Broker
BROKER_PATH="$webroot/modern/broker/server.js"
BROKER_SERVICE="ordersprinter-broker.service"
BROKER_PORT="3077"

# Repository
REPO_URL="$REPO_URL"
REPO_BRANCH="$REPO_BRANCH"

# Quick Commands
# Update and deploy:
#   cd $git_folder && git pull && WEBROOT=$webroot sudo bash deploy-modern.sh --v
#
# Restart broker:
#   sudo systemctl restart ordersprinter-broker.service
#
# Check broker status:
#   sudo systemctl status ordersprinter-broker.service
#
# View broker logs:
#   sudo journalctl -u ordersprinter-broker.service -f
EOF
  
  log_success "Configuration file created: $config_file"
}

# ============================================================================
# STEP 10: Display Documentation
# ============================================================================
display_documentation() {
  local git_folder="$1"
  local webroot="$2"
  
  cat <<EOF

${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}
${GREEN}║    OrderSprinter Modern Interface - Installation Complete     ║${NC}
${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}

${BLUE}Installation Summary:${NC}
  Git Repository:    $git_folder
  Web Root:          $webroot
  Broker Service:    ordersprinter-broker.service
  Broker Port:       3077

${BLUE}Quick Start:${NC}
  1. Open browser: http://$(hostname -I | awk '{print $1}')
  2. Login with your credentials
  3. Configure table layout in local config
  4. Connect customer display

${BLUE}Useful Commands:${NC}
  Update and deploy:
    cd $git_folder && git pull && WEBROOT=$webroot sudo bash deploy-modern.sh --v

  Restart broker:
    sudo systemctl restart ordersprinter-broker.service

  Check broker status:
    sudo systemctl status ordersprinter-broker.service

  View broker logs:
    sudo journalctl -u ordersprinter-broker.service -f

${BLUE}Documentation:${NC}
  See: $git_folder/README.md
  Setup Guide: $git_folder/SETUP_GUIDE.md (German)

${BLUE}Configuration:${NC}
  Config file: $git_folder/.ordersprinter-config

${GREEN}Installation successful!${NC}

EOF
}

# ============================================================================
# MAIN FLOW
# ============================================================================
main() {
  echo ""
  echo "╔════════════════════════════════════════════════════════════════╗"
  echo "║    OrderSprinter Modern Interface - Setup Script v39          ║"
  echo "╚════════════════════════════════════════════════════════════════╝"
  echo ""
  
  # Check if running as root for some operations
  if [[ $EUID -ne 0 ]]; then
    log_warn "This script will use sudo for system operations"
  fi
  
  # Step 1: Detect WEBROOT
  WEBROOT=$(detect_webroot) || {
    log_error "Cannot proceed without WEBROOT"
    exit 1
  }
  
  if ! confirm "Use WEBROOT: $WEBROOT?"; then
    read -p "Enter WEBROOT path: " WEBROOT
  fi
  
  # Step 2: Detect or ask for git folder
  GIT_FOLDER=$(detect_git_folder) || {
    read -p "Enter git repository folder path (default: $HOME/ordersprinter): " GIT_FOLDER
    GIT_FOLDER="${GIT_FOLDER:-$HOME/ordersprinter}"
  }
  
  if ! confirm "Use git folder: $GIT_FOLDER?"; then
    read -p "Enter git repository folder path: " GIT_FOLDER
  fi
  
  # Step 3: Check dependencies
  check_dependencies || {
    log_warn "Some dependencies missing, will attempt to install"
  }
  
  # Step 4: Install dependencies
  if confirm "Install/update dependencies (git, Node.js)?"; then
    install_dependencies || {
      log_error "Failed to install dependencies"
      exit 1
    }
  fi
  
  # Step 5: Download sources
  download_sources "$GIT_FOLDER" || {
    log_error "Failed to download sources"
    exit 1
  }
  
  # Step 6: Run deploy script
  run_deploy "$GIT_FOLDER" "$WEBROOT" || {
    log_error "Deployment failed"
    exit 1
  }
  
  # Step 7: Setup broker service
  setup_broker_service "$WEBROOT" || {
    log_error "Failed to setup broker service"
    exit 1
  }
  
  # Step 8: Start broker service
  start_broker_service || {
    log_error "Failed to start broker service"
    exit 1
  }
  
  # Step 9: Create config file
  create_config_file "$GIT_FOLDER" "$WEBROOT"
  
  # Step 10: Display documentation
  display_documentation "$GIT_FOLDER" "$WEBROOT"
  
  log_success "Setup complete!"
}

# Run main
main "$@"
