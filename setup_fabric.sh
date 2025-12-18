#!/bin/bash

# Define colors for better output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

# Define paths
FABRIC_SAMPLES_PATH="$HOME/go/src/github/Kae/fabric-samples"
DOCKER_APP_PATH="$HOME/fyp/docker_b_app"
LOG_DIR="$HOME/fabric_logs"
LOG_FILE="$LOG_DIR/setup_$(date +%Y%m%d_%H%M%S).log"

# Create log directory if it doesn't exist
mkdir -p "$LOG_DIR"
touch "$LOG_FILE"

# Function for logging
log() {
    local message="$1"
    local status="$2"
    local timestamp=$(date "+%Y-%m-%d %H:%M:%S")
    
    case "$status" in
        "success")
            echo -e "${GREEN}[SUCCESS]${NC} $message"
            echo "[$timestamp] [SUCCESS] $message" >> "$LOG_FILE"
            ;;
        "error")
            echo -e "${RED}[ERROR]${NC} $message"
            echo "[$timestamp] [ERROR] $message" >> "$LOG_FILE"
            ;;
        "info")
            echo -e "${YELLOW}[INFO]${NC} $message"
            echo "[$timestamp] [INFO] $message" >> "$LOG_FILE"
            ;;
        *)
            echo -e "$message"
            echo "[$timestamp] $message" >> "$LOG_FILE"
            ;;
    esac
}

# Function to execute command with logging
exec_cmd() {
    local cmd="$1"
    local success_msg="$2"
    local error_msg="$3"
    
    # Log the command being executed
    echo "Executing: $cmd" >> "$LOG_FILE"
    
    # Execute the command and capture output
    local output
    output=$(eval "$cmd" 2>&1)
    local status=$?
    
    # Log the command output
    echo "Command output:" >> "$LOG_FILE"
    echo "$output" >> "$LOG_FILE"
    echo "----------------------------------------" >> "$LOG_FILE"
    
    # Check status and log appropriate message
    if [ $status -eq 0 ]; then
        log "$success_msg" "success"
        return 0
    else
        log "$error_msg" "error"
        log "Error details: $output" "error"
        return 1
    fi
}


# Function to check if command was successful
check_status() {
    if [ $? -eq 0 ]; then
        log "$1" "success"
        return 0
    else
        log "$2" "error"
        return 1
    fi
}

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..." "info"
    
    # Check if Docker is running
    if ! docker info >/dev/null 2>&1; then
        log "Docker is not running. Please start Docker." "error"
        exit 1
    fi
    
    # Check if required directories exist
    if [ ! -d "$FABRIC_SAMPLES_PATH" ]; then
        log "Fabric Samples path does not exist: $FABRIC_SAMPLES_PATH" "error"
        exit 1
    fi
    
    if [ ! -d "$DOCKER_APP_PATH" ]; then
        log "Docker app path does not exist: $DOCKER_APP_PATH" "error"
        exit 1
    fi
    
    # Check if Node.js is installed (needed for enrollment)
    if ! command -v node >/dev/null 2>&1; then
        log "Node.js is not installed. Please install Node.js." "error"
        exit 1
    fi
    
    log "All prerequisites are met" "success"
}

# Setup Fabric Network
setup_fabric_network() {
    log "Setting up Fabric Network..." "info"
    
    if ! cd "$FABRIC_SAMPLES_PATH/test-network/"; then
        log "Failed to change directory to $FABRIC_SAMPLES_PATH/test-network/" "error"
        return 1
    fi
    
    # Ensure network is down before starting
    exec_cmd "./network.sh down" "Network down completed" "Network down had warnings (proceeding anyway)"
    
    # Start network with CA
    exec_cmd "./network.sh up createChannel -ca" "Fabric blockchain network is up with channel created" "Failed to bring up Fabric network"
}

# Setup Monitoring
setup_monitoring() {
    log "Setting up Prometheus and Grafana..." "info"
    
    if ! cd "$FABRIC_SAMPLES_PATH/test-network/prometheus-grafana"; then
        log "Failed to change directory to prometheus-grafana" "error"
        return 1
    fi
    
    exec_cmd "docker-compose up -d" "Prometheus and Grafana are running" "Failed to start Prometheus and Grafana"
}

# Deploy Chaincode
deploy_chaincode() {
    log "Deploying chaincode..." "info"
    
    if ! cd "$FABRIC_SAMPLES_PATH/test-network/"; then
        log "Failed to change directory to test-network" "error"
        return 1
    fi
    
    exec_cmd "./network.sh deployCC -ccn trafficviolation -ccp ../chaincode/trafficviolation/ -ccl go" \
            "Chaincode has deployed successfully" "Failed to deploy chaincode"
}

