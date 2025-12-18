#!/bin/bash

# Define colors for better output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

# Define paths
FABRIC_PATH="$HOME/go/src/github/Kae/fabric-samples/test-network"
MONITORING_PATH="$FABRIC_PATH/prometheus-grafana"
LOG_DIR="$HOME/fabric_logs"
LOG_FILE="$LOG_DIR/shutdown_$(date +%Y%m%d_%H%M%S).log"

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
    echo "[$timestamp] Executing: $cmd" >> "$LOG_FILE"
    
    # Execute the command and capture output
    local output
    output=$(eval "$cmd" 2>&1)
    local status=$?
    
    # Log the command output
    echo "[$timestamp] Command output:" >> "$LOG_FILE"
    echo "$output" >> "$LOG_FILE"
    echo "[$timestamp] ----------------------------------------" >> "$LOG_FILE"
    
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

# Function to shutdown Fabric network
shutdown_fabric() {
    log "Shutting down the Fabric network..." "info"
    
    if [ ! -d "$FABRIC_PATH" ]; then
        log "Fabric path does not exist: $FABRIC_PATH" "error"
        return 1
    fi
    
    if ! cd "$FABRIC_PATH"; then
        log "Failed to change directory to $FABRIC_PATH" "error"
        return 1
    fi
    
    exec_cmd "./network.sh down" "Fabric network shutdown complete" "Failed to shut down Fabric network"
}

# Function to shutdown Prometheus and Grafana
shutdown_monitoring() {
    log "Shutting down Prometheus and Grafana..." "info"
    
    if [ ! -d "$MONITORING_PATH" ]; then
        log "Monitoring path does not exist: $MONITORING_PATH" "error"
        return 1
    fi
    
    if ! cd "$MONITORING_PATH"; then
        log "Failed to change directory to $MONITORING_PATH" "error"
        return 1
    fi
    
    # Check if monitoring is running
    if docker-compose ps | grep -q "Up"; then
        exec_cmd "docker-compose down" "Prometheus and Grafana shutdown complete" "Failed to shut down Prometheus and Grafana"
    else
        log "Prometheus and Grafana are not running" "info"
    fi
}

# Function to shutdown Fabric SDK API
shutdown_api() {
    log "Shutting down Fabric SDK API..." "info"
    
    # Check if container exists and is running
    if docker ps -a -q -f name=fabricAPI | grep -q .; then
        if docker ps -q -f name=fabricAPI | grep -q .; then
            exec_cmd "docker stop fabricAPI" "Fabric SDK API stopped" "Failed to stop Fabric SDK API"
        else
            log "Fabric SDK API is not running" "info"
        fi
        
        exec_cmd "docker rm fabricAPI" "Fabric SDK API container removed" "Failed to remove Fabric SDK API container"
    else
        log "Fabric SDK API container does not exist" "info"
    fi
}

# Main execution
main() {
    local timestamp=$(date)
    log "===== Starting Hyperledger Fabric Environment Shutdown =====" "info"
    log "Timestamp: $timestamp" "info"
    
    # Check if Docker is running before proceeding
    if ! docker info >/dev/null 2>&1; then
        log "Docker is not running. Please start Docker before attempting shutdown." "error"
        exit 1
    fi
    
    shutdown_fabric
    shutdown_monitoring
    shutdown_api
    
    cd $HOME/go/src/github/Kae/explorer
    docker-compose down -v
    log "Explorer containers shut down" "info"
    log "===== Shutdown Process Completed =====" "info"
    log "Shutdown log saved to $LOG_FILE" "info"
}

# Execute main function
main