# Setup MongoDB
setup_mongodb() {
    log "Setting up MongoDB..." "info"
    
    # Check if MongoDB container exists
    if docker ps -a | grep -q mongodb; then
        # Start MongoDB if it exists but is not running
        if ! docker ps | grep -q mongodb; then
            exec_cmd "docker start mongodb" "MongoDB has started successfully" "Failed to start MongoDB"
        else
            log "MongoDB is already running" "info"
        fi
    else
        log "MongoDB container does not exist." "info"
        log "Creating MongoDB container..." "info"
        exec_cmd "docker run --name mongodb -d -p 27017:27017 -v /var/www/html/FYP/mongodb_data:/data/db mongo:latest" \
                "MongoDB container created and started" "Failed to create MongoDB container"
    fi
}

# Setup Fabric SDK API
setup_fabric_sdk() {
    log "Setting up Fabric SDK API..." "info"
    
    if ! cd "$DOCKER_APP_PATH"; then
        log "Failed to change directory to $DOCKER_APP_PATH" "error"
        return 1
    fi
    
    log "Resetting certificates and wallet..." "info"
    
    # Remove existing wallet and network directories and recreate them
    exec_cmd "rm -rf wallet/ network/ && mkdir -p network" \
            "Wallet and network directories reset" "Failed to reset wallet and network directories"
    
    # Copy connection profiles
    exec_cmd "cp \"$FABRIC_SAMPLES_PATH/test-network/organizations/peerOrganizations/org1.example.com/connection-org1.json\" network/connection-org1.json" \
            "Connection profile for Org1 copied" "Failed to copy connection profile for Org1"
            
    exec_cmd "cp \"$FABRIC_SAMPLES_PATH/test-network/organizations/peerOrganizations/org2.example.com/connection-org2.json\" network/connection-org2.json" \
            "Connection profile for Org2 copied" "Failed to copy connection profile for Org2"
    
    # Enroll admins
    log "Enrolling admin-org1..." "info"
    exec_cmd "node enrollAdminOrg1.js" "Admin Org1 enrolled successfully" "Failed to enroll Admin Org1"
    
    log "Enrolling admin-org2..." "info"
    exec_cmd "node enrollAdminOrg2.js" "Admin Org2 enrolled successfully" "Failed to enroll Admin Org2"
    
    log "Certificate setup completed" "success"
}

# Start Fabric API Docker Container
start_fabric_api() {
    log "Starting Fabric SDK API Docker container..." "info"
    
    if ! cd "$DOCKER_APP_PATH"; then
        log "Failed to change directory to $DOCKER_APP_PATH" "error"
        return 1
    fi
    
    # Check if container already exists
    if docker ps -a | grep -q fabricAPI; then
        log "Removing existing fabricAPI container" "info"
        exec_cmd "docker rm -f fabricAPI" "Existing fabricAPI container removed" "Failed to remove existing fabricAPI container"
    fi
    
    # Start the container with proper logging
    exec_cmd "docker run -d -v $(pwd)/wallet:/usr/src/app/wallet \
                -v $(pwd)/network:/usr/src/app/network \
                --network=\"host\" \
                --name fabricAPI \
                docker_b_app" \
                "Fabric SDK API container started" "Failed to start Fabric SDK API container"
    
    # Verify the container is running
    if docker ps | grep -q fabricAPI; then
        log "Fabric SDK API is running and accessible" "success"
    else
        log "Fabric SDK API container failed to start properly" "error"
        # Show logs from the container to help with debugging
        exec_cmd "docker logs fabricAPI" "Container logs retrieved" "Failed to retrieve container logs"
    fi
}

# Main execution function
main() {
    log "===== Starting Hyperledger Fabric Environment Setup =====" "info"
    log "Timestamp: $(date)" "info"
    
    check_prerequisites || { log "Prerequisites check failed. Exiting." "error"; exit 1; }
    setup_fabric_network || handle_error "setup_fabric_network" "Failed to set up Fabric network"
    setup_monitoring || handle_error "setup_monitoring" "Failed to set up monitoring"
    deploy_chaincode || handle_error "deploy_chaincode" "Failed to deploy chaincode"
    setup_mongodb || handle_error "setup_mongodb" "Failed to set up MongoDB"
    setup_fabric_sdk || handle_error "setup_fabric_sdk" "Failed to set up Fabric SDK"
    start_fabric_api || handle_error "start_fabric_api" "Failed to start Fabric API"
       
    cd $HOME/go/src/github/Kae/explorer
    ./setup_explorer.sh 
    
    log "===== Hyperledger Fabric Environment Setup Complete =====" "success"
    log "Setup log saved to $LOG_FILE" "info"
}
# Execute main function
main


